<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Redis Cache — leverages the Redis container already in docker-compose.
 *
 * Usage:
 *   Cache::remember('home:hero', 300, fn() => expensive_query());
 *   Cache::put('key', $data, 600);
 *   Cache::get('key');
 *   Cache::forget('key');
 *   Cache::flush('prefix:*');
 */
final class Cache
{
    private static ?\Redis $conn = null;
    private const PREFIX = 'nt:';

    public static function redis(): ?\Redis
    {
        if (self::$conn !== null) return self::$conn;

        try {
            $r = new \Redis();
            $host = $_ENV['REDIS_HOST'] ?? 'redis';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $r->connect($host, $port, 2.0);
            $r->setOption(\Redis::OPT_PREFIX, self::PREFIX);
            $r->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
            self::$conn = $r;
            return $r;
        } catch (\Throwable) {
            self::$conn = null;
            return null;
        }
    }

    /**
     * Get cached value or execute callback and cache result.
     */
    public static function remember(string $key, int $ttl, callable $cb): mixed
    {
        $r = self::redis();
        if ($r) {
            try {
                $cached = $r->get($key);
                if ($cached !== false) return $cached;
            } catch (\Throwable) {}
        }

        $value = $cb();

        if ($r && $value !== null) {
            try {
                $ttl > 0 ? $r->setex($key, $ttl, $value) : $r->set($key, $value);
            } catch (\Throwable) {}
        }

        return $value;
    }

    public static function get(string $key): mixed
    {
        $r = self::redis();
        if (!$r) return null;
        try {
            $v = $r->get($key);
            return ($v === false) ? null : $v;
        } catch (\Throwable) { return null; }
    }

    public static function put(string $key, mixed $value, int $ttl = 0): void
    {
        $r = self::redis();
        if (!$r) return;
        try { $ttl > 0 ? $r->setex($key, $ttl, $value) : $r->set($key, $value); }
        catch (\Throwable) {}
    }

    public static function forget(string $key): void
    {
        $r = self::redis();
        if (!$r) return;
        try { $r->del($key); } catch (\Throwable) {}
    }

    /** Flush keys matching a pattern (e.g. 'home:*'). */
    public static function flush(string $pattern = '*'): int
    {
        $r = self::redis();
        if (!$r) return 0;
        try {
            $keys = $r->keys($pattern);
            if (empty($keys)) return 0;
            $stripped = array_map(fn($k) => str_replace(self::PREFIX, '', $k), $keys);
            return $r->del(...$stripped);
        } catch (\Throwable) { return 0; }
    }

    public static function available(): bool
    {
        $r = self::redis();
        if (!$r) return false;
        try { return $r->ping() === '+PONG' || $r->ping() === true; }
        catch (\Throwable) { return false; }
    }
}