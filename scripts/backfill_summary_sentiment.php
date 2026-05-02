<?php
/**
 * Backfill summaries and sentiment for articles that are missing them.
 * Runs after the main enrichment to avoid memory contention.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\DB;
use App\Services\ExtractorClient;

$pdo = DB::pdo();
echo "=== Backfill Summaries & Sentiment ===\n\n";

$articles = $pdo->query("
    SELECT id, title, content, excerpt
    FROM articles
    WHERE deleted_at IS NULL AND status = 'published'
      AND (ai_summary IS NULL OR ai_summary = '' OR sentiment IS NULL)
    ORDER BY published_at DESC
    LIMIT 300
")->fetchAll(\PDO::FETCH_ASSOC);

$total = count($articles);
echo "{$total} articles to process\n\n";

$stmtSummary = $pdo->prepare("UPDATE articles SET ai_summary = :sum WHERE id = :id");
$stmtSentiment = $pdo->prepare("UPDATE articles SET sentiment = :sent, sentiment_score = :score WHERE id = :id");

$done = ['summary' => 0, 'sentiment' => 0, 'errors' => 0];

foreach ($articles as $i => $art) {
    $n = $i + 1;
    $text = strip_tags($art['content'] ?? $art['excerpt'] ?? $art['title']);
    if (mb_strlen($text) < 20) { echo "[{$n}] SKIP\n"; continue; }

    $short = mb_substr($text, 0, 60);
    echo "[{$n}/{$total}] {$short}...";

    // Summary
    if (empty($art['ai_summary'])) {
        try {
            $summary = ExtractorClient::summarize(mb_substr($text, 0, 2000));
            if ($summary && is_string($summary) && mb_strlen($summary) > 5) {
                $stmtSummary->execute([':sum' => $summary, ':id' => $art['id']]);
                $done['summary']++;
            }
        } catch (\Throwable $e) {
            echo " sum-err";
            $done['errors']++;
        }
    }

    // Sentiment
    try {
        $sent = ExtractorClient::analyzeSentiment(mb_substr($text, 0, 1000));
        if ($sent && isset($sent['sentiment'])) {
            $stmtSentiment->execute([
                ':sent' => $sent['sentiment'],
                ':score' => $sent['sentiment_score'] ?? $sent['score'] ?? 0,
                ':id' => $art['id'],
            ]);
            $done['sentiment']++;
        }
    } catch (\Throwable $e) {
        echo " sent-err";
        $done['errors']++;
    }

    echo " OK\n";

    // Pause every 5 articles to let the extractor breathe
    if ($n % 5 === 0) usleep(200000);
}

echo "\n=== Done ===\n";
echo "  Summaries:  {$done['summary']}\n";
echo "  Sentiment:  {$done['sentiment']}\n";
echo "  Errors:     {$done['errors']}\n";
