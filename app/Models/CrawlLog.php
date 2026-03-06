<?php
declare(strict_types=1);

namespace App\Models;

final class CrawlLog extends BaseModel
{
    protected static string $table   = 'crawl_logs';
    protected static string $orderBy = 'started_at DESC';

    /** Start a new crawl log entry. Returns the log ID. */
    public static function start(string $sourceId): int
    {
        $row = self::queryOne(
            "INSERT INTO crawl_logs (source_id) VALUES (:sid) RETURNING id",
            [':sid' => $sourceId]
        );
        return (int)($row['id'] ?? 0);
    }

    /** Finish a crawl log entry. */
    public static function finish(int $logId, string $status, int $found, int $new, int $dupes, ?string $error = null, ?array $details = null): void
    {
        self::execute(
            "UPDATE crawl_logs SET
                finished_at    = NOW(),
                status         = :status,
                articles_found = :found,
                articles_new   = :new,
                articles_dupes = :dupes,
                error_message  = :error,
                details        = :details
             WHERE id = :id",
            [
                ':id'      => $logId,
                ':status'  => $status,
                ':found'   => $found,
                ':new'     => $new,
                ':dupes'   => $dupes,
                ':error'   => $error,
                ':details' => $details ? json_encode($details) : null,
            ]
        );
    }

    /** Recent logs for admin panel. */
    public static function recent(int $limit = 50, ?string $sourceId = null): array
    {
        $where = $sourceId ? "WHERE cl.source_id = :sid" : "";
        $sql = "SELECT cl.*, cs.name AS source_name
             FROM crawl_logs cl
             JOIN crawl_sources cs ON cs.id = cl.source_id
             $where
             ORDER BY cl.started_at DESC
             LIMIT :lim";

        $stmt = self::pdo()->prepare($sql);
        if ($sourceId) {
            $stmt->bindValue(':sid', $sourceId);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Stats for dashboard. */
    public static function todayStats(): array
    {
        return self::queryOne(
            "SELECT
                COUNT(*) AS crawls_today,
                COALESCE(SUM(articles_new), 0) AS published_today,
                COUNT(*) FILTER (WHERE status = 'failed') AS errors_today
             FROM crawl_logs
             WHERE started_at >= CURRENT_DATE"
        ) ?: ['crawls_today' => 0, 'published_today' => 0, 'errors_today' => 0];
    }
}