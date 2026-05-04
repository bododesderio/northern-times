<?php
/**
 * Database Seeder — ensures critical seed data exists.
 *
 * Runs after migrations on every container start. 100% idempotent —
 * uses ON CONFLICT DO NOTHING / INSERT ... WHERE NOT EXISTS so it
 * never duplicates data. Safe to run repeatedly.
 *
 * Seeded data:
 *   1. Super admin user
 *   2. All 15 categories
 *   3. Category navigation ordering
 *   4. All crawl sources (main + Ugandan + Dokolo Post)
 */

declare(strict_types=1);

use App\Services\DB;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$pdo = DB::pdo();

echo "Running seeder...\n";

// ══════════════════════════════════════════════════════════════
// 0. CRITICAL SETTINGS (crawler_enabled, etc.)
// ══════════════════════════════════════════════════════════════
$settings = [
    'crawler_enabled'      => 'true',
    'crawler_auto_publish' => 'true',
    'site_name'            => $_ENV['APP_NAME'] ?? 'The Northern Times',
    'site_tagline'         => 'Independent journalism from Northern Uganda and beyond.',
];
$settingStmt = $pdo->prepare("
    INSERT INTO site_settings (setting_key, setting_value)
    VALUES (:key, :val)
    ON CONFLICT (setting_key) DO NOTHING
");
foreach ($settings as $key => $val) {
    $settingStmt->execute([':key' => $key, ':val' => $val]);
}
echo "  Settings ensured.\n";

// ══════════════════════════════════════════════════════════════
// 1. SUPER ADMIN
// ══════════════════════════════════════════════════════════════
$pdo->exec("
  INSERT INTO users (username, email, password_hash, role)
  VALUES ('admin', 'admin@northerntimes.local', '\$2y\$10\$wI1DVdVgYS3pSyFmxkzxuOCtfDI7Mhani8CyAU7XZV9TdO32hI36i', 'super_admin')
  ON CONFLICT (email) DO NOTHING
");

// ══════════════════════════════════════════════════════════════
// 2. ALL 15 CATEGORIES
// ══════════════════════════════════════════════════════════════
$categories = [
    ['Top Stories',       'top-stories',      'Lead stories and editor picks', 1],
    ['Northern Uganda',   'northern-uganda',  'Regional reporting and features', 2],
    ['Politics',          'politics',         'Politics, government, elections', 3],
    ['National',          'national',         'National news and affairs', 4],
    ['Business',          'business',         'Business, economy, finance', 5],
    ['Sports',            'sports',           'Sports, athletics, football', 6],
    ['Health',            'health',           'Health, medicine, public health', 7],
    ['World',             'world',            'World news, international affairs', 8],
    ['Opinion',           'opinion',          'Opinion, editorial, commentary', 9],
    ['Technology',        'technology',       'Technology, software, AI, innovation', 10],
    ['Education',         'education',        'Education news and policy', 50],
    ['Entertainment',     'entertainment',    'Entertainment, culture, arts', 50],
    ['Environment',       'environment',      'Environment, climate, conservation', 50],
    ['Lifestyle',         'lifestyle',        'Lifestyle, wellness, living', 50],
    ['Crime & Security',  'crime-security',   'Crime, law enforcement, security', 50],
];

$catStmt = $pdo->prepare("
    INSERT INTO categories (id, name, slug, description, sort_order)
    VALUES (gen_random_uuid(), :name, :slug, :desc, :sort)
    ON CONFLICT (slug) DO NOTHING
");

foreach ($categories as [$name, $slug, $desc, $sort]) {
    $catStmt->execute([':name' => $name, ':slug' => $slug, ':desc' => $desc, ':sort' => $sort]);
}

// Navigation visibility
$pdo->exec("
    UPDATE categories SET show_in_nav = TRUE
    WHERE slug IN ('top-stories','northern-uganda','politics','national','business','sports','health','world','opinion','technology')
");
$pdo->exec("
    UPDATE categories SET show_in_nav = FALSE
    WHERE slug IN ('education','entertainment','environment','lifestyle','crime-security')
");
$pdo->exec("UPDATE categories SET show_in_sidebar = TRUE");

$catCount = (int)$pdo->query("SELECT count(*) FROM categories")->fetchColumn();
echo "  Categories: {$catCount}\n";

// ══════════════════════════════════════════════════════════════
// 3. CRAWL SOURCES
// ══════════════════════════════════════════════════════════════

// Helper: get category ID by slug
function catId(PDO $pdo, string $slug): ?string {
    $stmt = $pdo->prepare("SELECT id::text FROM categories WHERE slug = :s LIMIT 1");
    $stmt->execute([':s' => $slug]);
    return $stmt->fetchColumn() ?: null;
}

// Helper: build category map JSON
function catMap(PDO $pdo, array $map): string {
    $result = [];
    foreach ($map as $tag => $slug) {
        $id = catId($pdo, $slug);
        if ($id) $result[$tag] = $id;
    }
    return json_encode((object)$result);
}

// Check if sources already exist
$existingSources = (int)$pdo->query("SELECT count(*) FROM crawl_sources")->fetchColumn();
if ($existingSources > 0) {
    echo "  Sources already seeded ({$existingSources}), skipping.\n";
} else {
    echo "  Seeding crawl sources...\n";

    $defaultCat = catId($pdo, 'top-stories');

    $sources = [
        // ── Ugandan Sources ──────────────────────────────────
        [
            'name' => 'Daily Monitor',
            'feed_url' => 'https://www.monitor.co.ug/uganda/rss',
            'website_url' => 'https://www.monitor.co.ug',
            'region' => 'ugandan',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sport' => 'sports', 'sports' => 'sports', 'football' => 'sports', 'health' => 'health', 'technology' => 'technology', 'opinion' => 'opinion', 'northern' => 'northern-uganda', 'gulu' => 'northern-uganda', 'lira' => 'northern-uganda', 'acholi' => 'northern-uganda'],
            'keyword_include' => 'uganda,kampala,museveni,parliament,gulu,lira,northern,acholi,election,police,army,court',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting,obituary',
            'strip_selectors' => '.ads,.sidebar,#related-articles,.social-share,.newsletter-signup',
            'content_selector' => '',
            'attribution' => 'Source: Daily Monitor',
        ],
        [
            'name' => 'New Vision',
            'feed_url' => 'https://www.newvision.co.ug/rss',
            'website_url' => 'https://www.newvision.co.ug',
            'region' => 'ugandan',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sport' => 'sports', 'sports' => 'sports', 'health' => 'health', 'technology' => 'technology', 'opinion' => 'opinion', 'northern' => 'northern-uganda'],
            'keyword_include' => 'uganda,kampala,museveni,parliament,gulu,lira,northern',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting',
            'strip_selectors' => '.ads,.sidebar,.social-share,.newsletter-signup',
            'content_selector' => '',
            'attribution' => 'Source: New Vision',
        ],
        [
            'name' => 'Nile Post',
            'feed_url' => 'https://nilepost.co.ug/feed/',
            'website_url' => 'https://nilepost.co.ug',
            'region' => 'ugandan',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sport' => 'sports', 'sports' => 'sports', 'health' => 'health', 'opinion' => 'opinion', 'technology' => 'technology'],
            'keyword_include' => '',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting',
            'strip_selectors' => '.ads,.sidebar,.social-share,.sharedaddy,#related-articles,.wp-block-newspack-blocks-homepage-articles',
            'content_selector' => '.entry-content,.post-content',
            'attribution' => 'Source: Nile Post',
        ],
        [
            'name' => 'The Observer',
            'feed_url' => 'https://observer.ug/feed/',
            'website_url' => 'https://observer.ug',
            'region' => 'ugandan',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sport' => 'sports', 'sports' => 'sports', 'health' => 'health', 'opinion' => 'opinion', 'technology' => 'technology'],
            'keyword_include' => '',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting',
            'strip_selectors' => '.ads,.sidebar,.social-share,.sharedaddy,#related-articles',
            'content_selector' => '.entry-content,.post-content',
            'attribution' => 'Source: The Observer',
        ],
        [
            'name' => 'The Independent',
            'feed_url' => 'https://www.independent.co.ug/feed/',
            'website_url' => 'https://www.independent.co.ug',
            'region' => 'ugandan',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sport' => 'sports', 'sports' => 'sports', 'health' => 'health', 'opinion' => 'opinion', 'technology' => 'technology'],
            'keyword_include' => '',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting',
            'strip_selectors' => '.ads,.sidebar,.social-share,.sharedaddy,#related-articles',
            'content_selector' => '.entry-content,.post-content',
            'attribution' => 'Source: The Independent',
        ],
        [
            'name' => 'Uganda Radio Network',
            'feed_url' => 'https://ugandaradionetwork.net/feed/',
            'website_url' => 'https://ugandaradionetwork.net',
            'region' => 'ugandan',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sports' => 'sports', 'health' => 'health', 'northern' => 'northern-uganda', 'gulu' => 'northern-uganda', 'lira' => 'northern-uganda', 'acholi' => 'northern-uganda', 'lango' => 'northern-uganda'],
            'keyword_include' => 'uganda,northern,gulu,lira,acholi,lango,teso,karamoja',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting',
            'strip_selectors' => '.ads,.sidebar,.social-share',
            'content_selector' => '.entry-content,.post-content',
            'attribution' => 'Source: Uganda Radio Network',
        ],
        [
            'name' => 'Dokolo Post',
            'feed_url' => 'https://dokolopost.com/feed/',
            'website_url' => 'https://dokolopost.com',
            'region' => 'ugandan',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sports' => 'sports', 'health' => 'health', 'northern' => 'northern-uganda', 'gulu' => 'northern-uganda', 'lira' => 'northern-uganda'],
            'keyword_include' => 'dokolo,lira,lango,northern,uganda',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting',
            'strip_selectors' => '.ads,.sidebar,.social-share,.wp-block-newspack-blocks-homepage-articles',
            'content_selector' => '.entry-content,.post-content',
            'attribution' => 'Source: Dokolo Post',
        ],

        // ── East African Sources ─────────────────────────────
        [
            'name' => 'The East African',
            'feed_url' => 'https://www.theeastafrican.co.ke/tea/rss',
            'website_url' => 'https://www.theeastafrican.co.ke',
            'region' => 'east_african',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sports' => 'sports', 'health' => 'health', 'opinion' => 'opinion', 'science' => 'technology'],
            'keyword_include' => 'east africa,uganda,kenya,tanzania,rwanda,burundi',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting',
            'strip_selectors' => '.ads,.sidebar,.social-share',
            'content_selector' => '',
            'attribution' => 'Source: The East African',
        ],
        [
            'name' => 'Nation Africa',
            'feed_url' => 'https://nation.africa/rss',
            'website_url' => 'https://nation.africa',
            'region' => 'east_african',
            'category_map' => ['news' => 'top-stories', 'politics' => 'politics', 'business' => 'business', 'sports' => 'sports', 'health' => 'health', 'opinion' => 'opinion'],
            'keyword_include' => 'east africa,uganda,kenya,tanzania',
            'keyword_exclude' => 'sponsored,advertisement,casino,betting',
            'strip_selectors' => '.ads,.sidebar,.social-share',
            'content_selector' => '',
            'attribution' => 'Source: Nation Africa',
        ],

        // ── International Sources ────────────────────────────
        [
            'name' => 'BBC Africa',
            'feed_url' => 'http://feeds.bbci.co.uk/news/world/africa/rss.xml',
            'website_url' => 'https://www.bbc.com/news/world/africa',
            'region' => 'international',
            'category_map' => ['news' => 'world', 'africa' => 'world', 'politics' => 'politics', 'business' => 'business', 'health' => 'health', 'technology' => 'technology', 'sport' => 'sports'],
            'keyword_include' => 'africa,uganda,east africa',
            'keyword_exclude' => '',
            'strip_selectors' => '.ads,.sidebar,.social-share',
            'content_selector' => '',
            'attribution' => 'Source: BBC Africa',
        ],
        [
            'name' => 'Al Jazeera Africa',
            'feed_url' => 'https://www.aljazeera.com/xml/rss/all.xml',
            'website_url' => 'https://www.aljazeera.com',
            'region' => 'international',
            'category_map' => ['news' => 'world', 'politics' => 'politics', 'business' => 'business', 'opinion' => 'opinion'],
            'keyword_include' => 'africa,uganda,east africa,kenya,congo',
            'keyword_exclude' => 'sponsored',
            'strip_selectors' => '.ads,.sidebar,.social-share',
            'content_selector' => '',
            'attribution' => 'Source: Al Jazeera',
        ],
        [
            'name' => 'Reuters Africa',
            'feed_url' => 'https://www.reutersagency.com/feed/',
            'website_url' => 'https://www.reuters.com',
            'region' => 'international',
            'category_map' => ['news' => 'world', 'politics' => 'politics', 'business' => 'business', 'technology' => 'technology'],
            'keyword_include' => 'africa,uganda,east africa',
            'keyword_exclude' => 'sponsored',
            'strip_selectors' => '.ads,.sidebar',
            'content_selector' => '',
            'attribution' => 'Source: Reuters',
        ],
    ];

    $srcStmt = $pdo->prepare("
        INSERT INTO crawl_sources (
            name, feed_url, website_url, source_type, is_active,
            crawl_interval, default_category_id, region, category_map,
            keyword_include, keyword_exclude, max_articles, strip_selectors,
            content_selector, attribution_text, nofollow
        ) VALUES (
            :name, :feed, :site, 'rss', TRUE,
            30, :default_cat, :region, :catmap::jsonb,
            :kw_include, :kw_exclude, 20, :strip,
            :content, :attr, TRUE
        ) ON CONFLICT DO NOTHING
    ");

    foreach ($sources as $src) {
        $srcStmt->execute([
            ':name'        => $src['name'],
            ':feed'        => $src['feed_url'],
            ':site'        => $src['website_url'],
            ':default_cat' => $defaultCat,
            ':region'      => $src['region'],
            ':catmap'      => catMap($pdo, $src['category_map']),
            ':kw_include'  => $src['keyword_include'],
            ':kw_exclude'  => $src['keyword_exclude'],
            ':strip'       => $src['strip_selectors'],
            ':content'     => $src['content_selector'],
            ':attr'        => $src['attribution'],
        ]);
    }

    $srcCount = (int)$pdo->query("SELECT count(*) FROM crawl_sources WHERE is_active = true")->fetchColumn();
    echo "  Sources seeded: {$srcCount}\n";
}

echo "Seeder complete.\n";
