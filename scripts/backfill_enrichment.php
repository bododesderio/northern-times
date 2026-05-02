<?php
/**
 * backfill_enrichment.php — Enrich existing articles with AI features.
 *
 * Adds: embeddings, summaries, sentiment, quality scores, NER tags.
 * Processes in batches to avoid overloading the extractor.
 *
 * Usage:
 *   php scripts/backfill_enrichment.php              # Process all un-enriched
 *   php scripts/backfill_enrichment.php --limit=50   # Process max 50
 *   php scripts/backfill_enrichment.php --dry-run    # Show counts only
 *
 * Run: docker compose exec -T app php scripts/backfill_enrichment.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\DB;
use App\Services\ExtractorClient;

$pdo = DB::pdo();
echo "=== Northern Times AI Enrichment Backfill ===\n\n";

$dryRun = in_array('--dry-run', $argv ?? [], true);
$limit  = 500;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int)substr($arg, 8);
    }
}
$skipSummary = in_array('--skip-summary', $argv ?? [], true);

// Count un-enriched articles
$counts = $pdo->query("
    SELECT
        count(*) FILTER (WHERE embedding IS NULL) as need_embedding,
        count(*) FILTER (WHERE ai_summary IS NULL OR ai_summary = '') as need_summary,
        count(*) FILTER (WHERE sentiment IS NULL) as need_sentiment,
        count(*) FILTER (WHERE quality_score IS NULL) as need_quality
    FROM articles WHERE deleted_at IS NULL AND status = 'published'
")->fetch(\PDO::FETCH_ASSOC);

echo "Articles needing enrichment:\n";
echo "  Embeddings:  {$counts['need_embedding']}\n";
echo "  Summaries:   {$counts['need_summary']}\n";
echo "  Sentiment:   {$counts['need_sentiment']}\n";
echo "  Quality:     {$counts['need_quality']}\n\n";

if ($dryRun) {
    echo "Dry run — exiting.\n";
    exit(0);
}

// Check extractor health
$health = @json_decode((string)@file_get_contents(
    ($_ENV['EXTRACTOR_URL'] ?? 'http://extractor:5000') . '/health'
), true);
if (!$health || ($health['status'] ?? '') !== 'ok') {
    echo "ERROR: Extractor service not healthy.\n";
    exit(1);
}
echo "Extractor: OK (models: " . implode(', ', array_keys(array_filter($health['models'] ?? [], fn($v) => $v === 'loaded'))) . ")\n\n";

// Fetch articles that need enrichment
$articles = $pdo->query("
    SELECT id, title, excerpt, content, featured_image
    FROM articles
    WHERE deleted_at IS NULL AND status = 'published'
      AND (embedding IS NULL OR ai_summary IS NULL OR ai_summary = '' OR sentiment IS NULL OR quality_score IS NULL)
    ORDER BY published_at DESC
    LIMIT {$limit}
")->fetchAll(\PDO::FETCH_ASSOC);

$total = count($articles);
echo "Processing {$total} articles...\n\n";

$stmtEmbed = $pdo->prepare("UPDATE articles SET embedding = :emb WHERE id = :id");
$stmtSummary = $pdo->prepare("UPDATE articles SET ai_summary = :sum WHERE id = :id");
$stmtSentiment = $pdo->prepare("UPDATE articles SET sentiment = :sent, sentiment_score = :score WHERE id = :id");
$stmtQuality = $pdo->prepare("UPDATE articles SET quality_score = :qs WHERE id = :id");

// Tags: ensure we can insert
$stmtFindTag = $pdo->prepare("SELECT id FROM tags WHERE slug = :slug LIMIT 1");
$stmtInsertTag = $pdo->prepare("INSERT INTO tags (name, slug, type) VALUES (:name, :slug, :type) ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name RETURNING id");
$stmtLinkTag = $pdo->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (:aid, :tid) ON CONFLICT DO NOTHING");

$enriched = ['embed' => 0, 'summary' => 0, 'sentiment' => 0, 'quality' => 0, 'tags' => 0];

foreach ($articles as $i => $art) {
    $n = $i + 1;
    $shortTitle = mb_substr($art['title'], 0, 60);
    echo "[{$n}/{$total}] {$shortTitle}...";

    $text = strip_tags($art['content'] ?? $art['excerpt'] ?? $art['title']);
    if (mb_strlen($text) < 20) {
        echo " SKIP (too short)\n";
        continue;
    }

    // Embedding (model handles 256 tokens, ~500 words)
    try {
        $embResult = ExtractorClient::embed(mb_substr($text, 0, 2000));
        if ($embResult && !empty($embResult['embedding'])) {
            $vec = '[' . implode(',', $embResult['embedding']) . ']';
            $stmtEmbed->execute([':emb' => $vec, ':id' => $art['id']]);
            $enriched['embed']++;
        }
    } catch (\Throwable $e) {
        echo " embed-err";
    }

    // Summary (skip with --skip-summary, as DistilBART is heavy on memory)
    if (!$skipSummary) {
        try {
            $summary = ExtractorClient::summarize(mb_substr($text, 0, 2000));
            if ($summary && is_string($summary) && mb_strlen($summary) > 5) {
                $stmtSummary->execute([':sum' => $summary, ':id' => $art['id']]);
                $enriched['summary']++;
            }
        } catch (\Throwable $e) {
            echo " sum-err";
        }
    }

    // Sentiment
    try {
        $sent = ExtractorClient::analyzeSentiment(mb_substr($text, 0, 1000));
        if ($sent && isset($sent['label'])) {
            $stmtSentiment->execute([
                ':sent' => $sent['label'],
                ':score' => $sent['score'] ?? 0,
                ':id' => $art['id'],
            ]);
            $enriched['sentiment']++;
        }
    } catch (\Throwable $e) {
        echo " sent-err";
    }

    // Quality score
    try {
        $imgCount = substr_count($art['content'] ?? '', '<img');
        $qs = ExtractorClient::qualityScore($text, $art['content'] ?? '', $imgCount);
        if ($qs !== null) {
            $stmtQuality->execute([':qs' => $qs, ':id' => $art['id']]);
            $enriched['quality']++;
        }
    } catch (\Throwable $e) {
        echo " qs-err";
    }

    // NER -> Tags
    try {
        $entities = ExtractorClient::extractEntities(mb_substr($text, 0, 2000), 10);
        if ($entities && is_array($entities)) {
            foreach ($entities as $ent) {
                $name = trim($ent['text'] ?? $ent['name'] ?? '');
                $type = strtolower($ent['label'] ?? $ent['type'] ?? 'topic');
                if (mb_strlen($name) < 2 || mb_strlen($name) > 100) continue;

                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
                $slug = trim($slug, '-');
                if (!$slug) continue;

                // Map NER label to tag type
                $tagType = match (true) {
                    in_array($type, ['person', 'per']) => 'person',
                    in_array($type, ['org', 'organization']) => 'org',
                    in_array($type, ['gpe', 'loc', 'location']) => 'location',
                    default => 'topic',
                };

                $stmtInsertTag->execute([':name' => $name, ':slug' => $slug, ':type' => $tagType]);
                $tagId = $stmtInsertTag->fetchColumn();

                if ($tagId) {
                    $stmtLinkTag->execute([':aid' => $art['id'], ':tid' => $tagId]);
                    $enriched['tags']++;
                }
            }
        }
    } catch (\Throwable $e) {
        echo " ner-err";
    }

    echo " OK\n";

    // Rate limit: small pause every 10 articles
    if ($n % 10 === 0) usleep(500000);
}

echo "\n=== Enrichment Complete ===\n";
echo "  Embeddings:  {$enriched['embed']}\n";
echo "  Summaries:   {$enriched['summary']}\n";
echo "  Sentiment:   {$enriched['sentiment']}\n";
echo "  Quality:     {$enriched['quality']}\n";
echo "  Tag links:   {$enriched['tags']}\n";
