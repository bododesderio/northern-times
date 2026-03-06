<?php
declare(strict_types=1);

namespace App\Models;

final class SocialMention extends BaseModel
{
    protected static string $table   = 'social_mentions';
    protected static string $orderBy = 'found_at DESC';

    public const POSITIVE = 'positive';
    public const NEUTRAL  = 'neutral';
    public const NEGATIVE = 'negative';

    public const PLATFORMS = ['twitter', 'facebook', 'reddit', 'google_news', 'web'];

    /** Recent mentions for dashboard. */
    public static function recent(int $limit = 50, ?string $platform = null, ?string $sentiment = null): array
    {
        $where  = [];
        $params = [];

        if ($platform) {
            $where[]  = 'platform = :platform';
            $params[':platform'] = $platform;
        }
        if ($sentiment) {
            $where[]  = 'sentiment = :sentiment';
            $params[':sentiment'] = $sentiment;
        }

        $sql = "SELECT * FROM social_mentions"
             . (!empty($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY found_at DESC LIMIT :lim";

        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Sentiment breakdown for dashboard. */
    public static function sentimentStats(int $days = 7): array
    {
        return self::query(
            "SELECT sentiment, COUNT(*) AS count
             FROM social_mentions
             WHERE found_at > NOW() - (:days || ' days')::INTERVAL
             GROUP BY sentiment
             ORDER BY count DESC",
            [':days' => $days]
        );
    }

    /** Platform breakdown. */
    public static function platformStats(int $days = 7): array
    {
        return self::query(
            "SELECT platform, COUNT(*) AS count,
                    COUNT(*) FILTER (WHERE sentiment = 'negative') AS negative_count
             FROM social_mentions
             WHERE found_at > NOW() - (:days || ' days')::INTERVAL
             GROUP BY platform
             ORDER BY count DESC",
            [':days' => $days]
        );
    }

    /** Daily trend (last N days). */
    public static function dailyTrend(int $days = 30): array
    {
        return self::query(
            "SELECT DATE(found_at) AS day,
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE sentiment = 'positive') AS positive,
                    COUNT(*) FILTER (WHERE sentiment = 'neutral') AS neutral,
                    COUNT(*) FILTER (WHERE sentiment = 'negative') AS negative
             FROM social_mentions
             WHERE found_at > NOW() - (:days || ' days')::INTERVAL
             GROUP BY DATE(found_at)
             ORDER BY day DESC",
            [':days' => $days]
        );
    }

    /** Unread count. */
    public static function unreadCount(): int
    {
        return (int)(self::queryColumn(
            "SELECT COUNT(*) FROM social_mentions WHERE is_read = FALSE"
        ) ?: 0);
    }

    /** Mark mentions as read. */
    public static function markAllRead(): void
    {
        self::execute("UPDATE social_mentions SET is_read = TRUE WHERE is_read = FALSE");
    }

    /** Competitor mentions. */
    public static function competitorMentions(int $limit = 50): array
    {
        $stmt = self::pdo()->prepare(
            "SELECT * FROM social_mentions
             WHERE is_competitor = TRUE
             ORDER BY found_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Dashboard summary. */
    public static function dashboardStats(): array
    {
        return self::queryOne(
            "SELECT
                COUNT(*) AS total_7d,
                COUNT(*) FILTER (WHERE sentiment = 'positive') AS positive_7d,
                COUNT(*) FILTER (WHERE sentiment = 'negative') AS negative_7d,
                COUNT(*) FILTER (WHERE is_read = FALSE) AS unread,
                COUNT(*) FILTER (WHERE found_at > NOW() - INTERVAL '24 hours') AS today
             FROM social_mentions
             WHERE found_at > NOW() - INTERVAL '7 days'"
        ) ?: ['total_7d' => 0, 'positive_7d' => 0, 'negative_7d' => 0, 'unread' => 0, 'today' => 0];
    }
}