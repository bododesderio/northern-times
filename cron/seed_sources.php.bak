<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$pdo = new PDO(
    'pgsql:host=' . $_ENV['DB_HOST'] . ';port=' . $_ENV['DB_PORT'] . ';dbname=' . $_ENV['DB_NAME'],
    $_ENV['DB_USER'], $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  THE NORTHERN TIMES — Full Reset & Seed (100)       ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

// ── FULL RESET ──────────────────────────────────────────────
$d = $pdo->exec("DELETE FROM articles WHERE source_hash IS NOT NULL");
echo "  🗑  Deleted {$d} crawled articles\n";
$pdo->exec("DELETE FROM crawl_logs");
echo "  🗑  Cleared crawl logs\n";
$pdo->exec("DELETE FROM crawl_sources");
echo "  🗑  Cleared all sources\n";
try { $pdo->exec("ALTER SEQUENCE crawl_sources_id_seq RESTART"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER SEQUENCE crawl_logs_id_seq RESTART"); } catch (\Throwable $e) {}
echo "\n  ✓ Full reset complete\n\n";

$defCat = $pdo->query("SELECT id FROM categories WHERE slug IN ('world','top-stories','news') LIMIT 1")->fetchColumn();
if (!$defCat) $defCat = $pdo->query("SELECT id FROM categories ORDER BY sort_order LIMIT 1")->fetchColumn();

$sources = [
    // ═════════════════ UGANDA (10) ═════════════════
    ['Dokolo Post',             'https://dokolopost.com/feed/',                                  'https://dokolopost.com',              'uganda'],
    ['Daily Monitor',           'https://www.monitor.co.ug/resource/rss/1625068',                'https://www.monitor.co.ug',           'uganda'],
    ['New Vision',              'https://www.newvision.co.ug/feed',                              'https://www.newvision.co.ug',         'uganda'],
    ['The Observer Uganda',     'https://observer.ug/feed',                                      'https://observer.ug',                 'uganda'],
    ['Nile Post',               'https://nilepost.co.ug/feed/',                                  'https://nilepost.co.ug',              'uganda'],
    ['Independent Uganda',      'https://www.independent.co.ug/feed/',                           'https://www.independent.co.ug',       'uganda'],
    ['Uganda Radio Network',    'https://ugandaradionetwork.net/feed/',                          'https://ugandaradionetwork.net',      'uganda'],
    ['Chimpreports',            'https://chimpreports.com/feed/',                                'https://chimpreports.com',            'uganda'],
    ['Red Pepper Uganda',       'https://redpepper.co.ug/feed/',                                 'https://redpepper.co.ug',             'uganda'],
    ['Eagle Online',            'https://eagle.co.ug/feed',                                      'https://eagle.co.ug',                 'uganda'],

    // ═════════════════ AFRICA (15) ═════════════════
    ['The East African',        'https://www.theeastafrican.co.ke/tea/rss',                      'https://www.theeastafrican.co.ke',    'africa'],
    ['Nation Africa',           'https://nation.africa/kenya/rss',                               'https://nation.africa',               'africa'],
    ['Citizen Digital Kenya',   'https://www.citizen.digital/feed',                              'https://www.citizen.digital',         'africa'],
    ['The Citizen Tanzania',    'https://www.thecitizen.co.tz/rss',                              'https://www.thecitizen.co.tz',        'africa'],
    ['News24 South Africa',    'https://feeds.news24.com/articles/news24/TopStories/rss',       'https://www.news24.com',              'africa'],
    ['TimesLIVE SA',           'https://www.timeslive.co.za/rss/',                              'https://www.timeslive.co.za',         'africa'],
    ['Punch Nigeria',          'https://punchng.com/feed/',                                     'https://punchng.com',                 'africa'],
    ['Premium Times Nigeria',  'https://www.premiumtimesng.com/feed',                           'https://www.premiumtimesng.com',      'africa'],
    ['Africa News',            'https://www.africanews.com/feed/',                              'https://www.africanews.com',          'africa'],
    ['The Star Kenya',         'https://www.the-star.co.ke/rss',                                'https://www.the-star.co.ke',          'africa'],
    ['Daily Maverick SA',      'https://www.dailymaverick.co.za/feed/',                         'https://www.dailymaverick.co.za',     'africa'],
    ['IOL South Africa',       'https://www.iol.co.za/cmlink/1.640',                            'https://www.iol.co.za',               'africa'],
    ['Vanguard Nigeria',       'https://www.vanguardngr.com/feed/',                             'https://www.vanguardngr.com',         'africa'],
    ['The Guardian Nigeria',   'https://guardian.ng/feed/',                                     'https://guardian.ng',                 'africa'],
    ['Sahara Reporters',       'http://saharareporters.com/feed',                               'https://saharareporters.com',         'africa'],

    // ═════════════════ UK & EUROPE (15) ═════════════════
    ['The Guardian',           'https://www.theguardian.com/world/rss',                         'https://www.theguardian.com',         'uk'],
    ['BBC News',               'https://feeds.bbci.co.uk/news/rss.xml',                         'https://www.bbc.com/news',            'uk'],
    ['The Independent UK',     'https://www.independent.co.uk/news/world/rss',                  'https://www.independent.co.uk',       'uk'],
    ['Sky News',               'https://feeds.skynews.com/feeds/rss/home.xml',                  'https://news.sky.com',                'uk'],
    ['The Scotsman',           'https://www.scotsman.com/rss',                                  'https://www.scotsman.com',            'uk'],
    ['The Irish Times',        'https://www.irishtimes.com/cmlink/news-1.1319192',              'https://www.irishtimes.com',          'uk'],
    ['RTE Ireland',            'https://www.rte.ie/news/rss/news-headlines.xml',                'https://www.rte.ie',                  'uk'],
    ['DW News',                'https://rss.dw.com/rdf/rss-en-all',                             'https://www.dw.com',                  'uk'],
    ['France 24',              'https://www.france24.com/en/rss',                                'https://www.france24.com/en',         'uk'],
    ['euronews',               'https://www.euronews.com/rss',                                  'https://www.euronews.com',            'uk'],
    ['The Local Europe',       'https://www.thelocal.com/feed/',                                'https://www.thelocal.com',            'uk'],
    ['EUobserver',             'https://euobserver.com/rss.xml',                                'https://euobserver.com',              'uk'],
    ['POLITICO Europe',        'https://www.politico.eu/feed/',                                 'https://www.politico.eu',             'uk'],
    ['Anadolu Agency',         'https://www.aa.com.tr/en/rss/default?cat=world',                'https://www.aa.com.tr',               'uk'],
    ['Swiss Info',             'https://www.swissinfo.ch/eng/rss/top-news',                     'https://www.swissinfo.ch',            'uk'],

    // ═════════════════ USA & CANADA (15) ═════════════════
    ['CNN',                    'http://rss.cnn.com/rss/edition.rss',                            'https://edition.cnn.com',             'usa'],
    ['NPR',                    'https://feeds.npr.org/1001/rss.xml',                            'https://www.npr.org',                 'usa'],
    ['New York Times',         'https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml',     'https://www.nytimes.com',             'usa'],
    ['NBC News',               'https://feeds.nbcnews.com/nbcnews/public/news',                'https://www.nbcnews.com',             'usa'],
    ['ABC News',               'https://abcnews.go.com/abcnews/topstories',                    'https://abcnews.go.com',              'usa'],
    ['CBS News',               'https://www.cbsnews.com/latest/rss/main',                       'https://www.cbsnews.com',             'usa'],
    ['Fox News',               'https://moxie.foxnews.com/google-publisher/latest.xml',         'https://www.foxnews.com',             'usa'],
    ['Washington Post',        'https://feeds.washingtonpost.com/rss/world',                    'https://www.washingtonpost.com',      'usa'],
    ['PBS NewsHour',           'https://www.pbs.org/newshour/feeds/rss/headlines',              'https://www.pbs.org/newshour',        'usa'],
    ['The Hill',               'https://thehill.com/feed/',                                     'https://thehill.com',                 'usa'],
    ['Politico US',            'https://www.politico.com/rss/politicopicks.xml',                'https://www.politico.com',            'usa'],
    ['CBC Canada',             'https://rss.cbc.ca/lineup/topstories.xml',                      'https://www.cbc.ca',                  'usa'],
    ['Global News Canada',     'https://globalnews.ca/feed/',                                   'https://globalnews.ca',               'usa'],
    ['Business Insider',       'https://www.businessinsider.com/rss',                           'https://www.businessinsider.com',     'usa'],
    ['USA Today',              'http://rss.usatoday.com/breakingnews',                          'https://www.usatoday.com',            'usa'],

    // ═════════════════ MIDDLE EAST (10) ═════════════════
    ['Al Jazeera',             'https://www.aljazeera.com/xml/rss/all.xml',                     'https://www.aljazeera.com',           'mideast'],
    ['Middle East Eye',        'https://www.middleeasteye.net/rss',                             'https://www.middleeasteye.net',       'mideast'],
    ['Arab News',              'https://www.arabnews.com/rss.xml',                              'https://www.arabnews.com',            'mideast'],
    ['Gulf News',              'https://gulfnews.com/rss',                                     'https://gulfnews.com',                'mideast'],
    ['The National UAE',       'https://www.thenationalnews.com/feed',                          'https://www.thenationalnews.com',     'mideast'],
    ['Jerusalem Post',         'https://www.jpost.com/rss/rssfeedsheadlines.aspx',              'https://www.jpost.com',               'mideast'],
    ['Times of Israel',        'https://www.timesofisrael.com/feed/',                           'https://www.timesofisrael.com',       'mideast'],
    ['Daily Sabah Turkey',     'https://www.dailysabah.com/rssFeed/front_page',                 'https://www.dailysabah.com',          'mideast'],
    ['Tehran Times',           'https://www.tehrantimes.com/rss',                               'https://www.tehrantimes.com',         'mideast'],
    ['Al-Monitor',             'https://www.al-monitor.com/rss',                                'https://www.al-monitor.com',          'mideast'],

    // ═════════════════ ASIA PACIFIC (15) ═════════════════
    ['South China Morning Post','https://www.scmp.com/rss/91/feed',                             'https://www.scmp.com',                'asia'],
    ['The Hindu',              'https://www.thehindu.com/feeder/default.rss',                   'https://www.thehindu.com',            'asia'],
    ['Times of India',         'https://timesofindia.indiatimes.com/rssfeedstopstories.cms',    'https://timesofindia.indiatimes.com', 'asia'],
    ['NDTV India',             'https://feeds.feedburner.com/ndtvnews-top-stories',             'https://www.ndtv.com',                'asia'],
    ['Sydney Morning Herald',  'https://www.smh.com.au/rss/feed.xml',                          'https://www.smh.com.au',              'asia'],
    ['ABC Australia',          'https://www.abc.net.au/news/feed/51120/rss.xml',                'https://www.abc.net.au/news',         'asia'],
    ['NHK World Japan',        'https://www3.nhk.or.jp/nhkworld/en/news/feeds/',                'https://www3.nhk.or.jp/nhkworld/',   'asia'],
    ['The Japan Times',        'https://www.japantimes.co.jp/feed/',                            'https://www.japantimes.co.jp',        'asia'],
    ['Channel News Asia',      'https://www.channelnewsasia.com/api/v1/rss-outbound-feed?_format=xml','https://www.channelnewsasia.com','asia'],
    ['Bangkok Post',           'https://www.bangkokpost.com/rss/data/topstories.xml',           'https://www.bangkokpost.com',         'asia'],
    ['Nikkei Asia',            'https://asia.nikkei.com/rss',                                   'https://asia.nikkei.com',             'asia'],
    ['New Zealand Herald',     'https://www.nzherald.co.nz/arc/outboundfeeds/rss/curated/78/?outputType=xml','https://www.nzherald.co.nz','asia'],
    ['Philippine Inquirer',    'https://newsinfo.inquirer.net/feed',                            'https://newsinfo.inquirer.net',       'asia'],
    ['The Korea Herald',       'http://www.koreaherald.com/common/rss_xml.php',                 'https://www.koreaherald.com',         'asia'],
    ['Straits Times',          'https://www.straitstimes.com/news/world/rss.xml',               'https://www.straitstimes.com',        'asia'],

    // ═════════════════ LATIN AMERICA (10) ═════════════════
    ['Buenos Aires Times',     'https://www.batimes.com.ar/feed',                               'https://www.batimes.com.ar',          'latam'],
    ['MercoPress',             'https://en.mercopress.com/rss',                                 'https://en.mercopress.com',           'latam'],
    ['Rio Times Brazil',       'https://riotimesonline.com/feed/',                              'https://riotimesonline.com',          'latam'],
    ['Mexico News Daily',      'https://mexiconewsdaily.com/feed/',                             'https://mexiconewsdaily.com',         'latam'],
    ['Colombia Reports',       'https://colombiareports.com/feed/',                             'https://colombiareports.com',         'latam'],
    ['Jamaica Observer',       'https://www.jamaicaobserver.com/feed/',                         'https://www.jamaicaobserver.com',     'latam'],
    ['Tico Times Costa Rica',  'https://ticotimes.net/feed',                                    'https://ticotimes.net',               'latam'],
    ['Santiago Times Chile',   'https://santiagotimes.cl/feed/',                                'https://santiagotimes.cl',            'latam'],
    ['Peru Reports',           'https://perureports.com/feed/',                                 'https://perureports.com',             'latam'],
    ['Trinidad Express',       'https://trinidadexpress.com/search/?f=rss',                     'https://trinidadexpress.com',         'latam'],

    // ═════════════════ GLOBAL WIRES (10) ═════════════════
    ['Reuters',                'https://www.reutersagency.com/feed/',                           'https://www.reuters.com',             'world'],
    ['AP News',                'https://rsshub.app/apnews/topics/apf-topnews',                  'https://apnews.com',                  'world'],
    ['The Conversation',       'https://theconversation.com/articles.atom',                     'https://theconversation.com',         'world'],
    ['Foreign Policy',         'https://foreignpolicy.com/feed/',                               'https://foreignpolicy.com',           'world'],
    ['The Diplomat',           'https://thediplomat.com/feed/',                                 'https://thediplomat.com',             'world'],
    ['Devex',                  'https://www.devex.com/news/rss',                                'https://www.devex.com',               'world'],
    ['Relief Web',             'https://reliefweb.int/headlines/rss.xml',                       'https://reliefweb.int',               'world'],
    ['The New Humanitarian',   'https://www.thenewhumanitarian.org/rss.xml',                    'https://www.thenewhumanitarian.org',  'world'],
    ['Global Voices',          'https://globalvoices.org/feed/',                                'https://globalvoices.org',            'world'],
    ['Rest of World',          'https://restofworld.org/feed/',                                 'https://restofworld.org',             'world'],
];

$sql = "INSERT INTO crawl_sources
    (name, feed_url, website_url, source_type, is_active,
     crawl_interval, default_category_id, category_map,
     keyword_include, keyword_exclude, max_articles, strip_selectors,
     attribution_text, nofollow, download_images, full_page_scrape)
    VALUES (:n, :f, :w, 'rss', true, :i, :c, '{}', NULL, NULL, :m, NULL, :a, true, true, true)";

$stmt = $pdo->prepare($sql);
$counts = [];

foreach ($sources as [$name, $feed, $web, $region]) {
    $interval = ($region === 'uganda') ? 15 : 30;
    $maxArt   = ($region === 'uganda') ? 30 : 20;

    $stmt->execute([':n' => $name, ':f' => $feed, ':w' => $web, ':i' => $interval, ':c' => $defCat, ':m' => $maxArt, ':a' => 'Source: ' . $name]);
    $counts[$region] = ($counts[$region] ?? 0) + 1;
    echo "  + [{$region}] {$name}\n";
}

$total = array_sum($counts);
echo "\n✅ Seeded {$total} sources:\n";
foreach ($counts as $r => $c) echo "   " . str_pad(ucfirst($r), 12) . ": {$c}\n";
echo "\nGo to /admin/crawler → ⚡ Crawl All Now\n";