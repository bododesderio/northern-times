<?php
/**
 * Weekly Digest — sends top articles to active subscribers.
 * Runs every Sunday at 8am via crontab.
 */
declare(strict_types=1);

set_time_limit(0);

$lockFile = sys_get_temp_dir() . '/nt_weekly_digest.lock';
$fp = fopen($lockFile, 'w');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo "[digest] Already running.\n";
    exit(0);
}

require_once __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}
require_once __DIR__ . '/../app/Support/helpers.php';

use App\Services\DB;
use App\Services\Cache;

// Redis distributed lock for multi-container deployments
try {
    $redis = Cache::redis();
    if ($redis && !$redis->set('lock:weekly_digest', getmypid(), ['NX', 'EX' => 7200])) {
        echo "[digest] Redis lock held by another instance.\n";
        exit(0);
    }
} catch (\Throwable) {}

echo "[digest] " . date('Y-m-d H:i:s') . " Starting weekly digest...\n";

$siteTitle = function_exists('site_name') ? site_name() : ($_ENV['APP_NAME'] ?? 'News');
$siteUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');

// Get top articles from past 7 days
$pdo = DB::pdo();
$articles = $pdo->query("
    SELECT a.title, a.slug, a.excerpt, a.featured_image, a.published_at,
           a.views, c.name AS category
    FROM articles a
    JOIN categories c ON c.id = a.category_id
    WHERE a.status = 'published'
      AND a.published_at >= NOW() - INTERVAL '7 days'
      AND a.deleted_at IS NULL
    ORDER BY a.views DESC, a.published_at DESC
    LIMIT 10
")->fetchAll();

if (empty($articles)) {
    echo "[digest] No articles from past week. Skipping.\n";
    flock($fp, LOCK_UN); fclose($fp);
    exit(0);
}

// Build HTML email
$accent = function_exists('get_site_setting') ? get_site_setting('theme_accent', '#cc0000') : '#cc0000';
$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>';
$html .= '<body style="margin:0;padding:0;background:#f4f4f4;font-family:Georgia,serif">';
$html .= '<div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0">';

// Header
$html .= '<div style="background:#1a1a1a;color:#fff;padding:24px;text-align:center">';
$html .= '<h1 style="margin:0;font-size:24px">' . htmlspecialchars($siteTitle) . '</h1>';
$html .= '<p style="margin:8px 0 0;opacity:.7;font-size:14px">Weekly Digest — ' . date('F j, Y') . '</p>';
$html .= '</div>';

// Intro
$html .= '<div style="padding:20px 24px;font-size:15px;color:#333;line-height:1.6">';
$html .= '<p>Here are the top stories from this week:</p>';
$html .= '</div>';

// Articles
foreach ($articles as $i => $a) {
    $url = $siteUrl . '/article/' . $a['slug'];
    $img = $a['featured_image'] ? htmlspecialchars($a['featured_image']) : '';
    $border = $i > 0 ? 'border-top:1px solid #eee;' : '';

    $html .= '<div style="padding:16px 24px;' . $border . '">';
    if ($img) {
        $html .= '<a href="' . htmlspecialchars($url) . '"><img src="' . $img . '" alt="" style="width:100%;height:auto;border-radius:6px;margin-bottom:10px" /></a>';
    }
    $html .= '<div style="font-size:11px;color:' . $accent . ';text-transform:uppercase;font-weight:700;letter-spacing:.05em">' . htmlspecialchars($a['category']) . '</div>';
    $html .= '<h2 style="margin:4px 0 6px;font-size:18px"><a href="' . htmlspecialchars($url) . '" style="color:#1a1a1a;text-decoration:none">' . htmlspecialchars($a['title']) . '</a></h2>';
    $html .= '<p style="margin:0;font-size:14px;color:#555;line-height:1.5">' . htmlspecialchars(mb_substr($a['excerpt'] ?? '', 0, 150)) . '</p>';
    $html .= '<a href="' . htmlspecialchars($url) . '" style="display:inline-block;margin-top:8px;color:' . $accent . ';font-size:13px;font-weight:600;text-decoration:none">Read more &rarr;</a>';
    $html .= '</div>';
}

// Footer
$html .= '<div style="background:#f8f8f8;padding:16px 24px;font-size:12px;color:#888;text-align:center;border-top:1px solid #eee">';
$html .= '<p>You are receiving this because you subscribed to ' . htmlspecialchars($siteTitle) . '.</p>';
$html .= '<p><a href="{{unsubscribe_url}}" style="color:' . $accent . '">Unsubscribe</a></p>';
$html .= '</div></div></body></html>';

// Queue emails to active subscribers
$subscribers = $pdo->query("SELECT id, email, name, unsub_token FROM newsletter_subscribers WHERE status = 'active'")->fetchAll();

$queued = 0;
$subject = "Weekly Digest — " . $siteTitle . " — " . date('M j, Y');

foreach ($subscribers as $sub) {
    $unsubUrl = $siteUrl . '/unsubscribe?token=' . urlencode($sub['unsub_token'] ?? '');
    $personalHtml = str_replace('{{unsubscribe_url}}', htmlspecialchars($unsubUrl), $html);

    try {
        $pdo->prepare("
            INSERT INTO email_queue (to_email, to_name, subject, body_html, status)
            VALUES (:email, :name, :subject, :body, 'pending')
        ")->execute([
            ':email' => $sub['email'],
            ':name' => $sub['name'] ?? '',
            ':subject' => $subject,
            ':body' => $personalHtml,
        ]);
        $queued++;
    } catch (\Throwable $e) {
        echo "[digest] Failed to queue for {$sub['email']}: {$e->getMessage()}\n";
    }
}

echo "[digest] Queued {$queued} digest emails for " . count($subscribers) . " subscribers.\n";

flock($fp, LOCK_UN); fclose($fp);
echo "[digest] Done.\n";
