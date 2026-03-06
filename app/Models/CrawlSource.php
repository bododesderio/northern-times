<?php
declare(strict_types=1);

namespace App\Models;

final class CrawlSource extends BaseModel
{
    protected static string $table   = 'crawl_sources';
    protected static string $orderBy = 'name ASC';

    public const TYPE_RSS  = 'rss';
    public const TYPE_ATOM = 'atom';
    public const TYPE_HTML = 'html';

    public const SOURCE_TYPES = [self::TYPE_RSS, self::TYPE_ATOM, self::TYPE_HTML];

    // ── Queries ──────────────────────────────────────────────────

    /** All active sources due for a crawl. */
    public static function dueForCrawl(): array
    {
        return self::query(
            "SELECT * FROM crawl_sources
             WHERE is_active = TRUE
               AND (last_crawled_at IS NULL
                    OR last_crawled_at < NOW() - (crawl_interval || ' minutes')::INTERVAL)
             ORDER BY last_crawled_at ASC NULLS FIRST"
        );
    }

    /** All active sources (bypass interval check). */
    public static function allActive(): array
    {
        return self::query(
            "SELECT * FROM crawl_sources WHERE is_active = TRUE ORDER BY name ASC"
        );
    }

    /** All sources for admin listing. */
    public static function adminList(): array
    {
        return self::query(
            "SELECT cs.*,
                    c.name AS default_category_name,
                    (SELECT COUNT(*) FROM articles a WHERE a.crawl_source_id = cs.id) AS article_count
             FROM crawl_sources cs
             LEFT JOIN categories c ON c.id = cs.default_category_id
             ORDER BY cs.name ASC"
        );
    }

    /** Insert a new source. */
    public static function store(array $d): ?array
    {
        $sql = "INSERT INTO crawl_sources
            (name, feed_url, website_url, logo_url, source_type, is_active,
             crawl_interval, default_category_id, category_map,
             keyword_include, keyword_exclude, max_articles, strip_selectors,
             attribution_text, nofollow, download_images, full_page_scrape, content_selector)
            VALUES
            (:name, :feed_url, :website_url, :logo_url, :source_type, :is_active,
             :crawl_interval, :default_category_id, :category_map,
             :keyword_include, :keyword_exclude, :max_articles, :strip_selectors,
             :attribution_text, :nofollow, :download_images, :full_page_scrape, :content_selector)
            RETURNING *";

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute([
            ':name'                => $d['name'],
            ':feed_url'            => $d['feed_url'],
            ':website_url'         => $d['website_url'] ?? null,
            ':logo_url'            => $d['logo_url'] ?? null,
            ':source_type'         => $d['source_type'] ?? self::TYPE_RSS,
            ':is_active'           => ($d['is_active'] ?? true) ? 'true' : 'false',
            ':crawl_interval'      => (int)($d['crawl_interval'] ?? 30),
            ':default_category_id' => $d['default_category_id'] ?? null,
            ':category_map'        => json_encode($d['category_map'] ?? []),
            ':keyword_include'     => $d['keyword_include'] ?? null,
            ':keyword_exclude'     => $d['keyword_exclude'] ?? null,
            ':max_articles'        => (int)($d['max_articles'] ?? 20),
            ':strip_selectors'     => $d['strip_selectors'] ?? null,
            ':attribution_text'    => $d['attribution_text'] ?? null,
            ':nofollow'            => ($d['nofollow'] ?? true) ? 'true' : 'false',
            ':download_images'     => ($d['download_images'] ?? true) ? 'true' : 'false',
            ':full_page_scrape'    => ($d['full_page_scrape'] ?? false) ? 'true' : 'false',
            ':content_selector'    => $d['content_selector'] ?? null,
        ]);

        return $stmt->fetch() ?: null;
    }

    /** Update an existing source. */
    public static function updateSource(string $id, array $d): ?array
    {
        $sql = "UPDATE crawl_sources SET
            name = :name, feed_url = :feed_url, website_url = :website_url,
            logo_url = :logo_url, source_type = :source_type, is_active = :is_active,
            crawl_interval = :crawl_interval, default_category_id = :default_category_id,
            category_map = :category_map, keyword_include = :keyword_include,
            keyword_exclude = :keyword_exclude, max_articles = :max_articles,
            strip_selectors = :strip_selectors, attribution_text = :attribution_text,
            nofollow = :nofollow, download_images = :download_images,
            full_page_scrape = :full_page_scrape, content_selector = :content_selector,
            updated_at = NOW()
            WHERE id = :id RETURNING *";

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute([
            ':id'                  => $id,
            ':name'                => $d['name'],
            ':feed_url'            => $d['feed_url'],
            ':website_url'         => $d['website_url'] ?? null,
            ':logo_url'            => $d['logo_url'] ?? null,
            ':source_type'         => $d['source_type'] ?? self::TYPE_RSS,
            ':is_active'           => ($d['is_active'] ?? true) ? 'true' : 'false',
            ':crawl_interval'      => (int)($d['crawl_interval'] ?? 30),
            ':default_category_id' => $d['default_category_id'] ?? null,
            ':category_map'        => json_encode($d['category_map'] ?? []),
            ':keyword_include'     => $d['keyword_include'] ?? null,
            ':keyword_exclude'     => $d['keyword_exclude'] ?? null,
            ':max_articles'        => (int)($d['max_articles'] ?? 20),
            ':strip_selectors'     => $d['strip_selectors'] ?? null,
            ':attribution_text'    => $d['attribution_text'] ?? null,
            ':nofollow'            => ($d['nofollow'] ?? true) ? 'true' : 'false',
            ':download_images'     => ($d['download_images'] ?? true) ? 'true' : 'false',
            ':full_page_scrape'    => ($d['full_page_scrape'] ?? false) ? 'true' : 'false',
            ':content_selector'    => $d['content_selector'] ?? null,
        ]);

        return $stmt->fetch() ?: null;
    }

    /** Mark a source as just-crawled. */
    public static function markCrawled(string $id, bool $success, ?string $error = null): void
    {
        $sql = "UPDATE crawl_sources SET
            last_crawled_at = NOW(),
            " . ($success ? "last_success_at = NOW(), last_error = NULL" : "last_error = :error") . ",
            updated_at = NOW()
            WHERE id = :id";

        $params = [':id' => $id];
        if (!$success) $params[':error'] = $error;

        self::execute($sql, $params);
    }

    /** Increment stats counters. */
    public static function incrementStats(string $id, int $crawled, int $published, int $errors): void
    {
        self::execute(
            "UPDATE crawl_sources SET
                total_crawled   = total_crawled   + :crawled,
                total_published = total_published + :published,
                total_errors    = total_errors    + :errors
             WHERE id = :id",
            [':id' => $id, ':crawled' => $crawled, ':published' => $published, ':errors' => $errors]
        );
    }

    /** Toggle active status. */
    public static function toggleActive(string $id): bool
    {
        self::execute(
            "UPDATE crawl_sources SET is_active = NOT is_active, updated_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
        $row = self::find($id);
        return $row ? (bool)$row['is_active'] : false;
    }

    /** Dashboard stats. */
    public static function dashboardStats(): array
    {
        return self::queryOne(
            "SELECT
                COUNT(*) AS total_sources,
                COUNT(*) FILTER (WHERE is_active) AS active_sources,
                SUM(total_published) AS total_articles,
                MAX(last_crawled_at) AS last_crawl
             FROM crawl_sources"
        ) ?: ['total_sources' => 0, 'active_sources' => 0, 'total_articles' => 0, 'last_crawl' => null];
    }
}