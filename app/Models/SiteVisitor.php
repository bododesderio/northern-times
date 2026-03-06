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
     * Uses UPSERT to avoid duplicates. Stores geo coordinates.
     */
    public static function record(string $ip, ?string $userAgent = null, ?string $firstPage = null, ?array $geo = null): void
    {
        try {
            self::execute(
                "INSERT INTO site_visitors (ip_address, user_agent, first_page, visit_date, created_at, latitude, longitude, city, country, country_code, region)
                 VALUES (:ip, :ua, :page, CURRENT_DATE, NOW(), :lat, :lng, :city, :country, :cc, :region)
                 ON CONFLICT (ip_address, visit_date) DO NOTHING",
                [
                    ':ip'      => $ip,
                    ':ua'      => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                    ':page'    => $firstPage ? mb_substr($firstPage, 0, 500) : null,
                    ':lat'     => $geo['lat'] ?? null,
                    ':lng'     => $geo['lon'] ?? null,
                    ':city'    => $geo['city'] ?? null,
                    ':country' => $geo['country_name'] ?? null,
                    ':cc'      => $geo['country'] ?? null,
                    ':region'  => $geo['region'] ?? null,
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
     * Reader map data — aggregated city counts with coordinates for a given period.
     *
     * @param string $period  'today', '7d', '30d', '90d', 'all'
     * @return array{total_visitors: int, cities: array}
     */
    public static function readerMapData(string $period = '30d'): array
    {
        $pdo = self::pdo();

        // Build date filter
        $dateFilter = match ($period) {
            'today' => "AND visit_date = CURRENT_DATE",
            '7d'    => "AND visit_date >= CURRENT_DATE - INTERVAL '7 days'",
            '30d'   => "AND visit_date >= CURRENT_DATE - INTERVAL '30 days'",
            '90d'   => "AND visit_date >= CURRENT_DATE - INTERVAL '90 days'",
            'all'   => "",
            default => "AND visit_date >= CURRENT_DATE - INTERVAL '30 days'",
        };

        // Total visitors for the period
        try {
            $totalSql = "SELECT COUNT(*) FROM site_visitors WHERE 1=1 {$dateFilter}";
            $total = (int)$pdo->query($totalSql)->fetchColumn();
        } catch (\Throwable) {
            $total = 0;
        }

        // Aggregated city data
        try {
            $citySql = "
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
            ";
            $cities = $pdo->query($citySql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $cities = [];
        }

        // Calculate percentages
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
     * Top countries for the reader map summary.
     */
    public static function topCountries(string $period = '30d', int $limit = 10): array
    {
        $pdo = self::pdo();

        $dateFilter = match ($period) {
            'today' => "AND visit_date = CURRENT_DATE",
            '7d'    => "AND visit_date >= CURRENT_DATE - INTERVAL '7 days'",
            '30d'   => "AND visit_date >= CURRENT_DATE - INTERVAL '30 days'",
            '90d'   => "AND visit_date >= CURRENT_DATE - INTERVAL '90 days'",
            'all'   => "",
            default => "AND visit_date >= CURRENT_DATE - INTERVAL '30 days'",
        };

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