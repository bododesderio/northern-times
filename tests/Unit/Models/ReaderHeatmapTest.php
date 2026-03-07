<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Services\DB;
use PDO;

/**
 * Phase 12: Reader Heatmap Tests
 */
final class ReaderHeatmapTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../..');
        $dotenv->safeLoad();
        self::$pdo = DB::pdo();
    }

    // ── Migration Tests ─────────────────────────────────────

    public function test_site_visitors_has_latitude_column(): void
    {
        $exists = (bool)self::$pdo->query("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'site_visitors' AND column_name = 'latitude'
            )
        ")->fetchColumn();

        $this->assertTrue($exists, 'site_visitors should have latitude column');
    }

    public function test_site_visitors_has_longitude_column(): void
    {
        $exists = (bool)self::$pdo->query("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'site_visitors' AND column_name = 'longitude'
            )
        ")->fetchColumn();

        $this->assertTrue($exists, 'site_visitors should have longitude column');
    }

    public function test_site_visitors_has_city_column(): void
    {
        $exists = (bool)self::$pdo->query("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'site_visitors' AND column_name = 'city'
            )
        ")->fetchColumn();

        $this->assertTrue($exists, 'site_visitors should have city column');
    }

    public function test_site_visitors_has_country_column(): void
    {
        $exists = (bool)self::$pdo->query("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'site_visitors' AND column_name = 'country'
            )
        ")->fetchColumn();

        $this->assertTrue($exists, 'site_visitors should have country column');
    }

    public function test_site_visitors_has_country_code_column(): void
    {
        $exists = (bool)self::$pdo->query("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'site_visitors' AND column_name = 'country_code'
            )
        ")->fetchColumn();

        $this->assertTrue($exists, 'site_visitors should have country_code column');
    }

    public function test_site_visitors_has_region_column(): void
    {
        $exists = (bool)self::$pdo->query("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'site_visitors' AND column_name = 'region'
            )
        ")->fetchColumn();

        $this->assertTrue($exists, 'site_visitors should have region column');
    }

    public function test_geo_index_exists(): void
    {
        $exists = (bool)self::$pdo->query("
            SELECT EXISTS (
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'site_visitors' AND indexname = 'idx_visitors_geo'
            )
        ")->fetchColumn();

        $this->assertTrue($exists, 'idx_visitors_geo index should exist');
    }

    // ── Model Tests ─────────────────────────────────────────

    public function test_record_with_geo_data(): void
    {
        $ip = '192.0.2.' . rand(100, 254);

        // Clean up first
        self::$pdo->prepare("DELETE FROM site_visitors WHERE ip_address = :ip::inet")->execute([':ip' => $ip]);

        \App\Models\SiteVisitor::record($ip, 'TestAgent', '/test', [
            'lat'          => 0.3476,
            'lon'          => 32.5825,
            'city'         => 'Kampala',
            'country_name' => 'Uganda',
            'country'      => 'UG',
            'region'       => 'Central Region',
        ]);

        $stmt = self::$pdo->prepare("
            SELECT city, country, country_code, latitude, longitude, region
            FROM site_visitors WHERE ip_address = :ip::inet AND visit_date = CURRENT_DATE
        ");
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row, 'Visitor record with geo should be inserted');
        $this->assertSame('Kampala', $row['city']);
        $this->assertSame('Uganda', $row['country']);
        $this->assertSame('UG', $row['country_code']);
        $this->assertEqualsWithDelta(0.3476, (float)$row['latitude'], 0.001);
        $this->assertEqualsWithDelta(32.5825, (float)$row['longitude'], 0.001);
        $this->assertSame('Central Region', $row['region']);

        // Cleanup
        self::$pdo->prepare("DELETE FROM site_visitors WHERE ip_address = :ip::inet")->execute([':ip' => $ip]);
    }

    public function test_record_without_geo_still_works(): void
    {
        $ip = '192.0.2.' . rand(1, 99);

        self::$pdo->prepare("DELETE FROM site_visitors WHERE ip_address = :ip::inet")->execute([':ip' => $ip]);

        \App\Models\SiteVisitor::record($ip, 'TestAgent', '/test', null);

        $stmt = self::$pdo->prepare("
            SELECT city, latitude FROM site_visitors
            WHERE ip_address = :ip::inet AND visit_date = CURRENT_DATE
        ");
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row, 'Visitor record without geo should be inserted');
        $this->assertNull($row['city']);
        $this->assertNull($row['latitude']);

        // Cleanup
        self::$pdo->prepare("DELETE FROM site_visitors WHERE ip_address = :ip::inet")->execute([':ip' => $ip]);
    }

    public function test_reader_map_data_returns_valid_structure(): void
    {
        $data = \App\Models\SiteVisitor::readerMapData('30d');

        $this->assertArrayHasKey('total_visitors', $data);
        $this->assertArrayHasKey('cities', $data);
        $this->assertIsInt($data['total_visitors']);
        $this->assertIsArray($data['cities']);
    }

    public function test_reader_map_data_with_seeded_visitors(): void
    {
        // Seed some test data
        $testIps = [
            ['192.0.2.50', 'Kampala', 'Uganda', 'UG', 0.3476, 32.5825],
            ['192.0.2.51', 'Kampala', 'Uganda', 'UG', 0.3476, 32.5825],
            ['192.0.2.52', 'Nairobi', 'Kenya', 'KE', -1.2864, 36.8172],
        ];

        foreach ($testIps as [$ip, $city, $country, $cc, $lat, $lng]) {
            self::$pdo->prepare("DELETE FROM site_visitors WHERE ip_address = :ip::inet")->execute([':ip' => $ip]);
            \App\Models\SiteVisitor::record($ip, 'TestAgent', '/test', [
                'lat' => $lat, 'lon' => $lng, 'city' => $city,
                'country_name' => $country, 'country' => $cc, 'region' => '',
            ]);
        }

        $data = \App\Models\SiteVisitor::readerMapData('today');

        $this->assertGreaterThanOrEqual(2, count($data['cities']), 'Should have at least 2 cities');

        // Kampala should have 2 visitors
        $kampala = array_filter($data['cities'], fn($c) => $c['city'] === 'Kampala');
        if (!empty($kampala)) {
            $kampala = reset($kampala);
            $this->assertGreaterThanOrEqual(2, $kampala['visitors']);
            $this->assertEqualsWithDelta(0.3476, $kampala['lat'], 0.05);
            $this->assertEqualsWithDelta(32.5825, $kampala['lng'], 0.05);
            $this->assertArrayHasKey('percentage', $kampala);
        }

        // Cleanup
        foreach ($testIps as [$ip]) {
            self::$pdo->prepare("DELETE FROM site_visitors WHERE ip_address = :ip::inet")->execute([':ip' => $ip]);
        }
    }

    public function test_top_countries(): void
    {
        $result = \App\Models\SiteVisitor::topCountries('all', 5);

        $this->assertIsArray($result);
        foreach ($result as $row) {
            $this->assertArrayHasKey('country', $row);
            $this->assertArrayHasKey('country_code', $row);
            $this->assertArrayHasKey('visitors', $row);
        }
    }

    public function test_period_filter_values(): void
    {
        // Each period should not throw
        foreach (['today', '7d', '30d', '90d', 'all'] as $period) {
            $data = \App\Models\SiteVisitor::readerMapData($period);
            $this->assertArrayHasKey('total_visitors', $data, "Period '{$period}' should return valid data");
        }
    }

    // ── File Existence Tests ────────────────────────────────

    public function test_reader_map_js_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../../public/assets/admin/reader-map.js',
            'reader-map.js should exist'
        );
    }

    public function test_migration_file_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../../database/migrations/0042_visitor_coordinates_v2.sql',
            'Migration 0042 should exist'
        );
    }

    public function test_dashboard_contains_map_widget(): void
    {
        $html = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('nt-reader-map', $html, 'Dashboard should contain map widget ID');
        $this->assertStringContainsString('reader-map.js', $html, 'Dashboard should load reader-map.js');
        $this->assertStringContainsString('leaflet', $html, 'Dashboard should load Leaflet');
        $this->assertStringContainsString('leaflet-heat', $html, 'Dashboard should load Leaflet.heat');
    }

    // ── Route Tests ─────────────────────────────────────────

    public function test_reader_map_api_route_defined(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../../routes/admin.php');
        $this->assertStringContainsString('admin_reader_map_api', $routes);
        $this->assertStringContainsString('/admin/api/reader-map', $routes);
        $this->assertStringContainsString('readerMapApi', $routes);
    }

    // ── Controller Tests ────────────────────────────────────

    public function test_admin_controller_has_reader_map_method(): void
    {
        $this->assertTrue(
            method_exists(\App\Controllers\AdminController::class, 'readerMapApi'),
            'AdminController should have readerMapApi method'
        );
    }

    // ── GeoIP Tests ─────────────────────────────────────────

    public function test_geoip_service_returns_lat_lon(): void
    {
        // Test with a private IP — should return fallback with lat/lon
        $data = \App\Services\GeoIP::lookup('127.0.0.1');

        $this->assertArrayHasKey('lat', $data);
        $this->assertArrayHasKey('lon', $data);
        $this->assertArrayHasKey('city', $data);
        $this->assertArrayHasKey('country', $data);
    }

    // ── Visitor Middleware Tests ────────────────────────────

    public function test_visitor_middleware_imports_geoip(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../app/Middleware/VisitorMiddleware.php');
        $this->assertStringContainsString('GeoIP', $source, 'VisitorMiddleware should use GeoIP service');
        $this->assertStringContainsString('GeoIP::lookup', $source, 'VisitorMiddleware should call GeoIP::lookup');
    }
}
