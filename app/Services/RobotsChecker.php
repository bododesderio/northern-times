<?php
declare(strict_types=1);

namespace App\Services;

/**
 * RobotsChecker — fetches and caches robots.txt per domain, then checks
 * whether a given URL is crawlable for a given user-agent.
 *
 * Parsed rules are cached in Redis (key: robots:<host>) for 24 hours to
 * avoid hammering target servers. Falls back to "allowed" if robots.txt
 * cannot be fetched or parsed.
 */
final class RobotsChecker
{
    public const USER_AGENT = 'NorthernTimesBot';
    public const USER_AGENT_FULL = 'NorthernTimesBot/1.0';
    private const CACHE_TTL = 86400; // 24 hours
    private const FETCH_TIMEOUT = 5;

    /**
     * Check whether $url is allowed for our crawler user-agent.
     * Returns true (allowed) on any fetch/parse error — fail open.
     */
    public static function isAllowed(string $url): bool
    {
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return true; // can't determine host — allow
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host'];
        $path   = $parsed['path'] ?? '/';

        $rules = self::getRules($scheme, $host);
        if ($rules === null) {
            return true; // robots.txt unavailable — fail open
        }

        return self::checkRules($rules, $path);
    }

    /**
     * Return parsed rules for a host, using Redis cache if available.
     * Returns null if robots.txt could not be fetched.
     *
     * Rules format: ['allow' => ['/path', ...], 'disallow' => ['/path', ...]]
     * from the most-specific matching user-agent block.
     */
    private static function getRules(string $scheme, string $host): ?array
    {
        $cacheKey = 'robots:' . $host;

        // Try Redis cache first
        $redis = self::redis();
        if ($redis) {
            $cached = $redis->get($cacheKey);
            if ($cached !== false) {
                return $cached === 'null' ? null : json_decode($cached, true);
            }
        }

        // Fetch robots.txt
        $robotsUrl = $scheme . '://' . $host . '/robots.txt';
        $content   = self::fetch($robotsUrl);

        if ($content === null) {
            self::cache($redis, $cacheKey, 'null');
            return null;
        }

        $rules = self::parse($content);
        self::cache($redis, $cacheKey, json_encode($rules));
        return $rules;
    }

    /**
     * Parse robots.txt content and extract rules for NorthernTimesBot
     * or the wildcard (*) agent, preferring the specific agent.
     */
    private static function parse(string $content): array
    {
        $lines  = preg_split('/\r?\n/', $content);
        $blocks = []; // ['agents' => [...], 'allow' => [...], 'disallow' => [...]]
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip blank lines and comments — do NOT close the current block
            // (blank lines within a User-agent block are allowed per RFC 9309)
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // A new User-agent directive after rules closes the previous block
            if (preg_match('/^User-agent:/i', $line) && $current !== null && (!empty($current['allow']) || !empty($current['disallow']))) {
                $blocks[] = $current;
                $current  = null;
            }

            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
                if ($current === null) {
                    $current = ['agents' => [], 'allow' => [], 'disallow' => []];
                }
                $current['agents'][] = trim($m[1]);
            } elseif (preg_match('/^Allow:\s*(.*)$/i', $line, $m) && $current !== null) {
                $p = trim($m[1]);
                if ($p !== '') $current['allow'][] = $p;
            } elseif (preg_match('/^Disallow:\s*(.*)$/i', $line, $m) && $current !== null) {
                $p = trim($m[1]);
                if ($p !== '') $current['disallow'][] = $p;
            }
        }
        if ($current !== null) {
            $blocks[] = $current;
        }

        // Find best matching block: specific agent > wildcard
        $specific = null;
        $wildcard = null;
        $botName  = strtolower(self::USER_AGENT);

        foreach ($blocks as $block) {
            foreach ($block['agents'] as $agent) {
                $a = strtolower(trim($agent));
                if ($a === $botName) {
                    $specific = $block;
                    break 2;
                }
                if ($a === '*') {
                    $wildcard = $block;
                }
            }
        }

        $match = $specific ?? $wildcard;
        if ($match === null) {
            return ['allow' => [], 'disallow' => []];
        }

        return ['allow' => $match['allow'], 'disallow' => $match['disallow']];
    }

    /**
     * Check path against parsed rules. Allow rules take precedence over
     * disallow rules when both match (standard robots.txt behaviour).
     */
    private static function checkRules(array $rules, string $path): bool
    {
        $bestAllow    = -1;
        $bestDisallow = -1;

        foreach ($rules['allow'] as $pattern) {
            $len = self::matchLength($pattern, $path);
            if ($len !== null && $len > $bestAllow) {
                $bestAllow = $len;
            }
        }

        foreach ($rules['disallow'] as $pattern) {
            $len = self::matchLength($pattern, $path);
            if ($len !== null && $len > $bestDisallow) {
                $bestDisallow = $len;
            }
        }

        if ($bestDisallow === -1) {
            return true; // nothing disallowed
        }
        if ($bestAllow >= $bestDisallow) {
            return true; // allow wins on equal or longer match
        }
        return false;
    }

    /**
     * Returns the length of the pattern if it matches the path, null otherwise.
     * Supports * wildcard and $ end-of-path anchor.
     */
    private static function matchLength(string $pattern, string $path): ?int
    {
        // Convert robots.txt pattern to regex
        $escaped = preg_quote($pattern, '#');
        $escaped = str_replace('\*', '.*', $escaped);
        $escaped = str_replace('\$', '$', $escaped);
        if (!preg_match('#^' . $escaped . '#', $path)) {
            return null;
        }
        return strlen($pattern);
    }

    /**
     * Fetch a URL with a short timeout, returning null on failure.
     */
    private static function fetch(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout'          => self::FETCH_TIMEOUT,
                'user_agent'       => self::USER_AGENT . '/1.0',
                'follow_location'  => true,
                'max_redirects'    => 3,
                'ignore_errors'    => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $result = @file_get_contents($url, false, $ctx);
        if ($result === false || strlen($result) > 512 * 1024) {
            return null; // failed or suspiciously large
        }
        // Must look like robots.txt (contains User-agent or is empty)
        if (!preg_match('/user-agent/i', $result) && trim($result) !== '') {
            return null;
        }
        return $result;
    }

    private static function cache(?\Redis $redis, string $key, string $value): void
    {
        if ($redis) {
            try { $redis->setex($key, self::CACHE_TTL, $value); } catch (\Throwable) {}
        }
    }

    private static ?\Redis $redisInstance = null;

    private static function redis(): ?\Redis
    {
        if (self::$redisInstance !== null) {
            try {
                self::$redisInstance->ping();
                return self::$redisInstance;
            } catch (\Throwable) {
                self::$redisInstance = null;
            }
        }
        try {
            $r = new \Redis();
            $r->connect($_ENV['REDIS_HOST'] ?? 'redis', (int)($_ENV['REDIS_PORT'] ?? 6379));
            $pass = $_ENV['REDIS_PASSWORD'] ?? null;
            if ($pass) $r->auth($pass);
            self::$redisInstance = $r;
            return $r;
        } catch (\Throwable) {
            return null;
        }
    }
}
