<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\DB;
use PDO;

/**
 * CronRun — tracks each execution of a scheduled task.
 */
final class CronRun extends BaseModel
{
    protected static string $table   = 'cron_runs';
    protected static string $orderBy = 'started_at DESC';

    /**
     * Start tracking a cron task. Returns the run record.
     */
    public static function start(string $taskName): ?array
    {
        return self::create([
            'task_name'  => $taskName,
            'status'     => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark a run as completed.
     */
    public static function finish(string $id, int $recordsAffected = 0, string $output = ''): ?array
    {
        $started = self::find($id);
        $durationMs = 0;
        if ($started) {
            $durationMs = (int)((microtime(true) - strtotime($started['started_at'])) * 1000);
        }

        return self::update($id, [
            'status'           => 'success',
            'records_affected' => $recordsAffected,
            'output'           => $output,
            'duration_ms'      => $durationMs,
            'finished_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark a run as failed.
     */
    public static function fail(string $id, string $errorMsg): ?array
    {
        $started = self::find($id);
        $durationMs = 0;
        if ($started) {
            $durationMs = (int)((microtime(true) - strtotime($started['started_at'])) * 1000);
        }

        return self::update($id, [
            'status'      => 'error',
            'error_msg'   => $errorMsg,
            'duration_ms' => $durationMs,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get the last run for each unique task.
     */
    public static function lastRunPerTask(): array
    {
        return self::query("
            SELECT DISTINCT ON (task_name)
                task_name, status, duration_ms, records_affected,
                output, error_msg, started_at, finished_at
            FROM cron_runs
            ORDER BY task_name, started_at DESC
        ");
    }

    /**
     * Get recent runs with optional task filter.
     */
    public static function recent(int $limit = 50, ?string $task = null): array
    {
        $limit = max(1, $limit);
        if ($task) {
            $stmt = self::pdo()->prepare(
                "SELECT * FROM cron_runs WHERE task_name = :t ORDER BY started_at DESC LIMIT :l"
            );
            $stmt->bindValue(':t', $task);
            $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        }
        $stmt = self::pdo()->prepare(
            "SELECT * FROM cron_runs ORDER BY started_at DESC LIMIT :l"
        );
        $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get failure count in last N hours.
     */
    public static function failureCount(int $hours = 24): int
    {
        return (int)self::queryColumn(
            "SELECT COUNT(*) FROM cron_runs WHERE status = 'error' AND started_at > NOW() - INTERVAL '" . (int)$hours . " hours'"
        );
    }

    /**
     * Clean old runs beyond retention days.
     */
    public static function cleanOld(int $retainDays = 30): int
    {
        return self::execute(
            "DELETE FROM cron_runs WHERE started_at < NOW() - INTERVAL '" . (int)$retainDays . " days'"
        );
    }
}
