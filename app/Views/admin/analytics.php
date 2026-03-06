<?php
/**
 * Admin Analytics Overview
 * Route: GET /admin/analytics
 */
$pageTitle = 'Analytics';
ob_start();
?>
<div class="nt-page-header">
  <h1 class="nt-page-title">📊 Analytics</h1>
  <p class="nt-page-sub">Traffic and engagement overview for the last 30 days.</p>
</div>

<!-- ── Metric Cards ───────────────────────────────────────────── -->
<div class="dash-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px">

  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Visitors Today</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($visitorsToday) ?></div>
  </div>

  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">This Week</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($visitorsWeek) ?></div>
  </div>

  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">This Month</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($visitorsMonth) ?></div>
  </div>

  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Total Visitors</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($visitorsTotal) ?></div>
  </div>

  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Page Views Today</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($viewsToday) ?></div>
  </div>

  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Views (Month)</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($viewsMonth) ?></div>
  </div>

</div>

<!-- ── Two-column lower section ──────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px">

  <!-- Top Articles -->
  <div class="dcard" style="padding:20px">
    <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">📰 Top Articles (Last 30 Days)</div>
    <?php if (empty($topArticles)): ?>
      <p style="color:var(--muted);font-size:.875rem">No view data yet.</p>
    <?php else: ?>
      <table style="width:100%;font-size:.85rem;border-collapse:collapse">
        <thead>
          <tr style="border-bottom:1px solid var(--border)">
            <th style="text-align:left;padding:6px 0;color:var(--muted);font-weight:600">#</th>
            <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Article</th>
            <th style="text-align:right;padding:6px 0;color:var(--muted);font-weight:600">Views</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topArticles as $i => $row): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px 0;color:var(--muted)"><?= $i + 1 ?></td>
            <td style="padding:8px">
              <a href="/article/<?= h($row['slug']) ?>" target="_blank" style="color:var(--ink);text-decoration:none;font-weight:500"
                 title="<?= h($row['title']) ?>"><?= h(mb_strimwidth($row['title'], 0, 55, '…')) ?></a>
            </td>
            <td style="text-align:right;padding:8px 0;font-weight:700"><?= number_format((int)$row['view_count']) ?></td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    <?php endif ?>
  </div>

  <!-- Country Breakdown -->
  <div class="dcard" style="padding:20px">
    <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">🌍 Visitor Locations (Last 30 Days)</div>
    <?php if (empty($countries)): ?>
      <p style="color:var(--muted);font-size:.875rem">No location data yet.</p>
    <?php else: ?>
      <?php
      $maxCnt = max(array_column($countries, 'cnt'));
      foreach ($countries as $c):
        $pct = $maxCnt > 0 ? round($c['cnt'] / $maxCnt * 100) : 0;
        $totalPct = $visitorsMonth > 0 ? round($c['cnt'] / $visitorsMonth * 100, 1) : 0;
      ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:.83rem;margin-bottom:3px">
          <span style="font-weight:500"><?= h($c['country']) ?></span>
          <span style="color:var(--muted)"><?= number_format((int)$c['cnt']) ?> (<?= $totalPct ?>%)</span>
        </div>
        <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden">
          <div style="width:<?= $pct ?>%;height:100%;background:var(--accent,#cc0000);border-radius:3px"></div>
        </div>
      </div>
      <?php endforeach ?>
    <?php endif ?>
  </div>

</div>

<!-- ── Daily Traffic Chart ────────────────────────────────────── -->
<?php if (!empty($dailyTraffic)): ?>
<div class="dcard" style="padding:20px;margin-bottom:28px">
  <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:16px">📈 Daily Traffic (Last 14 Days)</div>
  <div style="display:flex;align-items:flex-end;gap:6px;height:120px">
    <?php
    $maxDay = max(array_column($dailyTraffic, 'cnt'));
    foreach ($dailyTraffic as $d):
      $h = $maxDay > 0 ? max(4, round($d['cnt'] / $maxDay * 100)) : 4;
    ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px" title="<?= h($d['day']) ?>: <?= number_format((int)$d['cnt']) ?> visitors">
      <div style="width:100%;background:var(--accent,#cc0000);height:<?= $h ?>px;border-radius:4px 4px 0 0;opacity:.85;transition:opacity .15s" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity='.85'"></div>
      <span style="font-size:10px;color:var(--muted);writing-mode:vertical-lr;text-orientation:mixed;transform:rotate(180deg)"><?= date('M j', strtotime($d['day'])) ?></span>
    </div>
    <?php endforeach ?>
  </div>
</div>
<?php endif ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';