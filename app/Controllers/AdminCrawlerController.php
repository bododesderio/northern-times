<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\CrawlLog;
use App\Models\CrawlSource;
use App\Models\Setting;
use App\Services\CrawlerEngine;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminCrawlerController extends Controller
{
    /** Sources list + dashboard stats. */
    public function index(): Response
    {
        // Gracefully handle unmigrated database (0034_crawler_system.sql)
        try {
            CrawlSource::queryOne("SELECT 1 FROM crawl_sources LIMIT 1");
        } catch (\Throwable) {
            return $this->render('admin/crawler/index', [
                'sources'        => [],
                'stats'          => ['total_sources' => 0, 'active_sources' => 0, 'total_articles' => 0, 'last_crawl' => null],
                'today'          => ['crawls_today' => 0, 'published_today' => 0, 'errors_today' => 0],
                'recentLogs'     => [],
                'crawlerEnabled' => false,
                'csrf'           => Csrf::token(),
                'flash_success'  => Flash::get('success'),
                'flash_error'    => 'Crawler tables not found. Run migration: docker exec -it northern_times_app php database/migrate.php',
            ]);
        }

        $sources = CrawlSource::adminList();
        $stats   = CrawlSource::dashboardStats();
        $today   = CrawlLog::todayStats();
        $logs    = CrawlLog::recent(10);

        return $this->render('admin/crawler/index', [
            'sources'       => $sources,
            'stats'         => $stats,
            'today'         => $today,
            'recentLogs'    => $logs,
            'crawlerEnabled'=> Setting::get('crawler_enabled', 'false') === 'true',
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    /** Create source form. */
    public function create(): Response
    {
        return $this->render('admin/crawler/source_form', [
            'mode'       => 'create',
            'source'     => [],
            'categories' => Category::dropdown(),
            'csrf'       => Csrf::token(),
        ]);
    }

    /** Store new source. */
    public function store(): Response
    {
        $req = Request::createFromGlobals();
        $data = $this->extractSourceData($req);

        if ($data['name'] === '' || $data['feed_url'] === '') {
            Flash::set('error', 'Name and Feed URL are required.');
            return $this->redirect('/admin/crawler/create');
        }

        CrawlSource::store($data);
        Flash::set('success', 'Source "' . $data['name'] . '" created.');
        return $this->redirect('/admin/crawler');
    }

    /** Edit source form. */
    public function edit(string $id): Response
    {
        $source = CrawlSource::find($id);
        if (!$source) {
            Flash::set('error', 'Source not found.');
            return $this->redirect('/admin/crawler');
        }

        return $this->render('admin/crawler/source_form', [
            'mode'       => 'edit',
            'source'     => $source,
            'categories' => Category::dropdown(),
            'csrf'       => Csrf::token(),
        ]);
    }

    /** Update source. */
    public function update(string $id): Response
    {
        $req  = Request::createFromGlobals();
        $data = $this->extractSourceData($req);

        if ($data['name'] === '' || $data['feed_url'] === '') {
            Flash::set('error', 'Name and Feed URL are required.');
            return $this->redirect('/admin/crawler/' . $id . '/edit');
        }

        CrawlSource::updateSource($id, $data);
        Flash::set('success', 'Source updated.');
        return $this->redirect('/admin/crawler');
    }

    /** Delete source (and optionally its articles). */
    public function delete(string $id): Response
    {
        $source = CrawlSource::find($id);
        if (!$source) {
            Flash::set('error', 'Source not found.');
            return $this->redirect('/admin/crawler');
        }

        $req = Request::createFromGlobals();
        $deleteArticles = $req->request->get('delete_articles') === '1';

        if ($deleteArticles) {
            CrawlSource::execute(
                "DELETE FROM articles WHERE crawl_source_id = :id",
                [':id' => $id]
            );
        } else {
            // Unlink articles from source
            CrawlSource::execute(
                "UPDATE articles SET crawl_source_id = NULL WHERE crawl_source_id = :id",
                [':id' => $id]
            );
        }

        CrawlSource::delete($id);
        Flash::set('success', 'Source "' . $source['name'] . '" deleted.');
        return $this->redirect('/admin/crawler');
    }

    /** Toggle active/inactive. */
    public function toggle(string $id): Response
    {
        $newState = CrawlSource::toggleActive($id);
        Flash::set('success', 'Source ' . ($newState ? 'activated' : 'deactivated') . '.');
        return $this->redirect('/admin/crawler');
    }

    /** Manual crawl trigger (single source). */
    public function crawlNow(string $id): Response
    {
        $source = CrawlSource::find($id);
        if (!$source) {
            Flash::set('error', 'Source not found.');
            return $this->redirect('/admin/crawler');
        }

        try {
            [$found, $new, $dupes] = CrawlerEngine::crawlSource($source);
            Flash::set('success', "Crawled \"{$source['name']}\": {$found} found, {$new} new, {$dupes} duplicates.");
        } catch (\Throwable $e) {
            Flash::set('error', "Crawl failed: " . $e->getMessage());
        }

        return $this->redirect('/admin/crawler');
    }

    /** Manual crawl all sources (forced, ignores interval). */
    public function crawlAll(): Response
    {
        try {
            \App\Models\CrawlSource::execute(
                "UPDATE crawl_sources SET last_crawled_at = NULL WHERE is_active = TRUE"
            );
            $results = CrawlerEngine::crawlAll();
            if (isset($results['skipped'])) {
                Flash::set('error', 'Crawler is disabled. Enable it in settings.');
            } else {
                $total = array_sum(array_column($results, 'new'));
                Flash::set('success', count($results) . " sources crawled, {$total} new articles.");
            }
        } catch (\Throwable $e) {
            Flash::set('error', "Crawl failed: " . $e->getMessage());
        }

        return $this->redirect('/admin/crawler');
    }

    /** Visual crawler runner page. */
    public function visualRunner(): Response
    {
        $sources = CrawlSource::adminList();
        return $this->render('admin/crawler/visual_runner', [
            'sources' => $sources,
            'csrf'    => Csrf::token(),
        ]);
    }

    /** AJAX: Crawl a single source, return JSON result. */
    public function crawlSourceApi(string $id): Response
    {
        $source = CrawlSource::find($id);
        if (!$source) {
            return new Response(json_encode(['error' => 'Source not found']), 404, ['Content-Type' => 'application/json']);
        }

        $start = microtime(true);
        try {
            [$found, $new, $dupes] = CrawlerEngine::crawlSource($source);
            $elapsed = round(microtime(true) - $start, 2);

            return new Response(json_encode([
                'status'  => 'ok',
                'source'  => $source['name'],
                'found'   => $found,
                'new'     => $new,
                'dupes'   => $dupes,
                'time'    => $elapsed,
            ]), 200, ['Content-Type' => 'application/json']);

        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $start, 2);
            return new Response(json_encode([
                'status' => 'error',
                'source' => $source['name'],
                'error'  => $e->getMessage(),
                'time'   => $elapsed,
            ]), 200, ['Content-Type' => 'application/json']);
        }
    }

    /** AJAX: Get live source stats for dashboard. */
    public function crawlStatsApi(): Response
    {
        $stats = CrawlSource::dashboardStats();
        $today = CrawlLog::todayStats();
        return new Response(json_encode([
            'stats' => $stats,
            'today' => $today,
        ]), 200, ['Content-Type' => 'application/json']);
    }

    /** Crawl logs page. */
    public function logs(): Response
    {
        try {
            CrawlLog::queryOne("SELECT 1 FROM crawl_logs LIMIT 1");
        } catch (\Throwable) {
            Flash::set('error', 'Crawler tables not found. Run the migration first.');
            return $this->redirect('/admin/crawler');
        }

        $req = Request::createFromGlobals();
        $sourceId = $req->query->get('source') ?: null;

        $logs    = CrawlLog::recent(100, $sourceId);
        $sources = CrawlSource::all();

        return $this->render('admin/crawler/logs', [
            'logs'     => $logs,
            'sources'  => $sources,
            'sourceId' => $sourceId,
        ]);
    }

    /** Crawler settings page. */
    public function settings(): Response
    {
        return $this->render('admin/crawler/settings', [
            'settings' => [
                'crawler_enabled'       => Setting::get('crawler_enabled', 'false'),
                'crawler_interval'      => Setting::get('crawler_interval', '30'),
                'crawler_auto_publish'  => Setting::get('crawler_auto_publish', 'true'),
                'crawler_max_age_hours' => Setting::get('crawler_max_age_hours', '72'),
                'crawler_default_author'=> Setting::get('crawler_default_author', ''),
            ],
            'users' => \App\Models\User::query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username"),
            'csrf'  => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    /** Save crawler settings. */
    public function saveSettings(): Response
    {
        $req = Request::createFromGlobals();

        $keys = ['crawler_enabled', 'crawler_interval', 'crawler_auto_publish', 'crawler_max_age_hours', 'crawler_default_author'];
        foreach ($keys as $key) {
            $val = trim((string)$req->request->get($key, ''));
            if ($key === 'crawler_enabled' || $key === 'crawler_auto_publish') {
                $val = $req->request->get($key) ? 'true' : 'false';
            }
            Setting::set($key, $val, 'crawler');
        }

        Flash::set('success', 'Crawler settings saved.');
        return $this->redirect('/admin/crawler/settings');
    }

    /** Bulk archive all articles from a source. */
    public function archiveArticles(string $id): Response
    {
        $source = CrawlSource::find($id);
        if (!$source) {
            Flash::set('error', 'Source not found.');
            return $this->redirect('/admin/crawler');
        }

        $count = CrawlSource::execute(
            "UPDATE articles SET status = 'archived' WHERE crawl_source_id = :id AND status = 'published'",
            [':id' => $id]
        );

        Flash::set('success', "Archived {$count} articles from \"{$source['name']}\".");
        return $this->redirect('/admin/crawler');
    }

    /** AJAX: test a feed URL and return item count. */
    public function testFeed(): Response
    {
        $url = trim(Request::createFromGlobals()->query->get('url', ''));
        if ($url === '') {
            return $this->json(['ok' => false, 'error' => 'No URL provided']);
        }

        try {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'header'  => 'User-Agent: NorthernTimesCrawler/1.0',
                    'timeout' => 10,
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);

            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                return $this->json(['ok' => false, 'error' => 'Could not fetch URL']);
            }

            $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($body);
            libxml_clear_errors();

            if (!$xml) {
                return $this->json(['ok' => false, 'error' => 'Not valid XML']);
            }

            // Detect type and count items
            if (isset($xml->channel->item)) {
                return $this->json(['ok' => true, 'type' => 'RSS 2.0', 'items' => count($xml->channel->item)]);
            }
            if (isset($xml->entry)) {
                return $this->json(['ok' => true, 'type' => 'Atom', 'items' => count($xml->entry)]);
            }
            if (isset($xml->item)) {
                return $this->json(['ok' => true, 'type' => 'RSS 1.0/RDF', 'items' => count($xml->item)]);
            }

            return $this->json(['ok' => false, 'error' => 'No feed items found in XML']);

        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ── Private ─────────────────────────────────────────────────

    private function extractSourceData(Request $req): array
    {
        // Parse category map from textarea (format: keyword=category_id per line)
        $mapRaw  = trim((string)$req->request->get('category_map', ''));
        $catMap  = [];
        if ($mapRaw !== '') {
            foreach (explode("\n", $mapRaw) as $line) {
                $parts = explode('=', trim($line), 2);
                if (count($parts) === 2) {
                    $catMap[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
            }
        }

        return [
            'name'                => trim((string)$req->request->get('name', '')),
            'feed_url'            => trim((string)$req->request->get('feed_url', '')),
            'website_url'         => trim((string)$req->request->get('website_url', '')) ?: null,
            'logo_url'            => trim((string)$req->request->get('logo_url', '')) ?: null,
            'source_type'         => (string)$req->request->get('source_type', 'rss'),
            'is_active'           => (bool)$req->request->get('is_active'),
            'crawl_interval'      => (int)$req->request->get('crawl_interval', 30),
            'default_category_id' => trim((string)$req->request->get('default_category_id', '')) ?: null,
            'category_map'        => $catMap,
            'keyword_include'     => trim((string)$req->request->get('keyword_include', '')) ?: null,
            'keyword_exclude'     => trim((string)$req->request->get('keyword_exclude', '')) ?: null,
            'max_articles'        => max(1, (int)$req->request->get('max_articles', 20)),
            'strip_selectors'     => trim((string)$req->request->get('strip_selectors', '')) ?: null,
            'attribution_text'    => trim((string)$req->request->get('attribution_text', '')) ?: null,
            'nofollow'            => (bool)$req->request->get('nofollow'),
            'download_images'     => (bool)$req->request->get('download_images'),
            'full_page_scrape'    => (bool)$req->request->get('full_page_scrape'),
            'content_selector'    => trim((string)$req->request->get('content_selector', '')) ?: null,
        ];
    }
}