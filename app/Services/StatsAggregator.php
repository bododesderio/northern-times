<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Dashboard Stats Aggregator — pre-computes daily metrics.
 * Called by cron daily at midnight.
 */
final class StatsAggregator
{
    public static function aggregateToday(): void
    {
        $pdo = DB::pdo();
        $date = date('Y-m-d');

        try {
            $pdo->prepare("
                INSERT INTO daily_stats (stat_date, total_views, unique_visitors, articles_published, articles_crawled, comments_count, subscribers_count, top_category)
                SELECT
                    :date,
                    COALESCE((SELECT COUNT(*) FROM article_views WHERE DATE(viewed_at) = :d1), 0),
                    COALESCE((SELECT COUNT(DISTINCT ip_address) FROM site_visitors WHERE visit_date = :d2), 0),
                    COALESCE((SELECT COUNT(*) FROM articles WHERE DATE(published_at) = :d3 AND status = 'published'), 0),
                    COALESCE((SELECT COUNT(*) FROM articles WHERE DATE(created_at) = :d4 AND is_crawled = TRUE), 0),
                    COALESCE((SELECT COUNT(*) FROM comments WHERE DATE(created_at) = :d5), 0),
                    COALESCE((SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'), 0),
                    (SELECT c.name FROM articles a JOIN categories c ON c.id = a.category_id
                     WHERE DATE(a.published_at) = :d6 AND a.status = 'published'
                     GROUP BY c.name ORDER BY COUNT(*) DESC LIMIT 1)
                ON CONFLICT (stat_date) DO UPDATE SET
                    total_views = EXCLUDED.total_views,
                    unique_visitors = EXCLUDED.unique_visitors,
                    articles_published = EXCLUDED.articles_published,
                    articles_crawled = EXCLUDED.articles_crawled,
                    comments_count = EXCLUDED.comments_count,
                    subscribers_count = EXCLUDED.subscribers_count,
                    top_category = EXCLUDED.top_category
            ")->execute([
                ':date' => $date,
                ':d1' => $date, ':d2' => $date, ':d3' => $date,
                ':d4' => $date, ':d5' => $date, ':d6' => $date,
            ]);
        } catch (\Throwable $e) {
            error_log('[StatsAggregator] Error: ' . $e->getMessage());
        }
    }

    public static function getRange(string $from, string $to): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
            SELECT * FROM daily_stats
            WHERE stat_date BETWEEN :from AND :to
            ORDER BY stat_date DESC
        ");
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    public static function getLast(int $days = 30): array
    {
        return self::getRange(
            date('Y-m-d', strtotime("-{$days} days")),
            date('Y-m-d')
        );
    }
}
