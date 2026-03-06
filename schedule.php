<?php
/**
 * Scheduler — run via cron every minute:
 *   * * * * * php /var/www/html/schedule.php >> /var/www/html/storage/logs/schedule.log 2>&1
 *
 * Tasks:
 *   1. Publish scheduled articles whose published_at <= NOW()
 */
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

require __DIR__ . '/app/Support/helpers.php';

// Silence display, log errors
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/php-errors.log');

$ts = date('Y-m-d H:i:s');

try {
    // Track this cron run
    $run = null;
    try {
        $run = \App\Models\CronRun::start('publish_scheduled');
    } catch (\Throwable $e) {
        // Cron tracking table may not exist yet
    }

    $count = \App\Models\Article::publishScheduled();

    if ($count > 0) {
        echo "[{$ts}] Published {$count} scheduled article(s).\n";
    }

    // Send due scheduled newsletters
    $nlCount = 0;
    try {
        $dueIssues = \App\Models\NewsletterIssue::dueForSend();
        foreach ($dueIssues as $issue) {
            $articleIds = json_decode($issue['article_ids'] ?? '[]', true) ?: [];
            if (empty($articleIds)) {
                \App\Models\NewsletterIssue::markSent($issue['id'], 0, 0);
                continue;
            }

            $subscribers = \App\Models\Subscriber::activeSubscribers();
            if (empty($subscribers)) {
                \App\Models\NewsletterIssue::markSent($issue['id'], 0, 0);
                continue;
            }

            \App\Models\NewsletterIssue::markSending($issue['id']);

            $articles = \App\Models\Article::query(
                "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                        COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') AS author
                 FROM articles a LEFT JOIN users u ON u.id = a.author_id
                 WHERE a.id = ANY(:ids)",
                [':ids' => '{' . implode(',', $articleIds) . '}']
            );

            $queued = 0;
            $subject = $issue['subject'];
            $intro   = $issue['body_html'] ?: null;
            foreach ($subscribers as $sub) {
                $token = $sub['unsub_token'] ?? '';
                $html  = \App\Services\Mailer::digestEmail($subject, $articles, $token, $intro);
                \App\Services\Mailer::queue($sub['email'], $subject, $html, null, $sub['name'] ?? null);
                $queued++;
            }
            \App\Models\NewsletterIssue::markSent($issue['id'], $queued, 0);
            $nlCount++;
            echo "[{$ts}] Dispatched scheduled newsletter: {$subject} ({$queued} subscribers)\n";
        }
    } catch (\Throwable $e) {
        echo "[{$ts}] Newsletter schedule error: {$e->getMessage()}\n";
        error_log('schedule.php newsletter: ' . $e->getMessage());
    }

    if ($run) {
        try {
            $summary = "Published {$count} article(s)";
            if ($nlCount > 0) $summary .= ", dispatched {$nlCount} newsletter(s)";
            \App\Models\CronRun::finish($run['id'], $count + $nlCount, $summary);
        } catch (\Throwable $e) {}
    }
} catch (Throwable $e) {
    echo "[{$ts}] ERROR: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n";
    error_log("schedule.php: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (isset($run) && $run) {
        try {
            \App\Models\CronRun::fail($run['id'], $e->getMessage());
        } catch (\Throwable $e2) {}
    }
}