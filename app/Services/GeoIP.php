<?php
declare(strict_types=1);

namespace App\Services;

use GeoIp2\Database\Reader;

/**
 * GeoIP — Hybrid IP geolocation.
 *
 * Priority:
 *   1. MaxMind GeoLite2 local database (fast, accurate to city level)
 *   2. ip-api.com free API (fallback if .mmdb not installed)
 *
 * Browser Geolocation (GPS-level accuracy) is handled client-side
 * and sent to /api/visitor-location to override these results.
 */
final class GeoIP
{
    private const MMDB_PATH    = __DIR__ . '/../../storage/geoip/GeoLite2-City.mmdb';
    private const API_URL      = 'http://ip-api.com/json/';
    private const CACHE_TTL    = 86400;
    private const TIMEOUT      = 3;

    private static ?Reader $reader = null;

    /**
     * Get the real client IP — handles proxies, Docker, load balancers.
     */
    public static function clientIP(): string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            $ips = array_map('trim', explode(',', $xff));
            foreach ($ips as $ip) {
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        $xri = $_SERVER['HTTP_X_REAL_IP'] ?? '';
        if ($xri !== '' && filter_var($xri, FILTER_VALIDATE_IP)) {
            return $xri;
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Look up geolocation for an IP address.
     */
    public static function lookup(string $ip): array
    {
        if (self::isPrivate($ip)) {
            return self::localFallback();
        }

        // Check cache first
        $cached = self::fromCache($ip);
        if ($cached !== null) {
            return $cached;
        }

        // Try MaxMind local database first
        $data = self::lookupMaxMind($ip);

        // Fallback to ip-api.com
        if ($data === null) {
            $data = self::lookupIpApi($ip);
        }

        if ($data !== null) {
            self::toCache($ip, $data);
            return $data;
        }

        return self::localFallback();
    }

    /**
     * MaxMind GeoLite2 local database lookup.
     */
    private static function lookupMaxMind(string $ip): ?array
    {
        try {
            if (!file_exists(self::MMDB_PATH)) {
                return null;
            }

            if (self::$reader === null) {
                self::$reader = new Reader(self::MMDB_PATH);
            }

            $record = self::$reader->city($ip);

            $city = $record->city->name ?? '';
            $country = $record->country->isoCode ?? '';
            $countryName = $record->country->name ?? '';
            $region = $record->mostSpecificSubdivision->name ?? '';
            $lat = $record->location->latitude ?? 0.0;
            $lon = $record->location->longitude ?? 0.0;
            $tz = $record->location->timeZone ?? '';

            // MaxMind sometimes returns empty city — still useful for country
            return [
                'country'      => $country,
                'country_name' => $countryName,
                'city'         => $city,
                'region'       => $region,
                'lat'          => (float) $lat,
                'lon'          => (float) $lon,
                'timezone'     => $tz,
                'source'       => 'maxmind',
            ];
        } catch (\Throwable $e) {
            error_log('GeoIP MaxMind error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ip-api.com fallback.
     */
    private static function lookupIpApi(string $ip): ?array
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => self::TIMEOUT,
                    'header'  => "Accept: application/json\r\nUser-Agent: " . bot_name() . "/1.0\r\n",
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
                'lat'          => (float) ($raw['lat'] ?? 0),
                'lon'          => (float) ($raw['lon'] ?? 0),
                'timezone'     => $raw['timezone'] ?? '',
                'source'       => 'ipapi',
            ];
        } catch (\Throwable $e) {
            error_log('GeoIP ip-api error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Reverse geocode coordinates to city/country via Nominatim (free, no key).
     */
    public static function reverseGeocode(float $lat, float $lon): ?array
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header'  => "User-Agent: " . bot_name() . "/1.0\r\n",
                ],
            ]);

            $url = sprintf(
                'https://nominatim.openstreetmap.org/reverse?lat=%f&lon=%f&format=json&zoom=10&addressdetails=1',
                $lat, $lon
            );

            $json = @file_get_contents($url, false, $ctx);
            if ($json === false) return null;

            $raw = json_decode($json, true);
            if (!is_array($raw) || !isset($raw['address'])) return null;

            $addr = $raw['address'];

            // Nominatim uses different keys for city depending on the area
            $city = $addr['city']
                ?? $addr['town']
                ?? $addr['village']
                ?? $addr['municipality']
                ?? $addr['county']
                ?? '';

            $countryCode = strtoupper($addr['country_code'] ?? '');

            return [
                'country'      => $countryCode,
                'country_name' => $addr['country'] ?? '',
                'city'         => $city,
                'region'       => $addr['state'] ?? $addr['region'] ?? '',
                'lat'          => $lat,
                'lon'          => $lon,
                'timezone'     => '',
                'source'       => 'gps',
            ];
        } catch (\Throwable $e) {
            error_log('GeoIP reverse geocode error: ' . $e->getMessage());
            return null;
        }
    }

    private static function isPrivate(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)) return true;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private static function localFallback(): array
    {
        $tz = date_default_timezone_get();

        $tzMap = [
            'Africa/Kampala'       => ['UG', 'Uganda',       'Kampala',       'Central Region',  0.3163, 32.5822],
            'Africa/Nairobi'       => ['KE', 'Kenya',        'Nairobi',       'Nairobi County', -1.2864, 36.8172],
            'Africa/Dar_es_Salaam' => ['TZ', 'Tanzania',     'Dar es Salaam', 'Dar es Salaam',  -6.7924, 39.2083],
            'Africa/Kigali'        => ['RW', 'Rwanda',       'Kigali',        'Kigali',         -1.9403, 29.8739],
            'Africa/Juba'          => ['SS', 'South Sudan',  'Juba',          'Central Equatoria', 4.8594, 31.5713],
            'Africa/Addis_Ababa'   => ['ET', 'Ethiopia',     'Addis Ababa',   'Addis Ababa',     9.0192, 38.7525],
            'Africa/Lagos'         => ['NG', 'Nigeria',      'Lagos',         'Lagos',           6.5244,  3.3792],
            'Africa/Accra'         => ['GH', 'Ghana',        'Accra',         'Greater Accra',   5.6037, -0.1870],
            'Africa/Johannesburg'  => ['ZA', 'South Africa', 'Johannesburg',  'Gauteng',       -26.2041, 28.0473],
            'Europe/London'        => ['GB', 'United Kingdom','London',       'England',        51.5074, -0.1278],
            'America/New_York'     => ['US', 'United States','New York',      'New York',       40.7128, -74.0060],
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
                'source'       => 'fallback',
            ];
        }

        $parts = explode('/', $tz, 2);
        return [
            'country'      => '',
            'country_name' => '',
            'city'         => isset($parts[1]) ? str_replace('_', ' ', $parts[1]) : '',
            'region'       => $parts[0] ?? '',
            'lat'          => 0.0,
            'lon'          => 0.0,
            'timezone'     => $tz,
            'source'       => 'fallback',
        ];
    }

    private static function fromCache(string $ip): ?array
    {
        $file = self::cacheFile($ip);
        if (!file_exists($file)) return null;
        if (filemtime($file) < time() - self::CACHE_TTL) {
            @unlink($file);
            return null;
        }
        $data = @json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

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
        return self::cacheDir() . '/' . hash('sha256', $ip) . '.json';
    }
}
