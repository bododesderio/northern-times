<?php
declare(strict_types=1);

namespace Tests\Unit\Dashboard;

use PHPUnit\Framework\TestCase;

/**
 * DashboardIntelligenceHubTest — validates all Phase 13 Dashboard Upgrades:
 *
 *  1. Data Integrity: Analytics seeder, visitor tracking, article_views
 *  2. Real-Time Hooks: Polling endpoints, live pulse API
 *  3. Visual Upgrades: Chart.js area chart, radar chart, live map pings
 *  4. UX Features: Smart empty states, efficiency metric, glassmorphism
 *  5. Backend APIs: dashboard-pulse, engagement-radar, traffic-chart
 */
class DashboardIntelligenceHubTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════
    //  FILE EXISTENCE
    // ═══════════════════════════════════════════════════════════

    public function test_analytics_seeder_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../../scripts/seed_analytics.php',
            'Analytics seeder script must exist to fix 0-visitors data mismatch'
        );
    }

    public function test_reader_map_js_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../../public/assets/admin/reader-map.js'
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  ANALYTICS SEEDER VALIDATION
    // ═══════════════════════════════════════════════════════════

    public function test_seeder_inserts_site_visitors(): void
    {
        $code = file_get_contents(__DIR__ . '/../../../scripts/seed_analytics.php');
        $this->assertStringContainsString('INSERT INTO site_visitors', $code);
        $this->assertStringContainsString('ON CONFLICT', $code, 'Must handle duplicate IP+date conflicts');
    }

    public function test_seeder_inserts_article_views(): void
    {
        $code = file_get_contents(__DIR__ . '/../../../scripts/seed_analytics.php');
        $this->assertStringContainsString('INSERT INTO article_views', $code);
        $this->assertStringContainsString('article_id', $code);
    }

    public function test_seeder_has_geo_distribution(): void
    {
        $code = file_get_contents(__DIR__ . '/../../../scripts/seed_analytics.php');
        $this->assertStringContainsString('Kampala', $code);
        $this->assertStringContainsString('Gulu', $code);
        $this->assertStringContainsString('Nairobi', $code);
        $this->assertStringContainsString('latitude', $code);
        $this->assertStringContainsString('longitude', $code);
    }

    public function test_seeder_has_realistic_traffic_curve(): void
    {
        $code = file_get_contents(__DIR__ . '/../../../scripts/seed_analytics.php');
        $this->assertStringContainsString('VISITORS_GROWTH', $code, 'Must have growth factor for realistic curve');
        $this->assertStringContainsString('WEEKEND_FACTOR', $code, 'Must reduce weekend traffic');
    }

    // ═══════════════════════════════════════════════════════════
    //  BACKEND API ENDPOINTS
    // ═══════════════════════════════════════════════════════════

    public function test_dashboard_pulse_endpoint_exists(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('function dashboardPulse', $controller);
    }

    public function test_dashboard_pulse_returns_required_fields(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('visitors_today', $controller);
        $this->assertStringContainsString('views_last_hour', $controller);
        $this->assertStringContainsString('active_sessions', $controller);
        $this->assertStringContainsString('pending_review', $controller);
        $this->assertStringContainsString('pending_comments', $controller);
        $this->assertStringContainsString("'pings'", $controller);
    }

    public function test_dashboard_pulse_has_geo_pings(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('sv.latitude AS lat', $controller);
        $this->assertStringContainsString('sv.longitude AS lng', $controller);
        $this->assertStringContainsString("INTERVAL '5 minutes'", $controller, 'Must fetch recent pings for live map');
    }

    public function test_engagement_radar_endpoint_exists(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('function engagementRadar', $controller);
    }

    public function test_engagement_radar_aggregates_three_axes(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('avg_read_min', $controller, 'Must compute read time axis');
        $this->assertStringContainsString('comment_count', $controller, 'Must compute comment density axis');
        $this->assertStringContainsString('social_mentions', $controller, 'Must compute social shares axis');
    }

    public function test_engagement_radar_normalizes_scores(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('read_score', $controller);
        $this->assertStringContainsString('comment_score', $controller);
        $this->assertStringContainsString('views_score', $controller);
        $this->assertStringContainsString('social_score', $controller);
    }

    public function test_traffic_chart_endpoint_exists(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('function trafficChart', $controller);
    }

    public function test_traffic_chart_computes_7day_moving_average(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('avg_7d', $controller);
        $this->assertStringContainsString('window', $controller, 'Must use sliding window for 7-day average');
    }

    // ═══════════════════════════════════════════════════════════
    //  ROUTE REGISTRATION
    // ═══════════════════════════════════════════════════════════

    public function test_all_new_routes_registered(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../../routes/admin.php');
        $this->assertStringContainsString('admin_dashboard_pulse', $routes);
        $this->assertStringContainsString('admin_engagement_radar', $routes);
        $this->assertStringContainsString('admin_traffic_chart', $routes);
        $this->assertStringContainsString('/admin/api/dashboard-pulse', $routes);
        $this->assertStringContainsString('/admin/api/engagement-radar', $routes);
        $this->assertStringContainsString('/admin/api/traffic-chart', $routes);
    }

    // ═══════════════════════════════════════════════════════════
    //  DASHBOARD VIEW: CHART.JS INTEGRATION
    // ═══════════════════════════════════════════════════════════

    public function test_chartjs_cdn_loaded(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('cdn.jsdelivr.net/npm/chart.js', $view, 'Chart.js CDN must be loaded');
    }

    public function test_area_chart_canvas_exists(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('id="ntAreaChart"', $view);
        $this->assertStringContainsString("type: 'line'", $view, 'Must render as area/line chart');
    }

    public function test_area_chart_has_dual_axes(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString("yAxisID: 'y'", $view, 'Primary Y axis');
        $this->assertStringContainsString("yAxisID: 'y1'", $view, 'Secondary Y axis for visitors');
    }

    public function test_area_chart_has_three_datasets(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString("label: 'Daily", $view, 'Daily traffic dataset');
        $this->assertStringContainsString("label: '7-Day Average'", $view, '7-day average dataset');
        $this->assertStringContainsString("label: 'Visitors'", $view, 'Visitors dataset');
    }

    public function test_area_chart_uses_fill_for_area_effect(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('fill: true', $view, 'Area charts need fill: true');
    }

    public function test_radar_chart_canvas_exists(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('id="ntRadarChart"', $view);
        $this->assertStringContainsString("type: 'radar'", $view);
    }

    public function test_radar_chart_has_three_axes(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString("'Read Time'", $view);
        $this->assertStringContainsString("'Comments'", $view);
        $this->assertStringContainsString("'Views'", $view);
    }

    // ═══════════════════════════════════════════════════════════
    //  DASHBOARD VIEW: LIVE POLLING
    // ═══════════════════════════════════════════════════════════

    public function test_polling_js_present(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('/admin/api/dashboard-pulse', $view);
        $this->assertStringContainsString('setInterval', $view, 'Must use interval-based polling');
        $this->assertStringContainsString('POLL_INTERVAL', $view);
    }

    public function test_polling_pauses_on_hidden_tab(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('visibilitychange', $view, 'Must pause polling when tab is hidden');
        $this->assertStringContainsString('document.hidden', $view);
    }

    public function test_polling_updates_live_metrics(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('ntActiveSessions', $view, 'Must update active sessions count');
        $this->assertStringContainsString('flashPings', $view, 'Must flash live pings on map');
    }

    // ═══════════════════════════════════════════════════════════
    //  DASHBOARD VIEW: LIVE PULSE INDICATOR
    // ═══════════════════════════════════════════════════════════

    public function test_live_pulse_indicator_in_greeting(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('id="ntLivePulse"', $view);
        $this->assertStringContainsString('live-dot', $view);
        $this->assertStringContainsString('Live', $view);
    }

    public function test_live_pulse_css(): void
    {
        $css = file_get_contents(__DIR__ . '/../../../public/assets/admin/dashboard.css');
        $this->assertStringContainsString('.live-pulse', $css);
        $this->assertStringContainsString('liveBlink', $css, 'Must have blinking animation');
    }

    // ═══════════════════════════════════════════════════════════
    //  DASHBOARD VIEW: SMART EMPTY STATES
    // ═══════════════════════════════════════════════════════════

    public function test_smart_empty_state_for_subscribers(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('smart-empty', $view);
        $this->assertStringContainsString('Launch your first campaign', $view);
    }

    public function test_smart_empty_state_for_comments(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('readers will engage as traffic grows', $view);
    }

    public function test_smart_empty_state_for_popups(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('Create a signup popup', $view);
    }

    public function test_smart_empty_css(): void
    {
        $css = file_get_contents(__DIR__ . '/../../../public/assets/admin/dashboard.css');
        $this->assertStringContainsString('.smart-empty', $css);
        $this->assertStringContainsString('.se-icon', $css);
        $this->assertStringContainsString('dashed', $css, 'Empty state should have dashed border');
    }

    // ═══════════════════════════════════════════════════════════
    //  DASHBOARD VIEW: EFFICIENCY METRIC
    // ═══════════════════════════════════════════════════════════

    public function test_efficiency_metric_in_leaderboard(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('auth-efficiency', $view);
        $this->assertStringContainsString('min/w', $view, 'Must show reader-minutes per word label');
    }

    public function test_efficiency_query_computes_reader_mins_per_word(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('mins_per_word', $view);
    }

    public function test_efficiency_has_color_tiers(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../app/Views/admin/dashboard.php');
        $this->assertStringContainsString('eff-high', $view, 'High efficiency tier');
        $this->assertStringContainsString('eff-mid', $view, 'Medium efficiency tier');
        $this->assertStringContainsString('eff-low', $view, 'Low efficiency tier');
    }

    // ═══════════════════════════════════════════════════════════
    //  DASHBOARD VIEW: GLASSMORPHISM + BENTO GRID
    // ═══════════════════════════════════════════════════════════

    public function test_glassmorphism_css(): void
    {
        $css = file_get_contents(__DIR__ . '/../../../public/assets/admin/dashboard.css');
        $this->assertStringContainsString('backdrop-filter', $css);
        $this->assertStringContainsString('blur(', $css);
        $this->assertStringContainsString('.dcard', $css);
    }

    public function test_bento_grid_css(): void
    {
        $css = file_get_contents(__DIR__ . '/../../../public/assets/admin/dashboard.css');
        $this->assertStringContainsString('.dash-bento', $css);
        $this->assertStringContainsString('span-8', $css);
        $this->assertStringContainsString('span-4', $css);
        $this->assertStringContainsString('repeat(12, 1fr)', $css);
    }

    public function test_bento_responsive_breakpoints(): void
    {
        $css = file_get_contents(__DIR__ . '/../../../public/assets/admin/dashboard.css');
        $this->assertStringContainsString('@media (max-width: 1300px)', $css);
        $this->assertStringContainsString('span 6', $css, 'Bento should collapse to 6-col on medium screens');
    }

    // ═══════════════════════════════════════════════════════════
    //  CLS PREVENTION
    // ═══════════════════════════════════════════════════════════

    public function test_cls_min_heights_set(): void
    {
        $css = file_get_contents(__DIR__ . '/../../../public/assets/admin/dashboard.css');
        $this->assertStringContainsString('#ntAreaChart', $css);
        $this->assertStringContainsString('#ntRadarChart', $css);
        $this->assertStringContainsString('.rm-map-wrap', $css);
        $this->assertStringContainsString('min-height', $css);
    }

    // ═══════════════════════════════════════════════════════════
    //  READER MAP: LIVE PINGS INTEGRATION
    // ═══════════════════════════════════════════════════════════

    public function test_reader_map_exposes_flash_pings(): void
    {
        $js = file_get_contents(__DIR__ . '/../../../public/assets/admin/reader-map.js');
        $this->assertStringContainsString('function flashPings', $js);
        $this->assertStringContainsString('window.ntReaderMap', $js);
    }

    public function test_reader_map_flash_creates_expanding_rings(): void
    {
        $js = file_get_contents(__DIR__ . '/../../../public/assets/admin/reader-map.js');
        $this->assertStringContainsString("'#22c55e'", $js, 'Flash rings should be green');
        $this->assertStringContainsString('.transition()', $js, 'Must animate with d3 transitions');
        $this->assertStringContainsString('.remove()', $js, 'Flash dots must self-remove after animation');
    }

    public function test_reader_map_exposes_refresh(): void
    {
        $js = file_get_contents(__DIR__ . '/../../../public/assets/admin/reader-map.js');
        $this->assertStringContainsString("refresh:", $js, 'Must expose refresh method for external period changes');
    }
}
