<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\DB;

final class Popup extends BaseModel
{
    protected static string $table = 'popups';

    // ── Valid enum values ────────────────────────────────────────

    public const TYPES = [
        'cookie_consent','newsletter_signup','breaking_news','welcome_back',
        'exit_intent','ad_popup','promotion','pwa_install','paywall','survey',
    ];

    public const STYLES = [
        'minimal_bar','card_modal','split_image','fullscreen','slide_in','floating',
    ];

    public const STATUSES = ['draft','active','inactive','scheduled','expired'];

    public const TRIGGERS = [
        'page_load','scroll_percent','exit_intent','manual','page_views','time_delay',
    ];

    public const FREQUENCIES = [
        'once_ever','once_session','daily','weekly','monthly','custom_days','every_visit',
    ];

    public const POSITIONS = [
        'center','top','bottom','top-left','top-right','bottom-left','bottom-right',
    ];

    // ── Friendly labels ─────────────────────────────────────────

    public const TYPE_LABELS = [
        'cookie_consent'     => 'Cookie Consent',
        'newsletter_signup'  => 'Newsletter Signup',
        'breaking_news'      => 'Breaking News Alert',
        'welcome_back'       => 'Welcome Back',
        'exit_intent'        => 'Exit Intent',
        'ad_popup'           => 'Advertisement',
        'promotion'          => 'Promotion / Campaign',
        'pwa_install'        => 'App Download / PWA',
        'paywall'            => 'Paywall / Metered',
        'survey'             => 'Survey / Feedback',
    ];

    public const STYLE_LABELS = [
        'minimal_bar'   => 'Minimal Bar',
        'card_modal'    => 'Card Modal',
        'split_image'   => 'Split Image Modal',
        'fullscreen'    => 'Full-Screen Overlay',
        'slide_in'      => 'Slide-In Panel',
        'floating'      => 'Floating Banner',
    ];

    // ── Admin queries ───────────────────────────────────────────

    public static function adminList(int $page = 1, int $perPage = 20, string $status = '', string $type = ''): array
    {
        $pdo = DB::pdo();
        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }
        if ($type !== '') {
            $where[] = 'p.popup_type = :type';
            $params[':type'] = $type;
        }

        $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM popups p {$whereSQL}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT p.*, u.username AS created_by_name
                FROM popups p
                LEFT JOIN users u ON p.created_by = u.id
                {$whereSQL}
                ORDER BY p.priority ASC, p.created_at DESC
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    public static function store(array $data): ?array
    {
        $pdo = DB::pdo();
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);

        $sql = 'INSERT INTO popups (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ') RETURNING *';
        $stmt = $pdo->prepare($sql);

        foreach ($data as $key => $val) {
            if (is_bool($val)) {
                $stmt->bindValue(':' . $key, $val, \PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue(':' . $key, $val);
            }
        }

        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function updatePopup(string $id, array $data): void
    {
        $pdo = DB::pdo();
        $sets = [];
        foreach ($data as $key => $val) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                throw new \InvalidArgumentException("Invalid column name: {$key}");
            }
            $sets[] = "{$key} = :{$key}";
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $sets[] = "updated_at = :updated_at";
        $data['id'] = $id;

        $sql = 'UPDATE popups SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);

        foreach ($data as $key => $val) {
            if (is_bool($val)) {
                $stmt->bindValue(':' . $key, $val, \PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue(':' . $key, $val);
            }
        }

        $stmt->execute();
    }

    public static function delete(string $id): bool
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM popups WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public static function toggleStatus(string $id): void
    {
        $pdo = DB::pdo();
        $pdo->prepare(
            "UPDATE popups SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END, updated_at = NOW() WHERE id = :id"
        )->execute([':id' => $id]);
    }

    public static function duplicate(string $id): ?array
    {
        $original = self::find($id);
        if (!$original) return null;

        unset($original['id'], $original['created_at'], $original['updated_at']);
        $original['name'] = $original['name'] . ' (Copy)';
        $original['slug'] = $original['slug'] . '-copy-' . substr(md5((string)time()), 0, 6);
        $original['status'] = 'draft';
        $original['impressions'] = 0;
        $original['clicks'] = 0;
        $original['conversions'] = 0;
        $original['closes'] = 0;

        return self::store($original);
    }

    // ── Frontend: active popups for visitor ──────────────────────

    public static function getActiveForFrontend(): array
    {
        $pdo = DB::pdo();
        $sql = "SELECT id, name, slug, popup_type, banner_style, position,
                       title, body, image_url, button_text, button_url,
                       secondary_btn_text, secondary_btn_url,
                       bg_color, text_color, btn_bg_color, btn_text_color,
                       overlay_opacity, custom_css,
                       trigger_type, trigger_value, show_delay, close_delay,
                       frequency, frequency_days, priority, version,
                       target_audience, target_device, target_pages, target_categories,
                       has_email_field, sponsor_name, click_url, nofollow,
                       promo_code, campaign_name
                FROM popups
                WHERE status = 'active'
                  AND (start_date IS NULL OR start_date <= NOW())
                  AND (end_date IS NULL OR end_date >= NOW())
                ORDER BY priority ASC, created_at ASC";

        return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Analytics: counters ─────────────────────────────────────

    public static function incrementStat(string $id, string $field): void
    {
        if (!in_array($field, ['impressions', 'clicks', 'conversions', 'closes'])) return;
        $pdo = DB::pdo();
        $pdo->prepare("UPDATE popups SET {$field} = {$field} + 1 WHERE id = :id")
            ->execute([':id' => $id]);
    }

    // ── Analytics: dashboard widget stats ───────────────────────

    public static function dashboardStats(): array
    {
        $pdo = DB::pdo();

        $totals = $pdo->query(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'active') AS active,
                    COALESCE(SUM(impressions), 0) AS impressions,
                    COALESCE(SUM(conversions), 0) AS conversions
             FROM popups"
        )->fetch(\PDO::FETCH_ASSOC);

        $topPerformers = $pdo->query(
            "SELECT name, popup_type, impressions, conversions,
                    CASE WHEN impressions > 0
                         THEN ROUND(conversions::numeric / impressions * 100, 1)
                         ELSE 0 END AS conversion_rate
             FROM popups
             WHERE status = 'active' AND impressions > 0
             ORDER BY conversion_rate DESC
             LIMIT 5"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'totals' => $totals,
            'top_performers' => $topPerformers,
        ];
    }

    // ── Analytics: daily breakdown for chart ────────────────────

    public static function analyticsForPeriod(string $popupId = '', string $from = '', string $to = ''): array
    {
        $pdo = DB::pdo();
        $where = [];
        $params = [];

        if ($popupId !== '') {
            $where[] = 'pe.popup_id = :popup_id';
            $params[':popup_id'] = $popupId;
        }
        if ($from !== '') {
            $where[] = 'pe.created_at >= :from_date';
            $params[':from_date'] = $from;
        }
        if ($to !== '') {
            $where[] = 'pe.created_at <= :to_date';
            $params[':to_date'] = $to . ' 23:59:59';
        }

        $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Daily breakdown
        $sql = "SELECT DATE(pe.created_at) AS day,
                       pe.event_type,
                       COUNT(*) AS cnt
                FROM popup_events pe
                {$whereSQL}
                GROUP BY day, pe.event_type
                ORDER BY day ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $daily = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Per-popup summary
        $sql2 = "SELECT p.id, p.name, p.popup_type, p.banner_style, p.position, p.status,
                        p.impressions, p.clicks, p.conversions, p.closes,
                        CASE WHEN p.impressions > 0
                             THEN ROUND(p.conversions::numeric / p.impressions * 100, 1)
                             ELSE 0 END AS conversion_rate
                 FROM popups p
                 ORDER BY p.impressions DESC";
        $popups = $pdo->query($sql2)->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'daily' => $daily,
            'popups' => $popups,
        ];
    }

    // ── Analytics: summary cards with period comparison ──────────

    public static function analyticsSummary(string $from, string $to): array
    {
        $pdo = DB::pdo();

        // Current period totals
        $stmt = $pdo->prepare(
            "SELECT event_type, COUNT(*) AS cnt
             FROM popup_events
             WHERE created_at >= :from AND created_at <= :to
             GROUP BY event_type"
        );
        $stmt->execute([':from' => $from, ':to' => $to . ' 23:59:59']);
        $current = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $current[$row['event_type']] = (int)$row['cnt'];
        }

        // Previous period (same length, immediately before)
        $fromTs = strtotime($from);
        $toTs   = strtotime($to);
        $days   = max(1, (int)round(($toTs - $fromTs) / 86400));
        $prevFrom = date('Y-m-d', $fromTs - ($days * 86400));
        $prevTo   = date('Y-m-d', $fromTs - 86400);

        $stmt2 = $pdo->prepare(
            "SELECT event_type, COUNT(*) AS cnt
             FROM popup_events
             WHERE created_at >= :from AND created_at <= :to
             GROUP BY event_type"
        );
        $stmt2->execute([':from' => $prevFrom, ':to' => $prevTo . ' 23:59:59']);
        $previous = [];
        foreach ($stmt2->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $previous[$row['event_type']] = (int)$row['cnt'];
        }

        $calc = function (string $key) use ($current, $previous): array {
            $cur  = $current[$key] ?? 0;
            $prev = $previous[$key] ?? 0;
            $pct  = $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : ($cur > 0 ? 100.0 : 0.0);
            return ['value' => $cur, 'prev' => $prev, 'change' => $pct];
        };

        return [
            'impressions' => $calc('impression'),
            'clicks'      => $calc('click'),
            'conversions' => $calc('conversion'),
            'closes'      => $calc('close'),
        ];
    }

    // ── Analytics: hourly heatmap ───────────────────────────────

    public static function hourlyHeatmap(string $from, string $to): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare(
            "SELECT EXTRACT(DOW FROM created_at)::int AS dow,
                    EXTRACT(HOUR FROM created_at)::int AS hour,
                    event_type,
                    COUNT(*) AS cnt
             FROM popup_events
             WHERE created_at >= :from AND created_at <= :to
             GROUP BY dow, hour, event_type
             ORDER BY dow, hour"
        );
        $stmt->execute([':from' => $from, ':to' => $to . ' 23:59:59']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Analytics: breakdown by dimension ───────────────────────

    public static function breakdownBy(string $dimension, string $from, string $to): array
    {
        $allowed = ['popup_type', 'banner_style', 'position', 'target_device'];
        if (!in_array($dimension, $allowed, true)) return [];

        $pdo = DB::pdo();
        $stmt = $pdo->prepare(
            "SELECT p.{$dimension} AS dimension,
                    COUNT(*) FILTER (WHERE pe.event_type = 'impression') AS impressions,
                    COUNT(*) FILTER (WHERE pe.event_type = 'conversion') AS conversions,
                    COUNT(*) FILTER (WHERE pe.event_type = 'click') AS clicks,
                    CASE WHEN COUNT(*) FILTER (WHERE pe.event_type = 'impression') > 0
                         THEN ROUND(
                              COUNT(*) FILTER (WHERE pe.event_type = 'conversion')::numeric
                              / COUNT(*) FILTER (WHERE pe.event_type = 'impression') * 100, 1)
                         ELSE 0 END AS conversion_rate
             FROM popup_events pe
             JOIN popups p ON p.id = pe.popup_id
             WHERE pe.created_at >= :from AND pe.created_at <= :to
             GROUP BY p.{$dimension}
             ORDER BY impressions DESC"
        );
        $stmt->execute([':from' => $from, ':to' => $to . ' 23:59:59']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Analytics: subscriber source breakdown ──────────────────

    public static function subscriberSources(): array
    {
        $pdo = DB::pdo();
        return $pdo->query(
            "SELECT COALESCE(source, 'unknown') AS source, COUNT(*) AS cnt
             FROM newsletter_subscribers
             GROUP BY source
             ORDER BY cnt DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Analytics: CSV export rows ──────────────────────────────

    public static function csvExport(string $from, string $to): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare(
            "SELECT pe.id, p.name AS popup_name, p.popup_type, p.banner_style,
                    pe.event_type, pe.visitor_ip, pe.page_url,
                    pe.created_at
             FROM popup_events pe
             JOIN popups p ON p.id = pe.popup_id
             WHERE pe.created_at >= :from AND pe.created_at <= :to
             ORDER BY pe.created_at DESC
             LIMIT 50000"
        );
        $stmt->execute([':from' => $from, ':to' => $to . ' 23:59:59']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Analytics: compare two popups side-by-side ──────────────

    public static function comparePopups(string $idA, string $idB, string $from, string $to): array
    {
        $pdo = DB::pdo();

        $fetch = function (string $id) use ($pdo, $from, $to): array {
            $popup = self::find($id);
            if (!$popup) return [];

            $stmt = $pdo->prepare(
                "SELECT DATE(created_at) AS day, event_type, COUNT(*) AS cnt
                 FROM popup_events
                 WHERE popup_id = :id AND created_at >= :from AND created_at <= :to
                 GROUP BY day, event_type
                 ORDER BY day"
            );
            $stmt->execute([':id' => $id, ':from' => $from, ':to' => $to . ' 23:59:59']);

            return [
                'popup' => $popup,
                'daily' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            ];
        };

        return [
            'a' => $fetch($idA),
            'b' => $fetch($idB),
        ];
    }

    // ── All popups (for dropdown selectors) ─────────────────────

    public static function allForDropdown(): array
    {
        $pdo = DB::pdo();
        return $pdo->query("SELECT id, name, popup_type, status FROM popups ORDER BY name ASC")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }
}