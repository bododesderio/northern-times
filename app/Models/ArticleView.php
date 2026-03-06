<?php
declare(strict_types=1);

namespace App\Models;

/**
 * ArticleView model — article_views table.
 *
 * Granular per-view logging for analytics dashboards and time-series charts.
 * The articles.views column remains the authoritative total counter;
 * this table adds when/who detail for charts.
 */
final class ArticleView extends BaseModel
{
    protected static string $table   = 'article_views';
    protected static string $orderBy = 'viewed_at DESC';

    /**
     * Record a view for an article.
     * Also increments the articles.views counter.
     */
    public static function record(string $articleId, ?string $ip = null, ?string $referer = null): void
    {
        // Insert into granular log
        self::execute(
            "INSERT INTO article_views (article_id, ip_address, referer, viewed_at) VALUES (:aid, :ip, :ref, NOW())",
            [
                ':aid' => $articleId,
                ':ip'  => $ip,
                ':ref' => $referer,
            ]
        );

        // Increment the counter column
        self::execute(
            "UPDATE articles SET views = views + 1 WHERE id = :id",
            [':id' => $articleId]
        );
    }

    /**
     * Check if this IP already viewed this article in the last N minutes.
     * Used for session-based debounce.
     */
    public static function hasRecentView(string $articleId, string $ip, int $minutes = 30): bool
    {
        $stmt = self::pdo()->prepare(
            "SELECT 1 FROM article_views
             WHERE article_id = :aid AND ip_address = :ip
               AND viewed_at > NOW() - CAST(:interval AS INTERVAL)
             LIMIT 1"
        );
        $stmt->execute([':aid' => $articleId, ':ip' => $ip, ':interval' => "{$minutes} minutes"]);
        return $stmt->fetch() !== false;
    }

    /**
     * Daily view counts for the last N days (for dashboard charts).
     */
    public static function dailyCounts(int $days = 14): array
    {
        $interval = "{$days} days";
        return self::query(
            "SELECT d::date AS view_date, COALESCE(av.cnt, 0) AS views
             FROM generate_series(CURRENT_DATE - CAST(:int1 AS INTERVAL), CURRENT_DATE, '1 day') d
             LEFT JOIN (
                 SELECT viewed_at::date AS v_day, COUNT(*) AS cnt
                 FROM article_views
                 WHERE viewed_at >= NOW() - CAST(:int2 AS INTERVAL)
                 GROUP BY viewed_at::date
             ) av ON av.v_day = d::date
             ORDER BY d",
            [':int1' => $interval, ':int2' => $interval]
        );
    }

    /**
     * Top articles by views in the last N days.
     */
    public static function topArticles(int $days = 7, int $limit = 10): array
    {
        $stmt = self::pdo()->prepare(
            "SELECT a.title, a.slug, COUNT(*) AS recent_views
             FROM article_views av
             JOIN articles a ON a.id = av.article_id
             WHERE av.viewed_at >= NOW() - CAST(:interval AS INTERVAL)
             GROUP BY a.id, a.title, a.slug
             ORDER BY recent_views DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':interval', "{$days} days");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}