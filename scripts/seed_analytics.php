<?php
/**
 * seed_analytics.php — Populate site_visitors + article_views with realistic data.
 *
 * Fixes the "0 visitors / 1M views" mismatch by back-filling analytics tables
 * to match the article seeder's view counts.
 *
 * Usage:
 *   php scripts/seed_analytics.php          # Insert only (skip conflicts)
 *   php scripts/seed_analytics.php --fresh  # Truncate first, then seed
 *   php scripts/seed_analytics.php --check  # Show current counts only
 *
 * Run: docker exec -it northern_times_app php scripts/seed_analytics.php --fresh
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\DB;

$pdo = DB::pdo();
echo "═══ Northern Times Analytics Seeder ═══\n\n";

// ── Parse flags ──────────────────────────────────────────────
$fresh = in_array('--fresh', $argv ?? [], true);
$check = in_array('--check', $argv ?? [], true);

// ── Pre-flight: verify required columns exist ────────────────
echo "Pre-flight checks...\n";

try {
    $cols = $pdo->query("
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'site_visitors'
        ORDER BY ordinal_position
    ")->fetchAll(\PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    echo "  ✘ Cannot read site_visitors schema: {$e->getMessage()}\n";
    echo "  → Run migrations first: php database/migrate.php\n";
    exit(1);
}

$required = ['latitude', 'longitude', 'city', 'country', 'country_code', 'region'];
$missing  = array_diff($required, $cols);

if (!empty($missing)) {
    echo "  ✘ site_visitors is missing columns: " . implode(', ', $missing) . "\n";
    echo "  → These are added by migration 0042_visitor_coordinates.sql\n";
    echo "  → Run migrations first: php database/migrate.php\n";
    exit(1);
}
echo "  ✔ All required columns present\n";

// ── Check mode ───────────────────────────────────────────────
if ($check) {
    $vCount  = (int)$pdo->query("SELECT COUNT(*) FROM site_visitors")->fetchColumn();
    $avCount = (int)$pdo->query("SELECT COUNT(*) FROM article_views")->fetchColumn();
    $todayV  = (int)$pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date = CURRENT_DATE")->fetchColumn();
    $artCount = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
    echo "\n═══ Current State ═══\n";
    echo "  site_visitors:     {$vCount} rows\n";
    echo "  article_views:     {$avCount} rows\n";
    echo "  visitors today:    {$todayV}\n";
    echo "  published articles: {$artCount}\n";
    exit(0);
}

// ── Fresh mode: truncate before seeding ──────────────────────
if ($fresh) {
    echo "\n⚠ FRESH MODE: Clearing existing analytics data...\n";
    $pdo->exec("TRUNCATE TABLE article_views RESTART IDENTITY");
    $pdo->exec("TRUNCATE TABLE site_visitors RESTART IDENTITY CASCADE");
    echo "  ✔ Truncated article_views and site_visitors\n\n";
}

// ── Config ────────────────────────────────────────────────────
$DAYS_BACK             = 90;
$VISITORS_PER_DAY_BASE = 35;  // baseline unique visitors/day
$VISITORS_GROWTH       = 1.015; // 1.5% daily growth toward present
$WEEKEND_FACTOR        = 0.6;   // weekends get 60% traffic

// East-Africa-heavy city distribution (matches the reader heatmap spec)
$cities = [
    ['Kampala',       'Uganda',         'UG', 'Central Region',    0.3476,  32.5825, 0.327],
    ['Gulu',          'Uganda',         'UG', 'Northern Region',   2.7747,  32.2990, 0.144],
    ['Lira',          'Uganda',         'UG', 'Northern Region',   2.2499,  32.5339, 0.062],
    ['Arua',          'Uganda',         'UG', 'West Nile',         3.0209,  30.9110, 0.038],
    ['Kitgum',        'Uganda',         'UG', 'Northern Region',   3.2884,  32.8786, 0.025],
    ['Soroti',        'Uganda',         'UG', 'Eastern Region',    1.7147,  33.6112, 0.018],
    ['Nairobi',       'Kenya',          'KE', 'Nairobi County',   -1.2864,  36.8172, 0.078],
    ['Dar es Salaam', 'Tanzania',       'TZ', 'Dar es Salaam',    -6.7924,  39.2083, 0.042],
    ['Kigali',        'Rwanda',         'RW', 'Kigali',           -1.9403,  29.8739, 0.032],
    ['Juba',          'South Sudan',    'SS', 'Central Equatoria',  4.8594, 31.5713, 0.028],
    ['London',        'United Kingdom', 'GB', 'England',           51.5074, -0.1278, 0.045],
    ['New York',      'United States',  'US', 'New York',          40.7128,-74.0060, 0.022],
    ['Lagos',         'Nigeria',        'NG', 'Lagos',              6.5244,  3.3792, 0.018],
    ['Johannesburg',  'South Africa',   'ZA', 'Gauteng',          -26.2041, 28.0473, 0.012],
    ['Dubai',         'UAE',            'AE', 'Dubai',             25.2048, 55.2708, 0.009],
    ['Mbarara',       'Uganda',         'UG', 'Western Region',   -0.6072, 30.6545, 0.015],
    ['Jinja',         'Uganda',         'UG', 'Eastern Region',    0.4244, 33.2041, 0.012],
    ['Mbale',         'Uganda',         'UG', 'Eastern Region',    1.0647, 34.1747, 0.010],
    ['Addis Ababa',   'Ethiopia',       'ET', 'Addis Ababa',       9.0192, 38.7525, 0.008],
    ['Toronto',       'Canada',         'CA', 'Ontario',           43.6532,-79.3832, 0.006],
];

// ── Step 1: Seed site_visitors ────────────────────────────────
echo "Seeding site_visitors ({$DAYS_BACK} days)...\n";

$insertVisitor = $pdo->prepare("
    INSERT INTO site_visitors (ip_address, user_agent, first_page, visit_date, created_at, latitude, longitude, city, country, country_code, region)
    VALUES (:ip, :ua, :fp, :vd, :ca, :lat, :lng, :city, :country, :cc, :region)
    ON CONFLICT (ip_address, visit_date) DO NOTHING
");

$agents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) Safari/604.1',
    'Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) Firefox/121.0',
    'Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0',
];

$pages = ['/', '/article/uganda-news', '/category/politics', '/category/health', '/article/gulu-hospital', '/category/business', '/article/east-africa-trade', '/search'];

$totalVisitors = 0;
$ipCounter = 1;

for ($d = $DAYS_BACK; $d >= 0; $d--) {
    $date = date('Y-m-d', strtotime("-{$d} days"));
    $dow  = (int)date('N', strtotime($date)); // 1=Mon, 7=Sun

    // Scale visitors: growth curve + weekend dip
    $scale      = pow($VISITORS_GROWTH, $DAYS_BACK - $d);
    $weekendMod = ($dow >= 6) ? $WEEKEND_FACTOR : 1.0;
    $jitter     = mt_rand(80, 120) / 100;
    $count      = (int)round($VISITORS_PER_DAY_BASE * $scale * $weekendMod * $jitter);

    for ($v = 0; $v < $count; $v++) {
        // Pick a city based on weighted distribution
        $rand = mt_rand(1, 1000) / 1000;
        $cumulative = 0;
        $city = $cities[0]; // fallback
        foreach ($cities as $c) {
            $cumulative += $c[6];
            if ($rand <= $cumulative) { $city = $c; break; }
        }

        // Small coordinate jitter for realistic dot distribution
        $latJitter = (mt_rand(-500, 500) / 10000);
        $lngJitter = (mt_rand(-500, 500) / 10000);

        $ip = '203.0.' . (($ipCounter >> 8) & 0xFF) . '.' . ($ipCounter & 0xFF);
        $ipCounter++;
        if ($ipCounter > 65535) $ipCounter = 1;

        $timestamp = $date . ' ' . sprintf('%02d:%02d:%02d', mt_rand(5, 23), mt_rand(0, 59), mt_rand(0, 59));

        try {
            $insertVisitor->execute([
                ':ip'      => $ip,
                ':ua'      => $agents[array_rand($agents)],
                ':fp'      => $pages[array_rand($pages)],
                ':vd'      => $date,
                ':ca'      => $timestamp,
                ':lat'     => round($city[4] + $latJitter, 6),
                ':lng'     => round($city[5] + $lngJitter, 6),
                ':city'    => $city[0],
                ':country' => $city[1],
                ':cc'      => $city[2],
                ':region'  => $city[3],
            ]);
            $totalVisitors++;
        } catch (\Throwable $e) {
            // skip conflicts silently
        }
    }

    if ($d % 10 === 0) echo "  Day -{$d}: {$count} visitors (total so far: {$totalVisitors})\n";
}
echo "  ✅ Seeded {$totalVisitors} visitor records\n\n";

// ── Step 2: Seed article_views ────────────────────────────────
echo "Seeding article_views (matching article view counts)...\n";

// Get published articles with their view counts
$articles = $pdo->query("
    SELECT id, views, published_at
    FROM articles
    WHERE status = 'published' AND views > 0
    ORDER BY published_at DESC
")->fetchAll(\PDO::FETCH_ASSOC);

if (empty($articles)) {
    echo "  ⚠ No published articles found — skipping article_views.\n";
    echo "  → Run the article seeder first: php scripts/seed_articles.php\n\n";
} else {
    $insertView = $pdo->prepare("
        INSERT INTO article_views (article_id, ip_address, referer, viewed_at)
        VALUES (:aid, :ip, :ref, :va)
    ");

    $totalViewRows = 0;
    $viewIpCounter = 1;
    $referers = [NULL, 'https://www.google.com', 'https://twitter.com', 'https://facebook.com', NULL, NULL, 'https://news.google.com'];

    foreach ($articles as $art) {
        // Don't seed ALL views — cap at 200 rows per article, last 30 days
        $sampleSize = min(200, (int)$art['views']);
        $pubDate    = $art['published_at'] ? strtotime($art['published_at']) : strtotime('-30 days');
        $earliest   = max($pubDate, strtotime('-30 days'));

        for ($v = 0; $v < $sampleSize; $v++) {
            $rangeEnd = max((int)$earliest, time());
            $viewTime = date('Y-m-d H:i:s', mt_rand((int)$earliest, $rangeEnd));
            $viewIp = '198.51.' . (($viewIpCounter >> 8) & 0xFF) . '.' . ($viewIpCounter & 0xFF);
            $viewIpCounter++;
            if ($viewIpCounter > 65535) $viewIpCounter = 1;

            try {
                $insertView->execute([
                    ':aid' => $art['id'],
                    ':ip'  => $viewIp,
                    ':ref' => $referers[array_rand($referers)],
                    ':va'  => $viewTime,
                ]);
                $totalViewRows++;
            } catch (\Throwable) {
                // skip
            }
        }
    }
    echo "  ✅ Seeded {$totalViewRows} article_view records\n\n";
}

// ── Step 3: Verify ────────────────────────────────────────────
$vCount  = (int)$pdo->query("SELECT COUNT(*) FROM site_visitors")->fetchColumn();
$avCount = (int)$pdo->query("SELECT COUNT(*) FROM article_views")->fetchColumn();
$todayV  = (int)$pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date = CURRENT_DATE")->fetchColumn();
$geoV    = (int)$pdo->query("SELECT COUNT(*) FROM site_visitors WHERE latitude IS NOT NULL")->fetchColumn();

echo "═══ Verification ═══\n";
echo "  site_visitors:     {$vCount} rows\n";
echo "  ├─ with geo data:  {$geoV} rows\n";
echo "  ├─ visitors today: {$todayV}\n";
echo "  article_views:     {$avCount} rows\n";
echo "  ✅ Analytics data seeded successfully\n";
