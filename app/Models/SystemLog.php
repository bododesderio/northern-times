<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\DB;
use PDO;

/**
 * SystemLog — permanent, append-only audit trail for system admin actions.
 *
 * IMPORTANT: This model intentionally has NO delete method.
 * System logs are permanent and cannot be cleared, even by factory reset.
 */
final class SystemLog extends BaseModel
{
    protected static string $table   = 'system_log';
    protected static string $orderBy = 'created_at DESC';

    /**
     * Record a system action.
     */
    public static function log(
        string $action,
        string $details = '',
        int    $recordsAffected = 0,
        string $dangerLevel = 'safe'
    ): ?array {
        $user  = $_SESSION['user'] ?? null;
        $ip    = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '127.0.0.1');

        return self::create([
            'user_id'          => $user['id'] ?? null,
            'user_email'       => $user['email'] ?? 'system',
            'action'           => $action,
            'details'          => $details,
            'records_affected' => $recordsAffected,
            'danger_level'     => $dangerLevel,
            'ip_address'       => $ip,
        ]);
    }

    /**
     * Get recent log entries with pagination.
     */
    public static function recent(int $page = 1, int $perPage = 50): array
    {
        return self::paginate($page, $perPage);
    }

    /**
     * Get total log count.
     */
    public static function totalCount(): int
    {
        return self::count();
    }

    /**
     * Override delete to prevent log deletion.
     */
    public static function delete(string $id): bool
    {
        throw new \RuntimeException('System logs cannot be deleted.');
    }

    /**
     * Prevent bulk deletion — system logs are permanent.
     */
    public static function truncate(): void
    {
        throw new \RuntimeException('System logs cannot be truncated.');
    }
}