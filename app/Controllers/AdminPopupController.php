<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Popup;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\Flash;
use App\Services\Slug;
use App\Services\DB;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminPopupController extends Controller
{
    // ── List ─────────────────────────────────────────────────────

    public function index(): Response
    {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $status = trim($_GET['status'] ?? '');
        $type   = trim($_GET['type'] ?? '');

        $data = Popup::adminList($page, 20, $status, $type);

        return $this->render('admin/popups/index', [
            'items'  => $data['items'],
            'total'  => $data['total'],
            'page'   => $data['page'],
            'pages'  => $data['pages'],
            'status' => $status,
            'type'   => $type,
            'csrf'   => Csrf::token(),
            'stats'  => Popup::dashboardStats(),
        ]);
    }

    // ── Create form ─────────────────────────────────────────────

    public function create(): Response
    {
        return $this->render('admin/popups/form', [
            'mode'  => 'create',
            'popup' => [
                'name' => '', 'popup_type' => 'newsletter_signup', 'banner_style' => 'card_modal',
                'position' => 'center', 'title' => '', 'body' => '', 'image_url' => '',
                'button_text' => 'Subscribe', 'button_url' => '', 'secondary_btn_text' => '',
                'secondary_btn_url' => '', 'bg_color' => '#ffffff', 'text_color' => '#1a1a1a',
                'btn_bg_color' => '#cc0000', 'btn_text_color' => '#ffffff',
                'overlay_opacity' => '0.50', 'custom_css' => '',
                'trigger_type' => 'page_load', 'trigger_value' => '', 'show_delay' => 0,
                'close_delay' => 0, 'frequency' => 'once_session', 'frequency_days' => '',
                'priority' => 10, 'target_audience' => 'all', 'target_device' => 'all',
                'target_pages' => '', 'target_categories' => '', 'status' => 'draft',
                'start_date' => '', 'end_date' => '', 'has_email_field' => false,
                'subscriber_source' => '', 'sponsor_name' => '', 'click_url' => '',
                'nofollow' => true, 'promo_code' => '', 'campaign_name' => '',
            ],
            'csrf' => Csrf::token(),
        ]);
    }

    // ── Store ────────────────────────────────────────────────────

    public function store(): Response
    {
        $r = Request::createFromGlobals();
        $name = trim((string)$r->request->get('name', ''));

        if ($name === '') {
            Flash::set('error', 'Popup name is required.');
            return $this->redirect('/admin/popups/create');
        }

        $user = Auth::user();

        Popup::store([
            'name'               => $name,
            'slug'               => Slug::unique(Slug::make($name), '', 'popups'),
            'popup_type'         => $this->enum($r, 'popup_type', Popup::TYPES, 'newsletter_signup'),
            'banner_style'       => $this->enum($r, 'banner_style', Popup::STYLES, 'card_modal'),
            'position'           => $this->enum($r, 'position', Popup::POSITIONS, 'center'),
            'title'              => trim((string)$r->request->get('title', '')),
            'body'               => trim((string)$r->request->get('body', '')),
            'image_url'          => trim((string)$r->request->get('image_url', '')) ?: null,
            'button_text'        => trim((string)$r->request->get('button_text', 'Subscribe')),
            'button_url'         => trim((string)$r->request->get('button_url', '')) ?: null,
            'secondary_btn_text' => trim((string)$r->request->get('secondary_btn_text', '')) ?: null,
            'secondary_btn_url'  => trim((string)$r->request->get('secondary_btn_url', '')) ?: null,
            'bg_color'           => trim((string)$r->request->get('bg_color', '#ffffff')),
            'text_color'         => trim((string)$r->request->get('text_color', '#1a1a1a')),
            'btn_bg_color'       => trim((string)$r->request->get('btn_bg_color', '#cc0000')),
            'btn_text_color'     => trim((string)$r->request->get('btn_text_color', '#ffffff')),
            'overlay_opacity'    => max(0, min(1, (float)($r->request->get('overlay_opacity', 0.5)))),
            'custom_css'         => trim((string)$r->request->get('custom_css', '')) ?: null,
            'trigger_type'       => $this->enum($r, 'trigger_type', Popup::TRIGGERS, 'page_load'),
            'trigger_value'      => trim((string)$r->request->get('trigger_value', '')) ?: null,
            'show_delay'         => max(0, (int)$r->request->get('show_delay', 0)),
            'close_delay'        => max(0, (int)$r->request->get('close_delay', 0)),
            'frequency'          => $this->enum($r, 'frequency', Popup::FREQUENCIES, 'once_session'),
            'frequency_days'     => ((int)$r->request->get('frequency_days', 0)) ?: null,
            'priority'           => max(1, (int)$r->request->get('priority', 10)),
            'target_audience'    => trim((string)$r->request->get('target_audience', 'all')),
            'target_device'      => trim((string)$r->request->get('target_device', 'all')),
            'target_pages'       => trim((string)$r->request->get('target_pages', '')) ?: null,
            'target_categories'  => trim((string)$r->request->get('target_categories', '')) ?: null,
            'status'             => $this->enum($r, 'status', Popup::STATUSES, 'draft'),
            'start_date'         => $this->datetime($r, 'start_date'),
            'end_date'           => $this->datetime($r, 'end_date'),
            'has_email_field'    => (bool)$r->request->get('has_email_field', false),
            'subscriber_source'  => trim((string)$r->request->get('subscriber_source', '')) ?: null,
            'sponsor_name'       => trim((string)$r->request->get('sponsor_name', '')) ?: null,
            'click_url'          => trim((string)$r->request->get('click_url', '')) ?: null,
            'nofollow'           => (bool)$r->request->get('nofollow', true),
            'promo_code'         => trim((string)$r->request->get('promo_code', '')) ?: null,
            'campaign_name'      => trim((string)$r->request->get('campaign_name', '')) ?: null,
            'created_by'         => $user['id'],
        ]);

        Flash::set('success', 'Popup created.');
        return $this->redirect('/admin/popups');
    }

    // ── Edit form ───────────────────────────────────────────────

    public function edit(string $id): Response
    {
        $popup = Popup::find($id);
        if (!$popup) return new Response("404", 404);

        return $this->render('admin/popups/form', [
            'mode'  => 'edit',
            'popup' => $popup,
            'csrf'  => Csrf::token(),
        ]);
    }

    // ── Update ──────────────────────────────────────────────────

    public function update(string $id): Response
    {
        $popup = Popup::find($id);
        if (!$popup) return new Response("404", 404);

        $r = Request::createFromGlobals();
        $name = trim((string)$r->request->get('name', ''));

        if ($name === '') {
            Flash::set('error', 'Popup name is required.');
            return $this->redirect('/admin/popups/' . $id . '/edit');
        }

        Popup::updatePopup($id, [
            'name'               => $name,
            'popup_type'         => $this->enum($r, 'popup_type', Popup::TYPES, 'newsletter_signup'),
            'banner_style'       => $this->enum($r, 'banner_style', Popup::STYLES, 'card_modal'),
            'position'           => $this->enum($r, 'position', Popup::POSITIONS, 'center'),
            'title'              => trim((string)$r->request->get('title', '')),
            'body'               => trim((string)$r->request->get('body', '')),
            'image_url'          => trim((string)$r->request->get('image_url', '')) ?: null,
            'button_text'        => trim((string)$r->request->get('button_text', 'Subscribe')),
            'button_url'         => trim((string)$r->request->get('button_url', '')) ?: null,
            'secondary_btn_text' => trim((string)$r->request->get('secondary_btn_text', '')) ?: null,
            'secondary_btn_url'  => trim((string)$r->request->get('secondary_btn_url', '')) ?: null,
            'bg_color'           => trim((string)$r->request->get('bg_color', '#ffffff')),
            'text_color'         => trim((string)$r->request->get('text_color', '#1a1a1a')),
            'btn_bg_color'       => trim((string)$r->request->get('btn_bg_color', '#cc0000')),
            'btn_text_color'     => trim((string)$r->request->get('btn_text_color', '#ffffff')),
            'overlay_opacity'    => max(0, min(1, (float)($r->request->get('overlay_opacity', 0.5)))),
            'custom_css'         => trim((string)$r->request->get('custom_css', '')) ?: null,
            'trigger_type'       => $this->enum($r, 'trigger_type', Popup::TRIGGERS, 'page_load'),
            'trigger_value'      => trim((string)$r->request->get('trigger_value', '')) ?: null,
            'show_delay'         => max(0, (int)$r->request->get('show_delay', 0)),
            'close_delay'        => max(0, (int)$r->request->get('close_delay', 0)),
            'frequency'          => $this->enum($r, 'frequency', Popup::FREQUENCIES, 'once_session'),
            'frequency_days'     => ((int)$r->request->get('frequency_days', 0)) ?: null,
            'priority'           => max(1, (int)$r->request->get('priority', 10)),
            'target_audience'    => trim((string)$r->request->get('target_audience', 'all')),
            'target_device'      => trim((string)$r->request->get('target_device', 'all')),
            'target_pages'       => trim((string)$r->request->get('target_pages', '')) ?: null,
            'target_categories'  => trim((string)$r->request->get('target_categories', '')) ?: null,
            'status'             => $this->enum($r, 'status', Popup::STATUSES, 'draft'),
            'start_date'         => $this->datetime($r, 'start_date'),
            'end_date'           => $this->datetime($r, 'end_date'),
            'has_email_field'    => (bool)$r->request->get('has_email_field', false),
            'subscriber_source'  => trim((string)$r->request->get('subscriber_source', '')) ?: null,
            'sponsor_name'       => trim((string)$r->request->get('sponsor_name', '')) ?: null,
            'click_url'          => trim((string)$r->request->get('click_url', '')) ?: null,
            'nofollow'           => (bool)$r->request->get('nofollow', true),
            'promo_code'         => trim((string)$r->request->get('promo_code', '')) ?: null,
            'campaign_name'      => trim((string)$r->request->get('campaign_name', '')) ?: null,
        ]);

        Flash::set('success', 'Popup updated.');
        return $this->redirect('/admin/popups');
    }

    // ── Delete ──────────────────────────────────────────────────

    public function destroy(string $id): Response
    {
        Popup::delete($id);
        Flash::set('success', 'Popup deleted.');
        return $this->redirect('/admin/popups');
    }

    // ── Toggle active/inactive ──────────────────────────────────

    public function toggle(string $id): Response
    {
        Popup::toggleStatus($id);
        Flash::set('success', 'Popup status toggled.');
        return $this->redirect('/admin/popups');
    }

    // ── Duplicate ───────────────────────────────────────────────

    public function duplicateAction(string $id): Response
    {
        $copy = Popup::duplicate($id);
        if ($copy) {
            Flash::set('success', 'Popup duplicated as draft.');
        } else {
            Flash::set('error', 'Popup not found.');
        }
        return $this->redirect('/admin/popups');
    }

    // ── API: Active popups for frontend ─────────────────────────

    public function apiActive(): Response
    {
        $popups = Popup::getActiveForFrontend();
        return $this->json($popups);
    }

    // ── API: Track popup event ──────────────────────────────────

    public function apiTrack(): Response
    {
        $r = Request::createFromGlobals();
        $body = json_decode($r->getContent() ?: '{}', true) ?: [];

        $popupId   = trim($body['popup_id'] ?? '');
        $eventType = trim($body['event'] ?? '');

        if ($popupId === '' || !in_array($eventType, ['impression','click','conversion','close','email_submit'])) {
            return $this->json(['ok' => false, 'error' => 'Invalid params'], 400);
        }

        // Verify popup exists
        $popup = Popup::find($popupId);
        if (!$popup) {
            return $this->json(['ok' => false, 'error' => 'Popup not found'], 404);
        }

        // Insert event
        $pdo = DB::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO popup_events (popup_id, event_type, visitor_ip, user_agent, page_url)
             VALUES (:pid, :evt, :ip, :ua, :url)'
        );
        $stmt->execute([
            ':pid' => $popupId,
            ':evt' => $eventType,
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ':url' => substr($body['page_url'] ?? '', 0, 2000),
        ]);

        // Update counter
        $statMap = [
            'impression' => 'impressions',
            'click'      => 'clicks',
            'conversion' => 'conversions',
            'close'      => 'closes',
            'email_submit' => 'conversions',
        ];
        if (isset($statMap[$eventType])) {
            Popup::incrementStat($popupId, $statMap[$eventType]);
        }

        // Handle email submission
        if ($eventType === 'email_submit' && !empty($body['email'])) {
            $email = filter_var(trim($body['email']), FILTER_VALIDATE_EMAIL);
            if ($email) {
                try {
                    \App\Models\Subscriber::subscribe($email, $body['name'] ?? null);
                    // Update source if popup has subscriber_source
                    if (!empty($popup['subscriber_source'])) {
                        $pdo->prepare(
                            "UPDATE newsletter_subscribers SET source = :src WHERE email = :email"
                        )->execute([':src' => $popup['subscriber_source'], ':email' => $email]);
                    }
                } catch (\Throwable) {} // duplicate email — fine
            }
        }

        return $this->json(['ok' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    // ── Popup Analytics Dashboard ──────────────────────────────

    public function analytics(): Response
    {
        $pdo = DB::pdo();

        // ── Period / date range resolution ───────────────────────
        $period   = trim($_GET['period'] ?? '30');
        $fromDate = trim($_GET['from'] ?? '');
        $toDate   = trim($_GET['to'] ?? '');

        if ($fromDate !== '' && $toDate !== '') {
            $period = 'custom';
        } else {
            $map = [
                'today'     => [date('Y-m-d'), date('Y-m-d')],
                'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
                '7'         => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
                '30'        => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
                '90'        => [date('Y-m-d', strtotime('-89 days')), date('Y-m-d')],
            ];
            if (!isset($map[$period])) $period = '30';
            [$fromDate, $toDate] = $map[$period];
        }

        // Previous period (same length, offset backwards) for % change
        $days     = max(1, (int)round((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1);
        $prevFrom = date('Y-m-d', strtotime($fromDate) - $days * 86400);
        $prevTo   = date('Y-m-d', strtotime($fromDate) - 86400);

        // ── Chart data (daily + per-popup) ───────────────────────
        $chartData = Popup::analyticsForPeriod('', $fromDate, $toDate);

        // ── Heatmap (dow × hour) ─────────────────────────────────
        $heatmap = [];
        try {
            $stmt = $pdo->prepare("
                SELECT EXTRACT(DOW FROM created_at)::int AS dow,
                       EXTRACT(HOUR FROM created_at)::int AS hour,
                       COUNT(*) AS cnt
                FROM popup_events
                WHERE created_at >= :from AND created_at <= :to
                GROUP BY dow, hour
                ORDER BY dow, hour
            ");
            $stmt->execute([':from' => $fromDate, ':to' => $toDate . ' 23:59:59']);
            $heatmap = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        // ── KPI summary with period-over-period change ───────────
        $kpiQuery = function (string $from, string $to) use ($pdo): array {
            try {
                $stmt = $pdo->prepare("
                    SELECT event_type, COUNT(*) AS cnt
                    FROM popup_events
                    WHERE created_at >= :from AND created_at <= :to
                    GROUP BY event_type
                ");
                $stmt->execute([':from' => $from, ':to' => $to . ' 23:59:59']);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $map = [];
                foreach ($rows as $r) $map[$r['event_type']] = (int)$r['cnt'];
                return $map;
            } catch (\Throwable) {
                return [];
            }
        };

        $current  = $kpiQuery($fromDate, $toDate);
        $previous = $kpiQuery($prevFrom, $prevTo);

        $summary = [];
        foreach (['impressions' => 'impression', 'clicks' => 'click', 'conversions' => 'conversion', 'closes' => 'close'] as $key => $evType) {
            $cur  = $current[$evType] ?? 0;
            $prev = $previous[$evType] ?? 0;
            $change = $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : ($cur > 0 ? 100 : 0);
            $summary[$key] = ['value' => $cur, 'change' => $change];
        }

        // ── Breakdown by dimension ───────────────────────────────
        $breakdown = function (string $dimension) use ($pdo, $fromDate, $toDate): array {
            try {
                $stmt = $pdo->prepare("
                    SELECT p.{$dimension} AS dimension,
                           SUM(CASE WHEN pe.event_type='impression' THEN 1 ELSE 0 END) AS impressions,
                           SUM(CASE WHEN pe.event_type='conversion' THEN 1 ELSE 0 END) AS conversions,
                           CASE WHEN SUM(CASE WHEN pe.event_type='impression' THEN 1 ELSE 0 END) > 0
                                THEN ROUND(SUM(CASE WHEN pe.event_type='conversion' THEN 1 ELSE 0 END)::numeric /
                                     SUM(CASE WHEN pe.event_type='impression' THEN 1 ELSE 0 END) * 100, 1)
                                ELSE 0 END AS conversion_rate
                    FROM popup_events pe
                    JOIN popups p ON p.id = pe.popup_id
                    WHERE pe.created_at >= :from AND pe.created_at <= :to
                    GROUP BY p.{$dimension}
                    ORDER BY impressions DESC
                ");
                $stmt->execute([':from' => $fromDate, ':to' => $toDate . ' 23:59:59']);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                return [];
            }
        };

        $byType     = $breakdown('popup_type');
        $byPosition = $breakdown('position');
        $byDevice   = $breakdown('target_device');

        // ── Subscriber acquisition sources ───────────────────────
        $sources = [];
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(NULLIF(source,''), 'website') AS source, COUNT(*) AS cnt
                FROM newsletter_subscribers
                WHERE created_at >= :from AND created_at <= :to
                GROUP BY source ORDER BY cnt DESC
            ");
            $stmt->execute([':from' => $fromDate, ':to' => $toDate . ' 23:59:59']);
            $sources = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        // ── All popups (for compare dropdowns) ───────────────────
        $allPopups = [];
        try {
            $allPopups = $pdo->query("SELECT id, name FROM popups ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        // ── Compare side-by-side ─────────────────────────────────
        $compareA    = trim($_GET['compare_a'] ?? '');
        $compareB    = trim($_GET['compare_b'] ?? '');
        $comparison  = null;

        if ($compareA !== '' && $compareB !== '') {
            $comparison = ['a' => null, 'b' => null];
            foreach (['a' => $compareA, 'b' => $compareB] as $side => $pid) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT id, name, popup_type, banner_style, position,
                               impressions, clicks, conversions, closes, status
                        FROM popups WHERE id = :id
                    ");
                    $stmt->execute([':id' => $pid]);
                    $popup = $stmt->fetch(\PDO::FETCH_ASSOC);

                    $daily = [];
                    if ($popup) {
                        $dStmt = $pdo->prepare("
                            SELECT DATE(created_at) AS day, event_type, COUNT(*) AS cnt
                            FROM popup_events
                            WHERE popup_id = :pid AND created_at >= :from AND created_at <= :to
                            GROUP BY day, event_type ORDER BY day
                        ");
                        $dStmt->execute([':pid' => $pid, ':from' => $fromDate, ':to' => $toDate . ' 23:59:59']);
                        $daily = $dStmt->fetchAll(\PDO::FETCH_ASSOC);
                    }

                    $comparison[$side] = ['popup' => $popup, 'daily' => $daily];
                } catch (\Throwable) {
                    $comparison[$side] = ['popup' => null, 'daily' => []];
                }
            }
        }

        return $this->render('admin/popups/analytics', [
            'period'     => $period,
            'fromDate'   => $fromDate,
            'toDate'     => $toDate,
            'chartData'  => $chartData,
            'heatmap'    => $heatmap,
            'summary'    => $summary,
            'byType'     => $byType,
            'byPosition' => $byPosition,
            'byDevice'   => $byDevice,
            'sources'    => $sources,
            'allPopups'  => $allPopups,
            'compareA'   => $compareA,
            'compareB'   => $compareB,
            'comparison' => $comparison,
        ]);
    }

    // ── Popup Analytics CSV Export ───────────────────────────────

    public function analyticsExport(): Response
    {
        $pdo = DB::pdo();

        $fromDate = trim($_GET['from'] ?? date('Y-m-d', strtotime('-29 days')));
        $toDate   = trim($_GET['to'] ?? date('Y-m-d'));

        $rows = [];
        try {
            $stmt = $pdo->prepare("
                SELECT p.name AS popup_name, p.popup_type, p.status,
                       pe.event_type, pe.visitor_ip, pe.page_url,
                       pe.created_at
                FROM popup_events pe
                JOIN popups p ON p.id = pe.popup_id
                WHERE pe.created_at >= :from AND pe.created_at <= :to
                ORDER BY pe.created_at DESC
            ");
            $stmt->execute([':from' => $fromDate, ':to' => $toDate . ' 23:59:59']);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        $csv = "Popup,Type,Status,Event,IP,Page,Timestamp\n";
        foreach ($rows as $r) {
            $csv .= implode(',', [
                '"' . str_replace('"', '""', $r['popup_name']) . '"',
                $r['popup_type'],
                $r['status'],
                $r['event_type'],
                $r['visitor_ip'] ?? '',
                '"' . str_replace('"', '""', $r['page_url'] ?? '') . '"',
                $r['created_at'],
            ]) . "\n";
        }

        $response = new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="popup-analytics-' . $fromDate . '-to-' . $toDate . '.csv"',
        ]);
        return $response;
    }

    private function enum(Request $r, string $field, array $valid, string $default): string
    {
        $val = (string)$r->request->get($field, $default);
        return in_array($val, $valid, true) ? $val : $default;
    }

    private function datetime(Request $r, string $field): ?string
    {
        $val = trim((string)$r->request->get($field, ''));
        if ($val === '') return null;
        $ts = strtotime($val);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}