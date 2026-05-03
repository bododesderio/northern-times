<?php
/**
 * AI Rewriter Cron Job
 *
 * Processes articles queued for AI rewriting. Runs every minute via crontab.
 * Picks up articles with rewrite_status='queued', rewrites via Ollama,
 * and stores the rewritten content in rewritten_* columns pending approval.
 *
 * Workflow:
 *  - Rewritten content goes into rewritten_title/content/excerpt columns
 *  - Main title/content/excerpt are NEVER overwritten unless auto_apply is on
 *  - If auto_apply=true: also copies rewrite into main columns immediately
 *  - If auto_apply=false: sets rewrite_status='pending_approval' for editor review
 *  - A revision snapshot is always created before any content change
 *
 * Crontab:
 *   */10 * * * * cd /var/www/html && php cron/rewrite.php >> storage/logs/rewriter.log 2>&1
 */

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '256M');

// Lock file — prevent overlapping runs
$lockFile   = sys_get_temp_dir() . '/nt_rewriter.lock';
$lockHandle = @fopen($lockFile, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo '[' . date('Y-m-d H:i:s') . "] Rewriter already running (locked). Skipping.\n";
    if ($lockHandle) fclose($lockHandle);
    exit(0);
}
ftruncate($lockHandle, 0);
fwrite($lockHandle, (string)getmypid());
fflush($lockHandle);

register_shutdown_function(function () use ($lockHandle, $lockFile) {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
    @unlink($lockFile);
});

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}
require_once __DIR__ . '/../app/Support/helpers.php';
\App\Services\ErrorTracker::register();

$log = static function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
};

// Check enabled
if (($_ENV['REWRITER_ENABLED'] ?? 'false') !== 'true') {
    $log('Rewriter disabled (REWRITER_ENABLED != true). Exiting.');
    exit(0);
}

use App\Models\ArticleRevision;
use App\Models\Setting;
use App\Services\DB;
use App\Services\RewriterClient;

// Health check before processing
if (!RewriterClient::isHealthy()) {
    $log('Rewriter service unavailable. Skipping batch.');
    exit(0);
}

// Load settings
$rules      = Setting::get('rewriter_rules', '');
$maxWords   = (int)Setting::get('rewriter_max_words', '4000');
$autoApply  = Setting::get('rewriter_auto_apply', 'false') === 'true';
$batchSize  = max(1, (int)($_ENV['REWRITER_BATCH_SIZE'] ?? 3));

$pdo = DB::pdo();

// Fetch queued articles (recover any stuck in 'processing' for > 30 min)
$stmt = $pdo->prepare(
    "UPDATE articles
        SET rewrite_status = 'processing'
      WHERE id IN (
          SELECT id FROM articles
           WHERE rewrite_status = 'queued'
              OR (rewrite_status = 'processing' AND updated_at < NOW() - INTERVAL '30 minutes')
           ORDER BY created_at ASC
           LIMIT :limit
           FOR UPDATE SKIP LOCKED
      )
      RETURNING id, title, content, excerpt"
);
$stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (empty($articles)) {
    $log('No articles queued for rewriting.');
    exit(0);
}

$log(sprintf('Processing %d article(s)... auto_apply=%s max_words=%d',
    count($articles), $autoApply ? 'yes' : 'no', $maxWords));

$done = 0;
$failed = 0;

foreach ($articles as $article) {
    $id    = $article['id'];
    $title = $article['title'] ?? '';

    try {
        $result = RewriterClient::rewrite(
            title:         $article['title']   ?? '',
            content:       $article['content'] ?? '',
            excerpt:       $article['excerpt'] ?? '',
            rules:         $rules ?: null,
            maxChunkWords: $maxWords > 0 ? $maxWords : null,
        );

        if ($result === null) {
            throw new \RuntimeException('RewriterClient returned null');
        }

        // Create revision snapshot of the current (original) content
        try {
            ArticleRevision::createRevision(
                $id, null,
                $article['title']   ?? '',
                $article['content'] ?? '',
                $article['excerpt'] ?? ''
            );
        } catch (\Throwable $e) {
            error_log("Rewriter: revision snapshot failed for #{$id}: " . $e->getMessage());
        }

        if ($autoApply) {
            // Auto-apply: store rewrite in both rewritten_* AND main columns
            $upd = $pdo->prepare(
                "UPDATE articles SET
                    rewritten_title   = :rw_title,
                    rewritten_content = :rw_content,
                    rewritten_excerpt = :rw_excerpt,
                    title             = :title,
                    content           = :content,
                    excerpt           = :excerpt,
                    rewrite_status    = 'approved',
                    rewritten_at      = NOW(),
                    rewriter_model    = :model,
                    updated_at        = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':rw_title'   => mb_substr($result['title'],   0, 255),
                ':rw_content' => $result['content'],
                ':rw_excerpt' => mb_substr($result['excerpt'], 0, 500),
                ':title'      => mb_substr($result['title'],   0, 255),
                ':content'    => $result['content'],
                ':excerpt'    => mb_substr($result['excerpt'], 0, 500),
                ':model'      => mb_substr($result['model_used'], 0, 100),
                ':id'         => $id,
            ]);
            $statusLabel = 'approved (auto-applied)';
        } else {
            // Pending approval: store rewrite in rewritten_* columns only
            $upd = $pdo->prepare(
                "UPDATE articles SET
                    rewritten_title   = :rw_title,
                    rewritten_content = :rw_content,
                    rewritten_excerpt = :rw_excerpt,
                    rewrite_status    = 'pending_approval',
                    rewritten_at      = NOW(),
                    rewriter_model    = :model,
                    updated_at        = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':rw_title'   => mb_substr($result['title'],   0, 255),
                ':rw_content' => $result['content'],
                ':rw_excerpt' => mb_substr($result['excerpt'], 0, 500),
                ':model'      => mb_substr($result['model_used'], 0, 100),
                ':id'         => $id,
            ]);
            $statusLabel = 'pending_approval';
        }

        $chunks = $result['chunks_processed'] ?? 1;
        $log(sprintf(
            "  [%s] #%s \"%s\" — %d→%d words, %d chunk(s) (%s)",
            $statusLabel, $id, mb_substr($title, 0, 60),
            $result['original_word_count'], $result['word_count'],
            $chunks, $result['model_used']
        ));
        $done++;

    } catch (\Throwable $e) {
        $pdo->prepare("UPDATE articles SET rewrite_status = 'failed', updated_at = NOW() WHERE id = :id")
            ->execute([':id' => $id]);
        $log(sprintf('  [fail] #%s "%s" — %s', $id, mb_substr($title, 0, 60), $e->getMessage()));
        $failed++;
    }
}

$log(sprintf('Batch complete. done=%d failed=%d', $done, $failed));
