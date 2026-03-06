<?php
declare(strict_types=1);

namespace App\Services;

/**
 * GeoIP — Lightweight IP geolocation using ip-api.com (free tier).
 *
 * Free tier: 45 requests/minute, no API key needed.
 * Results are cached in storage/cache/ for 24 hours to stay within limits.
 *
 * Returns: ['country' => 'UG', 'country_name' => 'Uganda', 'city' => 'Kampala',
 *           'region' => 'Central Region', 'lat' => 0.3163, 'lon' => 32.5822, 'timezone' => 'Africa/Kampala']
 */
final class GeoIP
{
    private const API_URL   = 'http://ip-api.com/json/';
    private const CACHE_TTL = 86400; // 24 hours
    private const TIMEOUT   = 3;     // seconds

    /**
     * Get the real client IP — handles proxies, Docker, load balancers.
     *
     * Priority: X-Forwarded-For → X-Real-IP → REMOTE_ADDR
     * Skips private IPs in X-Forwarded-For chain (proxy IPs).
     */
    public static function clientIP(): string
    {
        // X-Forwarded-For may contain: "client, proxy1, proxy2"
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            $ips = array_map('trim', explode(',', $xff));
            foreach ($ips as $ip) {
                // Return the first non-private IP (the real client)
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // X-Real-IP (set by Nginx)
        $xri = $_SERVER['HTTP_X_REAL_IP'] ?? '';
        if ($xri !== '' && filter_var($xri, FILTER_VALIDATE_IP)) {
            return $xri;
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Look up geolocation for an IP address.
     *
     * @return array{country: string, country_name: string, city: string, region: string, lat: float, lon: float, timezone: string}
     */
    public static function lookup(string $ip): array
    {
        // Don't look up private/local IPs — return timezone-based fallback
        if (self::isPrivate($ip)) {
            return self::localFallback();
        }

        // Check cache first
        $cached = self::fromCache($ip);
        if ($cached !== null) {
            return $cached;
        }

        // Call the API
        $data = self::fetch($ip);
        if ($data !== null) {
            self::toCache($ip, $data);
            return $data;
        }

        // API failed — return timezone-based best guess
        return self::localFallback();
    }

    /**
     * Fetch from ip-api.com
     */
    private static function fetch(string $ip): ?array
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => self::TIMEOUT,
                    'header'  => "Accept: application/json\r\nUser-Agent: NorthernTimesCMS/1.0\r\n",
                ],
            ]);

            $url  = self::API_URL . urlencode($ip) . '?fields=status,country,countryCode,regionName,city,lat,lon,timezone';
            $json = @file_get_contents($url, false, $ctx);

            if ($json === false) return null;

            $raw = json_decode($json, true);
            if (!is_array($raw) || ($raw['status'] ?? '') !== 'success') return null;

            return [
                'country'      => $raw['countryCode'] ?? '',
                'country_name' => $raw['country'] ?? '',
                'city'         => $raw['city'] ?? '',
                'region'       => $raw['regionName'] ?? '',
                'lat'          => (float)($raw['lat'] ?? 0),
                'lon'          => (float)($raw['lon'] ?? 0),
                'timezone'     => $raw['timezone'] ?? '',
            ];
        } catch (\Throwable $e) {
            error_log('GeoIP fetch error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if IP is private/localhost.
     */
    private static function isPrivate(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)) return true;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Fallback for private/local IPs — uses server timezone to guess country.
     *
     * This ensures the frontend never shows "Loading..." indefinitely.
     * Common timezone → country mappings for East Africa and major regions.
     */
    private static function localFallback(): array
    {
        $tz = date_default_timezone_get();

        // Timezone → country mapping (covers server's likely location)
        $tzMap = [
            // East Africa
            'Africa/Kampala'       => ['UG', 'Uganda',       'Kampala',       'Central Region',  0.3163, 32.5822],
            'Africa/Nairobi'       => ['KE', 'Kenya',        'Nairobi',       'Nairobi County', -1.2864, 36.8172],
            'Africa/Dar_es_Salaam' => ['TZ', 'Tanzania',     'Dar es Salaam', 'Dar es Salaam',  -6.7924, 39.2083],
            'Africa/Kigali'        => ['RW', 'Rwanda',       'Kigali',        'Kigali',         -1.9403, 29.8739],
            'Africa/Juba'          => ['SS', 'South Sudan',  'Juba',          'Central Equatoria', 4.8594, 31.5713],
            'Africa/Addis_Ababa'   => ['ET', 'Ethiopia',     'Addis Ababa',   'Addis Ababa',     9.0192, 38.7525],
            // West Africa
            'Africa/Lagos'         => ['NG', 'Nigeria',      'Lagos',         'Lagos',           6.5244,  3.3792],
            'Africa/Accra'         => ['GH', 'Ghana',        'Accra',         'Greater Accra',   5.6037, -0.1870],
            // Southern Africa
            'Africa/Johannesburg'  => ['ZA', 'South Africa', 'Johannesburg',  'Gauteng',       -26.2041, 28.0473],
            // Europe
            'Europe/London'        => ['GB', 'United Kingdom','London',       'England',        51.5074, -0.1278],
            'Europe/Paris'         => ['FR', 'France',       'Paris',         'Île-de-France',  48.8566,  2.3522],
            'Europe/Berlin'        => ['DE', 'Germany',      'Berlin',        'Berlin',         52.5200, 13.4050],
            // Americas
            'America/New_York'     => ['US', 'United States','New York',      'New York',       40.7128, -74.0060],
            'America/Chicago'      => ['US', 'United States','Chicago',       'Illinois',       41.8781, -87.6298],
            'America/Los_Angeles'  => ['US', 'United States','Los Angeles',   'California',     34.0522,-118.2437],
            // Asia
            'Asia/Dubai'           => ['AE', 'UAE',          'Dubai',         'Dubai',          25.2048, 55.2708],
            'Asia/Kolkata'         => ['IN', 'India',        'Mumbai',        'Maharashtra',    19.0760, 72.8777],
            'Asia/Shanghai'        => ['CN', 'China',        'Shanghai',      'Shanghai',       31.2304,121.4737],
            'Asia/Tokyo'           => ['JP', 'Japan',        'Tokyo',         'Tokyo',          35.6762,139.6503],
        ];

        if (isset($tzMap[$tz])) {
            [$code, $name, $city, $region, $lat, $lon] = $tzMap[$tz];
            return [
                'country'      => $code,
                'country_name' => $name,
                'city'         => $city,
                'region'       => $region,
                'lat'          => $lat,
                'lon'          => $lon,
                'timezone'     => $tz,
            ];
        }

        // Last resort: parse timezone continent/city
        $parts = explode('/', $tz, 2);
        $city  = isset($parts[1]) ? str_replace('_', ' ', $parts[1]) : '';

        return [
            'country'      => '',
            'country_name' => '',
            'city'         => $city,
            'region'       => $parts[0] ?? '',
            'lat'          => 0.0,
            'lon'          => 0.0,
            'timezone'     => $tz,
        ];
    }

    /**
     * Read from file cache.
     */
    private static function fromCache(string $ip): ?array
    {
        $file = self::cacheFile($ip);
        if (!file_exists($file)) return null;
        if (filemtime($file) < time() - self::CACHE_TTL) {
            @unlink($file);
            return null;
        }
        $data = @json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Write to file cache.
     */
    private static function toCache(string $ip, array $data): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents(self::cacheFile($ip), json_encode($data));
    }

    private static function cacheDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/geoip';
    }

    private static function cacheFile(string $ip): string
    {
        return self::cacheDir() . '/' . md5($ip) . '.json';
    }

    /**
     * Empty result for unknown/private IPs.
     */
    private static function empty(): array
    {
        return [
            'country'      => '',
            'country_name' => '',
            'city'         => '',
            'region'       => '',
            'lat'          => 0.0,
            'lon'          => 0.0,
            'timezone'     => '',
        ];
    }
}