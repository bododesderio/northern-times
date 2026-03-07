<?php
declare(strict_types=1);

namespace App\Models;

/**
 * SiteVisitor model — site_visitors table.
 */
final class SiteVisitor extends BaseModel
{
    protected static string $table   = 'site_visitors';
    protected static string $orderBy = 'visit_date DESC';

    /**
     * Record a unique daily visitor (one row per IP per day).
     * Uses UPSERT to avoid duplicates. Stores geo coordinates and device info.
     */
    public static function record(string $ip, ?string $userAgent = null, ?string $firstPage = null, ?array $geo = null, ?array $device = null): void
    {
        try {
            self::execute(
                "INSERT INTO site_visitors (ip_address, user_agent, first_page, visit_date, created_at,
                    latitude, longitude, city, country, country_code, region,
                    device_type, browser, os)
                 VALUES (:ip, :ua, :page, CURRENT_DATE, NOW(),
                    :lat, :lng, :city, :country, :cc, :region,
                    :device_type, :browser, :os)
                 ON CONFLICT (ip_address, visit_date) DO UPDATE SET
                    user_agent = COALESCE(EXCLUDED.user_agent, site_visitors.user_agent),
                    first_page = COALESCE(EXCLUDED.first_page, site_visitors.first_page),
                    latitude = COALESCE(site_visitors.latitude, EXCLUDED.latitude),
                    longitude = COALESCE(site_visitors.longitude, EXCLUDED.longitude),
                    city = COALESCE(site_visitors.city, EXCLUDED.city),
                    country = COALESCE(site_visitors.country, EXCLUDED.country),
                    country_code = COALESCE(site_visitors.country_code, EXCLUDED.country_code),
                    region = COALESCE(site_visitors.region, EXCLUDED.region),
                    device_type = COALESCE(EXCLUDED.device_type, site_visitors.device_type),
                    browser = COALESCE(EXCLUDED.browser, site_visitors.browser),
                    os = COALESCE(EXCLUDED.os, site_visitors.os)",
                [
                    ':ip'          => $ip,
                    ':ua'          => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                    ':page'        => $firstPage ? mb_substr($firstPage, 0, 500) : null,
                    ':lat'         => $geo['lat'] ?? null,
                    ':lng'         => $geo['lon'] ?? null,
                    ':city'        => $geo['city'] ?? null,
                    ':country'     => $geo['country_name'] ?? null,
                    ':cc'          => $geo['country'] ?? null,
                    ':region'      => $geo['region'] ?? null,
                    ':device_type' => $device['device_type'] ?? null,
                    ':browser'     => $device['browser'] ?? null,
                    ':os'          => $device['os'] ?? null,
                ]
            );
        } catch (\Throwable) {
            // Silently fail — visitor tracking should never break the site
        }
    }

    /**
     * Dashboard visitor stats (safe — returns 0 if table empty).
     */
    public static function dashboardCounts(): array
    {
        $pdo = self::pdo();

        $safe = function (string $sql) use ($pdo): int {
            try {
                return (int)$pdo->query($sql)->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };

        return [
            'total'      => $safe("SELECT COUNT(*) FROM site_visitors"),
            'today'      => $safe("SELECT COUNT(*) FROM site_visitors WHERE visit_date = CURRENT_DATE"),
            'this_week'  => $safe("SELECT COUNT(*) FROM site_visitors WHERE visit_date >= CURRENT_DATE - INTERVAL '7 days'"),
            'this_month' => $safe("SELECT COUNT(*) FROM site_visitors WHERE visit_date >= CURRENT_DATE - INTERVAL '30 days'"),
        ];
    }

    /**
     * Build date filter SQL fragment for a given period.
     */
    private static function dateFilter(string $period): string
    {
        return match ($period) {
            'today' => "AND visit_date = CURRENT_DATE",
            '7d'    => "AND visit_date >= CURRENT_DATE - INTERVAL '7 days'",
            '30d'   => "AND visit_date >= CURRENT_DATE - INTERVAL '30 days'",
            '90d'   => "AND visit_date >= CURRENT_DATE - INTERVAL '90 days'",
            'all'   => "",
            default => "AND visit_date >= CURRENT_DATE - INTERVAL '30 days'",
        };
    }

    /**
     * Reader map data — aggregated city counts with coordinates for a given period.
     */
    public static function readerMapData(string $period = '30d'): array
    {
        $pdo = self::pdo();
        $dateFilter = self::dateFilter($period);

        try {
            $total = (int)$pdo->query("SELECT COUNT(*) FROM site_visitors WHERE 1=1 {$dateFilter}")->fetchColumn();
        } catch (\Throwable) {
            $total = 0;
        }

        try {
            $cities = $pdo->query("
                SELECT
                    city,
                    country,
                    country_code,
                    ROUND(AVG(latitude)::numeric, 4) AS lat,
                    ROUND(AVG(longitude)::numeric, 4) AS lng,
                    COUNT(*) AS visitors
                FROM site_visitors
                WHERE latitude IS NOT NULL
                  AND city IS NOT NULL
                  AND city != ''
                  {$dateFilter}
                GROUP BY city, country, country_code
                ORDER BY visitors DESC
                LIMIT 500
            ")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $cities = [];
        }

        foreach ($cities as &$c) {
            $c['visitors']   = (int)$c['visitors'];
            $c['lat']        = (float)$c['lat'];
            $c['lng']        = (float)$c['lng'];
            $c['percentage'] = $total > 0 ? round(($c['visitors'] / $total) * 100, 1) : 0;
        }
        unset($c);

        return [
            'total_visitors' => $total,
            'cities'         => $cities,
        ];
    }

    /**
     * City detail analytics — device breakdown, browsers, OS, top pages, categories visited.
     */
    public static function cityDetail(string $city, string $period = '30d'): array
    {
        $pdo = self::pdo();
        $dateFilter = self::dateFilter($period);

        $safePrep = function (string $sql) use ($pdo, $city) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':city' => $city]);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                return [];
            }
        };

        // Total visitors from this city
        $totalVisitors = 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitors WHERE city = :city {$dateFilter}");
            $stmt->execute([':city' => $city]);
            $totalVisitors = (int)$stmt->fetchColumn();
        } catch (\Throwable) {}

        // Device breakdown
        $devices = $safePrep("
            SELECT COALESCE(device_type, 'unknown') AS device, COUNT(*) AS count
            FROM site_visitors WHERE city = :city {$dateFilter}
            GROUP BY device_type ORDER BY count DESC
        ");

        // Browser breakdown
        $browsers = $safePrep("
            SELECT COALESCE(browser, 'Unknown') AS browser, COUNT(*) AS count
            FROM site_visitors WHERE city = :city {$dateFilter}
            GROUP BY browser ORDER BY count DESC LIMIT 10
        ");

        // OS breakdown
        $oses = $safePrep("
            SELECT COALESCE(os, 'Unknown') AS os, COUNT(*) AS count
            FROM site_visitors WHERE city = :city {$dateFilter}
            GROUP BY os ORDER BY count DESC LIMIT 10
        ");

        // Top landing pages
        $pages = $safePrep("
            SELECT first_page AS page, COUNT(*) AS count
            FROM site_visitors WHERE city = :city AND first_page IS NOT NULL {$dateFilter}
            GROUP BY first_page ORDER BY count DESC LIMIT 10
        ");

        // Top categories visited (by matching first_page to article slugs)
        $categories = $safePrep("
            SELECT c.name AS category, c.slug, COUNT(*) AS count
            FROM site_visitors sv
            JOIN articles a ON sv.first_page = '/' || a.slug
            JOIN categories c ON c.id = a.category_id
            WHERE sv.city = :city {$dateFilter}
            GROUP BY c.name, c.slug ORDER BY count DESC LIMIT 10
        ");

        // Visits over time
        $timeline = $safePrep("
            SELECT visit_date::text AS date, COUNT(*) AS count
            FROM site_visitors WHERE city = :city {$dateFilter}
            GROUP BY visit_date ORDER BY visit_date DESC LIMIT 30
        ");

        // Country info
        $countryInfo = $safePrep("
            SELECT country, country_code, region,
                   ROUND(AVG(latitude)::numeric, 6) AS lat,
                   ROUND(AVG(longitude)::numeric, 6) AS lng
            FROM site_visitors WHERE city = :city AND latitude IS NOT NULL {$dateFilter}
            GROUP BY country, country_code, region
            LIMIT 1
        ");

        // Popup interactions from this city
        $popupStats = [];
        try {
            $stmt = $pdo->prepare("
                SELECT 'impressions' AS metric, COUNT(*) AS count
                FROM popup_events pe
                JOIN site_visitors sv ON sv.ip_address = pe.ip_address AND sv.visit_date = pe.created_at::date
                WHERE sv.city = :city AND pe.event_type = 'impression' {$dateFilter}
                UNION ALL
                SELECT 'conversions' AS metric, COUNT(*) AS count
                FROM popup_events pe
                JOIN site_visitors sv ON sv.ip_address = pe.ip_address AND sv.visit_date = pe.created_at::date
                WHERE sv.city = :city AND pe.event_type = 'conversion' {$dateFilter}
                UNION ALL
                SELECT 'dismissals' AS metric, COUNT(*) AS count
                FROM popup_dismissals pd
                JOIN site_visitors sv ON sv.ip_address = pd.ip_address AND sv.visit_date = pd.dismissed_at::date
                WHERE sv.city = :city {$dateFilter}
            ");
            $stmt->execute([':city' => $city]);
            $popupStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        // Ad interactions from this city
        $adStats = [];
        try {
            $stmt = $pdo->prepare("
                SELECT 'impressions' AS metric, COUNT(*) AS count
                FROM ad_events ae
                JOIN site_visitors sv ON sv.ip_address = ae.ip_address AND sv.visit_date = ae.created_at::date
                WHERE sv.city = :city AND ae.event_type = 'impression' {$dateFilter}
                UNION ALL
                SELECT 'clicks' AS metric, COUNT(*) AS count
                FROM ad_events ae
                JOIN site_visitors sv ON sv.ip_address = ae.ip_address AND sv.visit_date = ae.created_at::date
                WHERE sv.city = :city AND ae.event_type = 'click' {$dateFilter}
            ");
            $stmt->execute([':city' => $city]);
            $adStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        // Top ad slots by interaction in this city
        $topAds = [];
        try {
            $stmt = $pdo->prepare("
                SELECT ads.slot_name, ads.label, ae.event_type,
                       COUNT(*) AS count
                FROM ad_events ae
                JOIN ad_slots ads ON ads.id = ae.ad_slot_id
                JOIN site_visitors sv ON sv.ip_address = ae.ip_address AND sv.visit_date = ae.created_at::date
                WHERE sv.city = :city {$dateFilter}
                GROUP BY ads.slot_name, ads.label, ae.event_type
                ORDER BY count DESC LIMIT 10
            ");
            $stmt->execute([':city' => $city]);
            $topAds = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        // Format device counts with percentages
        foreach ($devices as &$d) {
            $d['count'] = (int)$d['count'];
            $d['pct'] = $totalVisitors > 0 ? round(($d['count'] / $totalVisitors) * 100, 1) : 0;
        }
        unset($d);
        foreach ($browsers as &$b) {
            $b['count'] = (int)$b['count'];
            $b['pct'] = $totalVisitors > 0 ? round(($b['count'] / $totalVisitors) * 100, 1) : 0;
        }
        unset($b);
        foreach ($oses as &$o) {
            $o['count'] = (int)$o['count'];
            $o['pct'] = $totalVisitors > 0 ? round(($o['count'] / $totalVisitors) * 100, 1) : 0;
        }
        unset($o);

        return [
            'city'         => $city,
            'total'        => $totalVisitors,
            'location'     => $countryInfo[0] ?? null,
            'devices'      => $devices,
            'browsers'     => $browsers,
            'os'           => $oses,
            'top_pages'    => $pages,
            'categories'   => $categories,
            'timeline'     => array_reverse($timeline),
            'popup_stats'  => $popupStats,
            'ad_stats'     => $adStats,
            'top_ads'      => $topAds,
        ];
    }

    /**
     * Top countries for the reader map summary.
     */
    public static function topCountries(string $period = '30d', int $limit = 10): array
    {
        $pdo = self::pdo();
        $dateFilter = self::dateFilter($period);

        try {
            return $pdo->query("
                SELECT country, country_code, COUNT(*) AS visitors
                FROM site_visitors
                WHERE country IS NOT NULL AND country != ''
                  {$dateFilter}
                GROUP BY country, country_code
                ORDER BY visitors DESC
                LIMIT " . (int)$limit . "
            ")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
}
