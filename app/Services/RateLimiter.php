<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Login Rate Limiter — blocks brute-force attacks.
 *
 * Tracks failed login attempts by IP address in the `login_attempts` table.
 * After MAX_ATTEMPTS failures within WINDOW_MINUTES, the IP is locked out.
 *
 * The table auto-cleans stale rows on every check (no cron needed).
 */
final class RateLimiter
{
    /** Max failed attempts before lockout */
    private const MAX_ATTEMPTS = 5;

    /** Time window in minutes */
    private const WINDOW_MINUTES = 15;

    /**
     * Check if an IP is currently rate-limited.
     *
     * @return array{blocked: bool, remaining: int, retry_after: ?int}
     *   - blocked: true if the IP has exceeded the limit
     *   - remaining: attempts left before lockout
     *   - retry_after: seconds until the oldest attempt expires (null if not blocked)
     */
    public static function check(string $ip): array
    {
        $pdo = DB::pdo();

        // Clean up expired rows (older than the window)
        $pdo->prepare("
            DELETE FROM login_attempts
            WHERE attempted_at < NOW() - INTERVAL '" . (int)self::WINDOW_MINUTES . " minutes'
        ")->execute();

        // Count recent failures for this IP
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS cnt,
                   MIN(attempted_at) AS oldest
            FROM login_attempts
            WHERE ip_address = :ip
              AND attempted_at > NOW() - INTERVAL '" . (int)self::WINDOW_MINUTES . " minutes'
        ");
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch();

        $count     = (int)($row['cnt'] ?? 0);
        $remaining = max(0, self::MAX_ATTEMPTS - $count);
        $blocked   = $count >= self::MAX_ATTEMPTS;

        $retryAfter = null;
        if ($blocked && !empty($row['oldest'])) {
            // Seconds until the oldest attempt expires
            $oldestTs   = strtotime($row['oldest']);
            $expiresAt  = $oldestTs + (self::WINDOW_MINUTES * 60);
            $retryAfter = max(1, $expiresAt - time());
        }

        return [
            'blocked'     => $blocked,
            'remaining'   => $remaining,
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * Record a failed login attempt.
     */
    public static function recordFailure(string $ip, string $email = ''): void
    {
        $pdo  = DB::pdo();
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (ip_address, email, attempted_at)
            VALUES (:ip, :email, NOW())
        ");
        $stmt->execute([
            ':ip'    => $ip,
            ':email' => $email,
        ]);
    }

    /**
     * Clear all attempts for an IP (called on successful login).
     */
    public static function clearAttempts(string $ip): void
    {
        $pdo = DB::pdo();
        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")
            ->execute([':ip' => $ip]);
    }

    /**
     * Get info for display: how many attempts remain, etc.
     */
    public static function attemptsRemaining(string $ip): int
    {
        return self::check($ip)['remaining'];
    }

    /**
     * Generic rate limiter using Redis sliding window.
     * Returns true if the request is allowed, false if rate-limited.
     *
     * @param string $key   Unique key (e.g. 'api:search:127.0.0.1')
     * @param int    $max   Max requests allowed in the window
     * @param int    $window Window size in seconds
     */
    public static function allow(string $key, int $max = 30, int $window = 60): bool
    {
        try {
            $redis = Cache::redis();
            if (!$redis) return true; // fail open if Redis unavailable

            $redisKey = 'rl:' . $key;
            $now = microtime(true);

            // Remove expired entries
            $redis->zRemRangeByScore($redisKey, '-inf', (string)($now - $window));

            // Count current entries
            $count = $redis->zCard($redisKey);
            if ($count >= $max) {
                return false;
            }

            // Add this request
            $redis->zAdd($redisKey, $now, $now . ':' . bin2hex(random_bytes(4)));
            $redis->expire($redisKey, $window + 1);

            return true;
        } catch (\Throwable) {
            return true; // fail open
        }
    }
}