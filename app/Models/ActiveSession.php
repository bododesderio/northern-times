<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\DB;
use PDO;

/**
 * ActiveSession — tracks who is currently logged in.
 */
final class ActiveSession extends BaseModel
{
    protected static string $table      = 'active_sessions';
    protected static string $primaryKey = 'id';
    protected static string $orderBy    = 'last_activity DESC';

    /**
     * Record or update a session for the current user.
     */
    public static function touch(string $sessionId, string $userId): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
            INSERT INTO active_sessions (id, user_id, ip_address, user_agent, last_activity)
            VALUES (:id, :uid, :ip, :ua, NOW())
            ON CONFLICT (id) DO UPDATE SET
                last_activity = NOW(),
                ip_address = :ip2,
                user_agent = :ua2
        ");
        $stmt->execute([
            ':id'  => $sessionId,
            ':uid' => $userId,
            ':ip'  => $ip,
            ':ua'  => $ua,
            ':ip2' => $ip,
            ':ua2' => $ua,
        ]);
    }

    /**
     * Get all active sessions (active in last 30 minutes) with user info.
     */
    public static function activeSessions(int $minutesThreshold = 30): array
    {
        return self::query("
            SELECT s.id AS session_id, s.ip_address, s.user_agent,
                   s.last_activity, s.created_at,
                   u.id AS user_id, u.username, u.email, u.role
            FROM active_sessions s
            JOIN users u ON u.id = s.user_id
            WHERE s.last_activity > NOW() - INTERVAL '" . (int)$minutesThreshold . " minutes'
            ORDER BY s.last_activity DESC
        ");
    }

    /**
     * Get all sessions (including stale) with user info.
     */
    public static function allSessions(): array
    {
        return self::query("
            SELECT s.id AS session_id, s.ip_address, s.user_agent,
                   s.last_activity, s.created_at,
                   u.id AS user_id, u.username, u.email, u.role
            FROM active_sessions s
            JOIN users u ON u.id = s.user_id
            ORDER BY s.last_activity DESC
        ");
    }

    /**
     * Force-terminate a specific session.
     */
    public static function forceLogout(string $sessionId): bool
    {
        return self::delete($sessionId);
    }

    /**
     * Force-terminate all sessions for a user.
     */
    public static function forceLogoutUser(string $userId): int
    {
        return self::execute(
            "DELETE FROM active_sessions WHERE user_id = :uid",
            [':uid' => $userId]
        );
    }

    /**
     * Remove stale sessions older than N hours.
     */
    public static function cleanStale(int $hours = 24): int
    {
        return self::execute(
            "DELETE FROM active_sessions WHERE last_activity < NOW() - INTERVAL '" . (int)$hours . " hours'"
        );
    }

    /**
     * Count currently active users.
     */
    public static function activeCount(int $minutesThreshold = 30): int
    {
        return (int)self::queryColumn(
            "SELECT COUNT(DISTINCT user_id) FROM active_sessions WHERE last_activity > NOW() - INTERVAL '" . (int)$minutesThreshold . " minutes'"
        );
    }
}
