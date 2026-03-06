<?php
/**
 * Popup Analytics Dashboard
 * /admin/popups/analytics
 */

$pageTitle = 'Popup Analytics';
$activeNav = 'popup-analytics';
ob_start();

$typeLabels  = \App\Models\Popup::TYPE_LABELS;
$styleLabels = \App\Models\Popup::STYLE_LABELS;

$dayMap = [];
foreach (($chartData['daily'] ?? []) as $row) {
    $day = $row['day'];
    if (!isset($dayMap[$day])) $dayMap[$day] = ['impression' => 0, 'click' => 0, 'conversion' => 0, 'close' => 0];
    $dayMap[$day][$row['event_type']] = (int)$row['cnt'];
}
$chartLabels      = array_keys($dayMap);
$chartImpressions = array_column(array_values($dayMap), 'impression');
$chartConversions = array_column(array_values($dayMap), 'conversion');
$chartClicks      = array_column(array_values($dayMap), 'click');

$heatGrid = [];
$heatMax  = 1;
foreach (($heatmap ?? []) as $row) {
    $k = $row['dow'] . '-' . $row['hour'];
    $heatGrid[$k] = ($heatGrid[$k] ?? 0) + (int)$row['cnt'];
    if ($heatGrid[$k] > $heatMax) $heatMax = $heatGrid[$k];
}
$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>

<style>
/* ── Analytics layout ─────────────────────────────────────────── */
.an-kpi-grid   { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:28px; }
.an-kpi        { background:var(--surface,#fff); border:1px solid var(--border,#e2e2e2); border-radius:14px; padding:22px 20px; position:relative; overflow:hidden; transition:box-shadow .15s; }
.an-kpi:hover  { box-shadow:0 6px 24px rgba(0,0,0,.08); }
.an-kpi::after { content:''; position:absolute; inset:0; border-radius:14px; opacity:0; transition:opacity .2s; }
.an-kpi-label  { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted,#888); margin-bottom:10px; }
.an-kpi-value  { font-size:30px; font-weight:800; color:var(--ink,#121212); line-height:1; letter-spacing:-1px; }
.an-kpi-change { font-size:12px; font-weight:600; margin-top:8px; display:flex; align-items:center; gap:4px; }
.an-kpi-change.up   { color:#15803d; }
.an-kpi-change.down { color:#c00; }
.an-kpi-change.flat { color:var(--muted,#888); }
.an-kpi-accent { position:absolute; bottom:0; left:0; right:0; height:3px; border-radius:0 0 14px 14px; }

/* period bar */
.an-period { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:28px; align-items:center; }
.an-period a { padding:7px 16px; border-radius:9px; border:1px solid var(--border,#e2e2e2); background:var(--surface,#fff); color:var(--ink,#333); font:inherit; font-size:13px; font-weight:500; cursor:pointer; text-decoration:none; transition:all .15s; }
.an-period a:hover { border-color:var(--accent,#c00); color:var(--accent,#c00); }
.an-period a.active { background:var(--accent,#c00); color:#fff; border-color:var(--accent,#c00); font-weight:700; }
.an-period-sep { color:var(--border,#ddd); font-size:18px; line-height:1; }

/* chart box */
.an-box { background:var(--surface,#fff); border:1px solid var(--border,#e2e2e2); border-radius:14px; padding:24px; margin-bottom:24px; }
.an-box-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; flex-wrap:wrap; gap:8px; }
.an-box-title { font-size:15px; font-weight:800; color:var(--ink,#111); margin:0; letter-spacing:-.2px; }
.an-box-sub { font-size:12px; color:var(--muted,#888); margin-top:2px; }

/* tables */
.an-table { width:100%; border-collapse:collapse; font-size:13px; }
.an-table th { text-align:left; padding:10px 14px; border-bottom:2px solid var(--border,#e2e2e2); font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--muted,#888); white-space:nowrap; }
.an-table td { padding:11px 14px; border-bottom:1px solid #f2f2f2; vertical-align:middle; }
.an-table tbody tr:last-child td { border-bottom:none; }
.an-table tbody tr { transition:background .1s; }
.an-table tbody tr:hover td { background:#fafafa; }
.an-table .right { text-align:right; }
.an-table .bold  { font-weight:700; }

/* popup status badge */
.an-status { display:inline-block; padding:2px 10px; border-radius:99px; font-size:11px; font-weight:700; letter-spacing:.03em; }
.an-status.active   { background:#dcfce7; color:#15803d; }
.an-status.inactive { background:#f3f4f6; color:#555; }

/* heatmap */
.an-heat-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.an-heat { display:grid; grid-template-columns:44px repeat(24,minmax(26px,1fr)); gap:3px; font-size:11px; min-width:700px; }
.an-heat .h-hour { text-align:center; font-size:10px; color:var(--muted,#999); font-weight:600; padding-bottom:2px; }
.an-heat .h-day  { font-weight:700; color:var(--muted,#888); font-size:11px; display:flex; align-items:center; }
.an-heat .h-cell { aspect-ratio:1; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:600; min-height:22px; cursor:default; transition:transform .1s; }
.an-heat .h-cell:hover { transform:scale(1.25); z-index:1; }

/* breakdown grid */
.an-breakdown { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; margin-bottom:24px; }

/* source tiles */
.an-sources { display:flex; flex-wrap:wrap; gap:14px; }
.an-source-tile { flex:1; min-width:130px; padding:18px; background:#fafafa; border:1px solid var(--border,#e2e2e2); border-radius:12px; text-align:center; transition:box-shadow .15s; }
.an-source-tile:hover { box-shadow:0 4px 14px rgba(0,0,0,.07); }
.an-source-tile .sv { font-size:26px; font-weight:800; color:var(--ink,#111); letter-spacing:-1px; }
.an-source-tile .sk { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--muted,#888); margin-top:4px; }
.an-source-tile .sp { font-size:11px; color:#aaa; margin-top:3px; }

/* compare */
.an-compare-panels { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.an-compare-panel { background:#fafafa; border:1px solid var(--border,#e2e2e2); border-radius:12px; padding:20px; }
.an-compare-panel h4 { margin:0 0 14px; font-size:15px; font-weight:800; }
.an-compare-stat { display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:13px; }
.an-compare-stat span { color:var(--muted,#888); }

/* custom range dropdown */
.an-custom { position:relative; }
.an-custom summary { padding:7px 16px; border-radius:9px; border:1px solid var(--border,#e2e2e2); background:var(--surface,#fff); font:inherit; font-size:13px; font-weight:500; cursor:pointer; list-style:none; color:var(--ink,#333); display:flex; align-items:center; gap:6px; transition:border-color .15s; }
.an-custom summary:hover { border-color:var(--accent,#c00); }
.an-custom[open] summary { border-color:var(--accent,#c00); }
.an-custom-panel { position:absolute; top:calc(100% + 6px); left:0; z-index:50; background:var(--surface,#fff); border:1px solid var(--border,#e2e2e2); border-radius:14px; padding:18px; box-shadow:0 8px 28px rgba(0,0,0,.12); min-width:260px; }
.an-custom-panel label { display:block; font-size:12px; font-weight:700; margin-bottom:5px; color:var(--ink,#333); }
.an-custom-panel input[type=date] { width:100%; padding:8px 10px; border:1px solid var(--border,#e2e2e2); border-radius:8px; font:inherit; font-size:13px; margin-bottom:12px; outline:none; transition:border-color .15s; }
.an-custom-panel input[type=date]:focus { border-color:var(--accent,#c00); }
.an-custom-panel .an-apply { width:100%; padding:9px; background:var(--accent,#c00); color:#fff; border:none; border-radius:9px; font:inherit; font-size:13px; font-weight:700; cursor:pointer; transition:opacity .15s; }
.an-custom-panel .an-apply:hover { opacity:.88; }

/* empty state */
.an-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px 20px; color:var(--muted,#aaa); gap:10px; }
.an-empty svg { opacity:.35; }
.an-empty p { font-size:14px; margin:0; }

@media(max-width:768px){
  .an-compare-panels { grid-template-columns:1fr; }
  .an-breakdown { grid-template-columns:1fr; }
}
@media(max-width:480px){
  .an-kpi-value { font-size:24px; }
}
</style>

<!-- ── Page header ─────────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1>Popup Analytics</h1>
    <p class="sub"><?= htmlspecialchars($fromDate) ?> &rarr; <?= htmlspecialchars($toDate) ?></p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="/admin/popups" class="btn light">&larr; Back to Popups</a>
    <a href="/admin/popups/analytics/export?from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>" class="btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </a>
  </div>
</div>

<!-- ── Period selector ─────────────────────────────────────────── -->
<div class="an-period">
  <?php foreach (['today' => 'Today', 'yesterday' => 'Yesterday', '7' => '7 Days', '30' => '30 Days', '90' => '90 Days'] as $key => $label): ?>
    <a href="/admin/popups/analytics?period=<?= $key ?>" class="<?= $period === (string)$key ? 'active' : '' ?>"><?= $label ?></a>
  <?php endforeach; ?>

  <span class="an-period-sep">|</span>

  <details class="an-custom">
    <summary>
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Custom Range
    </summary>
    <div class="an-custom-panel">
      <label>From</label>
      <input type="date" id="customFrom" value="<?= htmlspecialchars($fromDate) ?>" />
      <label>To</label>
      <input type="date" id="customTo"   value="<?= htmlspecialchars($toDate) ?>" />
      <button class="an-apply" onclick="location.href='/admin/popups/analytics?from='+document.getElementById('customFrom').value+'&to='+document.getElementById('customTo').value">Apply</button>
    </div>
  </details>
</div>

<!-- ── KPI cards ───────────────────────────────────────────────── -->
<?php
$kpiDefs = [
    'impressions'  => ['label' => 'Impressions',  'color' => '#3b82f6'],
    'clicks'       => ['label' => 'Clicks',        'color' => '#f59e0b'],
    'conversions'  => ['label' => 'Conversions',   'color' => '#15803d'],
    'closes'       => ['label' => 'Closes',        'color' => '#6366f1'],
];
?>
<div class="an-kpi-grid">
  <?php foreach ($kpiDefs as $key => $def):
      $s     = $summary[$key] ?? ['value' => 0, 'change' => 0];
      $cls   = $s['change'] > 0 ? 'up' : ($s['change'] < 0 ? 'down' : 'flat');
      $arrow = $s['change'] > 0 ? '&#9650;' : ($s['change'] < 0 ? '&#9660;' : '&ndash;');
  ?>
    <div class="an-kpi">
      <div class="an-kpi-label"><?= $def['label'] ?></div>
      <div class="an-kpi-value"><?= number_format((int)$s['value']) ?></div>
      <div class="an-kpi-change <?= $cls ?>">
        <span><?= $arrow ?></span>
        <span><?= abs((float)$s['change']) ?>% vs prev period</span>
      </div>
      <div class="an-kpi-accent" style="background:<?= $def['color'] ?>"></div>
    </div>
  <?php endforeach;

  $imp  = (int)($summary['impressions']['value'] ?? 0);
  $conv = (int)($summary['conversions']['value'] ?? 0);
  $rate = $imp > 0 ? round($conv / $imp * 100, 1) : 0;
  ?>
  <div class="an-kpi">
    <div class="an-kpi-label">Conversion Rate</div>
    <div class="an-kpi-value"><?= $rate ?>%</div>
    <div class="an-kpi-change flat"><?= number_format($conv) ?> / <?= number_format($imp) ?></div>
    <div class="an-kpi-accent" style="background:#c00"></div>
  </div>
</div>

<!-- ── Main trend chart ────────────────────────────────────────── -->
<div class="an-box">
  <div class="an-box-header">
    <div>
      <h3 class="an-box-title">Impressions &amp; Conversions Over Time</h3>
      <div class="an-box-sub">Daily trend across the selected period</div>
    </div>
  </div>
  <canvas id="mainChart" height="75"></canvas>
</div>

<!-- ── Per-popup performance table ────────────────────────────── -->
<div class="an-box">
  <div class="an-box-header">
    <div>
      <h3 class="an-box-title">Per-Popup Performance</h3>
      <div class="an-box-sub">All popups ranked by impressions</div>
    </div>
  </div>
  <?php if (!empty($chartData['popups'])): ?>
  <div style="overflow-x:auto">
    <table class="an-table">
      <thead>
        <tr>
          <th>Popup</th>
          <th>Type</th>
          <th>Status</th>
          <th class="right">Impressions</th>
          <th class="right">Clicks</th>
          <th class="right">Conversions</th>
          <th class="right">Closes</th>
          <th class="right">Conv. Rate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($chartData['popups'] as $p): ?>
        <tr>
          <td class="bold"><?= htmlspecialchars($p['name']) ?></td>
          <td><?= $typeLabels[$p['popup_type']] ?? $p['popup_type'] ?></td>
          <td>
            <span class="an-status <?= $p['status'] === 'active' ? 'active' : 'inactive' ?>">
              <?= ucfirst($p['status']) ?>
            </span>
          </td>
          <td class="right"><?= number_format((int)$p['impressions']) ?></td>
          <td class="right"><?= number_format((int)$p['clicks']) ?></td>
          <td class="right"><?= number_format((int)$p['conversions']) ?></td>
          <td class="right"><?= number_format((int)$p['closes']) ?></td>
          <td class="right bold"><?= $p['conversion_rate'] ?>%</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="an-empty">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      <p>No popup data yet for this period.</p>
    </div>
  <?php endif; ?>
</div>

<!-- ── Breakdown grid ──────────────────────────────────────────── -->
<div class="an-breakdown">
  <?php foreach ([
    ['By Popup Type',    $byType,     $typeLabels],
    ['By Position',      $byPosition, []],
    ['By Target Device', $byDevice,   []],
  ] as [$bTitle, $bData, $bLabels]): ?>
    <div class="an-box" style="margin-bottom:0">
      <div class="an-box-header"><h3 class="an-box-title"><?= $bTitle ?></h3></div>
      <?php if (!empty($bData)): ?>
        <table class="an-table">
          <thead>
            <tr>
              <th><?= $bTitle ?></th>
              <th class="right">Impr.</th>
              <th class="right">Conv.</th>
              <th class="right">Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bData as $row): ?>
            <tr>
              <td class="bold"><?= htmlspecialchars($bLabels[$row['dimension']] ?? ucwords(str_replace('_', ' ', $row['dimension']))) ?></td>
              <td class="right"><?= number_format((int)$row['impressions']) ?></td>
              <td class="right"><?= number_format((int)$row['conversions']) ?></td>
              <td class="right bold"><?= $row['conversion_rate'] ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="an-empty"><p>No data for this period.</p></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- ── Subscriber acquisition ─────────────────────────────────── -->
<div class="an-box">
  <div class="an-box-header">
    <div>
      <h3 class="an-box-title">Subscriber Acquisition Sources</h3>
      <div class="an-box-sub">Where new subscribers came from during this period</div>
    </div>
  </div>
  <?php if (!empty($sources)):
    $totalSubs = array_sum(array_column($sources, 'cnt'));
  ?>
    <div class="an-sources">
      <?php foreach ($sources as $src):
          $pct = $totalSubs > 0 ? round((int)$src['cnt'] / $totalSubs * 100, 1) : 0;
      ?>
        <div class="an-source-tile">
          <div class="sv"><?= number_format((int)$src['cnt']) ?></div>
          <div class="sk"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $src['source']))) ?></div>
          <div class="sp"><?= $pct ?>%</div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="an-empty"><p>No subscriber data yet.</p></div>
  <?php endif; ?>
</div>

<!-- ── Heatmap ─────────────────────────────────────────────────── -->
<div class="an-box">
  <div class="an-box-header">
    <div>
      <h3 class="an-box-title">Hourly Activity Heatmap</h3>
      <div class="an-box-sub">Total popup events by day of week and hour &mdash; darker cells = more activity</div>
    </div>
  </div>
  <div class="an-heat-wrap">
    <div class="an-heat">
      <div></div>
      <?php for ($h = 0; $h < 24; $h++): ?>
        <div class="h-hour"><?= $h ?></div>
      <?php endfor; ?>
      <?php for ($d = 0; $d < 7; $d++): ?>
        <div class="h-day"><?= $dayNames[$d] ?></div>
        <?php for ($h = 0; $h < 24; $h++):
            $val       = $heatGrid[$d . '-' . $h] ?? 0;
            $intensity = $heatMax > 0 ? $val / $heatMax : 0;
            if ($val === 0)           { $bg = '#f5f5f5'; $tc = 'transparent'; }
            elseif ($intensity < .25) { $bg = 'rgba(59,130,246,.15)'; $tc = '#3b82f6'; }
            elseif ($intensity < .50) { $bg = 'rgba(59,130,246,.35)'; $tc = '#2563eb'; }
            elseif ($intensity < .75) { $bg = 'rgba(59,130,246,.60)'; $tc = '#fff'; }
            else                      { $bg = 'rgba(59,130,246,.88)'; $tc = '#fff'; }
        ?>
          <div class="h-cell" style="background:<?= $bg ?>;color:<?= $tc ?>" title="<?= $dayNames[$d] ?> <?= $h ?>:00 &ndash; <?= $val ?> events">
            <?= $val > 0 ? $val : '' ?>
          </div>
        <?php endfor; ?>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- ── Compare popups ─────────────────────────────────────────── -->
<div class="an-box">
  <div class="an-box-header">
    <div>
      <h3 class="an-box-title">Compare Popups Side-by-Side</h3>
      <div class="an-box-sub">Select two popups to compare their performance metrics</div>
    </div>
  </div>

  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;align-items:flex-end">
    <div>
      <label style="display:block;font-size:12px;font-weight:700;margin-bottom:5px;color:var(--ink,#333)">Popup A</label>
      <select id="cmpA" style="padding:9px 12px;border:1px solid var(--border,#e2e2e2);border-radius:9px;font:inherit;font-size:13px;min-width:220px;outline:none">
        <option value="">Select popup&hellip;</option>
        <?php foreach ($allPopups as $ap): ?>
          <option value="<?= htmlspecialchars($ap['id']) ?>" <?= $compareA === $ap['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ap['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:700;margin-bottom:5px;color:var(--ink,#333)">Popup B</label>
      <select id="cmpB" style="padding:9px 12px;border:1px solid var(--border,#e2e2e2);border-radius:9px;font:inherit;font-size:13px;min-width:220px;outline:none">
        <option value="">Select popup&hellip;</option>
        <?php foreach ($allPopups as $ap): ?>
          <option value="<?= htmlspecialchars($ap['id']) ?>" <?= $compareB === $ap['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ap['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button
      onclick="var a=document.getElementById('cmpA').value,b=document.getElementById('cmpB').value;if(a&&b)location.href='/admin/popups/analytics?from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&compare_a='+a+'&compare_b='+b;"
      class="btn"
      style="height:40px"
    >Compare</button>
  </div>

  <?php if ($comparison && !empty($comparison['a']['popup']) && !empty($comparison['b']['popup'])): ?>
    <div class="an-compare-panels">
      <?php foreach (['a', 'b'] as $side):
          $cp     = $comparison[$side]['popup'];
          $cvRate = (int)$cp['impressions'] > 0 ? round((int)$cp['conversions'] / (int)$cp['impressions'] * 100, 1) : 0;
      ?>
        <div class="an-compare-panel">
          <h4><?= htmlspecialchars($cp['name']) ?></h4>
          <div class="an-compare-stat">
            <div><span>Type:</span> <?= $typeLabels[$cp['popup_type']] ?? $cp['popup_type'] ?></div>
            <div><span>Style:</span> <?= $styleLabels[$cp['banner_style']] ?? $cp['banner_style'] ?></div>
            <div><span>Impressions:</span> <strong><?= number_format((int)$cp['impressions']) ?></strong></div>
            <div><span>Clicks:</span> <strong><?= number_format((int)$cp['clicks']) ?></strong></div>
            <div><span>Conversions:</span> <strong><?= number_format((int)$cp['conversions']) ?></strong></div>
            <div><span>Conv. Rate:</span> <strong><?= $cvRate ?>%</strong></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <canvas id="compareChart" height="65"></canvas>
  <?php else: ?>
    <div class="an-empty">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <p>Select two popups above and click Compare.</p>
    </div>
  <?php endif; ?>
</div>

<!-- ── Chart.js ───────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  // shared defaults
  Chart.defaults.font.family = "'Libre Franklin', system-ui, sans-serif";
  Chart.defaults.font.size   = 12;
  Chart.defaults.color       = '#888';

  /* ── Main trend chart ──────────────────────────────────────── */
  var mainCtx = document.getElementById('mainChart');
  if (mainCtx) {
    new Chart(mainCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
          {
            label: 'Impressions',
            data: <?= json_encode($chartImpressions) ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.07)',
            fill: true, tension: 0.35, pointRadius: 3, borderWidth: 2
          },
          {
            label: 'Conversions',
            data: <?= json_encode($chartConversions) ?>,
            borderColor: '#15803d',
            backgroundColor: 'rgba(21,128,61,0.07)',
            fill: true, tension: 0.35, pointRadius: 3, borderWidth: 2
          },
          {
            label: 'Clicks',
            data: <?= json_encode($chartClicks) ?>,
            borderColor: '#f59e0b',
            backgroundColor: 'transparent',
            fill: false, tension: 0.35, pointRadius: 2, borderWidth: 1.5,
            borderDash: [5, 4]
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        scales: {
          x: { grid: { color: 'rgba(0,0,0,.04)' } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' } }
        },
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 18 } } }
      }
    });
  }

  /* ── Compare chart ─────────────────────────────────────────── */
  <?php if ($comparison && !empty($comparison['a']['popup']) && !empty($comparison['b']['popup'])): ?>
  var cmpCtx  = document.getElementById('compareChart');
  if (cmpCtx) {
    var cmpData = <?= json_encode($comparison) ?>;
    var allDays = {};
    ['a', 'b'].forEach(function (side) {
      (cmpData[side].daily || []).forEach(function (row) {
        if (!allDays[row.day]) allDays[row.day] = { a_imp: 0, a_conv: 0, b_imp: 0, b_conv: 0 };
        if (row.event_type === 'impression') allDays[row.day][side + '_imp']  = +row.cnt;
        if (row.event_type === 'conversion') allDays[row.day][side + '_conv'] = +row.cnt;
      });
    });
    var days = Object.keys(allDays).sort();
    new Chart(cmpCtx, {
      type: 'line',
      data: {
        labels: days,
        datasets: [
          { label: cmpData.a.popup.name + ' \u2013 Impr.',  data: days.map(function(d){return allDays[d].a_imp}),  borderColor: '#3b82f6', tension: 0.35, pointRadius: 2, borderWidth: 2 },
          { label: cmpData.a.popup.name + ' \u2013 Conv.',  data: days.map(function(d){return allDays[d].a_conv}), borderColor: '#3b82f6', borderDash: [5,4], tension: 0.35, pointRadius: 2, borderWidth: 1.5 },
          { label: cmpData.b.popup.name + ' \u2013 Impr.',  data: days.map(function(d){return allDays[d].b_imp}),  borderColor: '#f59e0b', tension: 0.35, pointRadius: 2, borderWidth: 2 },
          { label: cmpData.b.popup.name + ' \u2013 Conv.',  data: days.map(function(d){return allDays[d].b_conv}), borderColor: '#f59e0b', borderDash: [5,4], tension: 0.35, pointRadius: 2, borderWidth: 1.5 }
        ]
      },
      options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        scales: {
          x: { grid: { color: 'rgba(0,0,0,.04)' } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' } }
        },
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 16 } } }
      }
    });
  }
  <?php endif; ?>
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
