<?php
/**
 * Admin Engagement & Shares
 * Route: GET /admin/engagement
 */
$pageTitle = $pageTitle ?? 'Engagement & Shares';
$topArticles = $topArticles ?? [];
$platformBreakdown = $platformBreakdown ?? [];
$recentShares = $recentShares ?? [];
$dailyTrend = $dailyTrend ?? [];
$summary = $summary ?? [];

$platformLabels = [
  'twitter'   => 'Twitter / X',
  'facebook'  => 'Facebook',
  'whatsapp'  => 'WhatsApp',
  'linkedin'  => 'LinkedIn',
  'email'     => 'Email',
  'copy'      => 'Copy Link',
];

$platformColors = [
  'twitter'   => '#1DA1F2',
  'facebook'  => '#1877F2',
  'whatsapp'  => '#25D366',
  'linkedin'  => '#0A66C2',
  'email'     => '#EA4335',
  'copy'      => '#6c757d',
];

ob_start();
?>

<div class="nt-page-header">
  <h1 class="nt-page-title">Engagement & Shares</h1>
  <p class="nt-page-sub">Reader engagement metrics and social share analytics</p>
</div>

<!-- Summary Cards -->
<div class="dash-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px">
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Avg Engagement Score</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= $summary['avg_engagement'] ?? 0 ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Total Shares (30d)</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($summary['total_shares_30d'] ?? 0) ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Avg Scroll Depth</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= $summary['avg_scroll_depth'] ?? 0 ?>%</div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Avg Time on Page</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= gmdate('i:s', $summary['avg_time_on_page'] ?? 0) ?></div>
  </div>
</div>

<!-- Platform Breakdown + Daily Trend side by side -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:28px">
  <div class="dcard" style="padding:20px">
    <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">Shares by Platform</div>
    <?php if (!empty($platformBreakdown)): ?>
    <canvas id="platformChart" height="220"></canvas>
    <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-top:16px">
      <tbody>
        <?php foreach ($platformBreakdown as $p): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:6px 0;font-weight:500">
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $platformColors[$p['platform']] ?? '#999' ?>;margin-right:6px;vertical-align:middle"></span>
            <?= h($platformLabels[$p['platform']] ?? ucfirst($p['platform'])) ?>
          </td>
          <td style="text-align:right;padding:6px 0;font-weight:700"><?= number_format((int)$p['share_count']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="color:var(--muted);font-size:.85rem">No share data available yet.</p>
    <?php endif; ?>
  </div>

  <?php if (!empty($dailyTrend)): ?>
  <div class="dcard" style="padding:20px">
    <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:16px">Daily Engagement Trend (30 days)</div>
    <canvas id="dailyTrendChart" height="100"></canvas>
  </div>
  <?php endif; ?>
</div>

<!-- Top Engaged Articles -->
<div class="dcard" style="margin-bottom:28px;padding:20px">
  <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">Top Engaged Articles</div>
  <div style="overflow-x:auto">
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Article</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Published</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Views</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Shares</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Scroll Depth %</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Time on Page</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Engagement</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topArticles as $a): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:8px">
            <a href="/article/<?= h($a['slug']) ?>" target="_blank" style="color:var(--accent,#cc0000);text-decoration:none;font-weight:500"><?= h(mb_substr($a['title'], 0, 60)) ?><?= mb_strlen($a['title']) > 60 ? '...' : '' ?></a>
          </td>
          <td style="padding:8px"><?= h(!empty($a['published_at']) ? date('M j', strtotime($a['published_at'])) : '') ?></td>
          <td style="text-align:right;padding:8px;font-weight:700"><?= number_format((int)($a['views'] ?? 0)) ?></td>
          <td style="text-align:right;padding:8px"><?= number_format((int)($a['share_count'] ?? 0)) ?></td>
          <td style="text-align:right;padding:8px"><?= round((float)($a['avg_scroll_depth'] ?? 0), 1) ?>%</td>
          <td style="text-align:right;padding:8px"><?= gmdate('i:s', (int)($a['avg_time_on_page'] ?? 0)) ?></td>
          <td style="text-align:right;padding:8px">
            <?php
              $score = round((float)($a['engagement_score'] ?? 0), 1);
              $color = $score >= 70 ? '#28a745' : ($score >= 40 ? '#ffc107' : '#dc3545');
            ?>
            <span style="font-weight:700;color:<?= $color ?>"><?= $score ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($topArticles)): ?>
        <tr><td colspan="7" style="padding:16px;text-align:center;color:var(--muted)">No engagement data available yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent Shares Feed -->
<div class="dcard" style="margin-bottom:28px;padding:20px">
  <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">Recent Shares</div>
  <?php if (!empty($recentShares)): ?>
  <div style="max-height:400px;overflow-y:auto">
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Time</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Article</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Platform</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentShares as $s): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:6px 8px;white-space:nowrap;color:var(--muted)"><?= h(date('M j, H:i', strtotime($s['created_at']))) ?></td>
          <td style="padding:6px 8px;font-weight:500"><?= h(mb_substr($s['article_title'], 0, 55)) ?><?= mb_strlen($s['article_title']) > 55 ? '...' : '' ?></td>
          <td style="padding:6px 8px">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $platformColors[$s['platform']] ?? '#999' ?>;margin-right:4px;vertical-align:middle"></span>
            <?= h($platformLabels[$s['platform']] ?? ucfirst($s['platform'])) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p style="color:var(--muted);font-size:.85rem">No recent shares recorded.</p>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
<?php if (!empty($platformBreakdown)): ?>
(function() {
  var pData = <?= json_encode($platformBreakdown) ?>;
  var labels = <?= json_encode($platformLabels) ?>;
  var colors = <?= json_encode($platformColors) ?>;
  var ctx = document.getElementById('platformChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: pData.map(function(d) { return labels[d.platform] || d.platform; }),
        datasets: [{
          data: pData.map(function(d) { return parseInt(d.share_count); }),
          backgroundColor: pData.map(function(d) { return colors[d.platform] || '#999'; }),
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } }
        }
      }
    });
  }
})();
<?php endif; ?>

<?php if (!empty($dailyTrend)): ?>
(function() {
  var tData = <?= json_encode($dailyTrend) ?>;
  var ctx = document.getElementById('dailyTrendChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: tData.map(function(d) { return d.stat_date; }),
        datasets: [
          {
            label: 'Avg Engagement',
            data: tData.map(function(d) { return parseFloat(d.avg_engagement); }),
            borderColor: '#cc0000',
            backgroundColor: 'rgba(204,0,0,0.08)',
            tension: 0.3,
            fill: true,
            yAxisID: 'y'
          },
          {
            label: 'Shares',
            data: tData.map(function(d) { return parseInt(d.total_shares); }),
            borderColor: '#1877F2',
            backgroundColor: 'rgba(24,119,242,0.08)',
            tension: 0.3,
            fill: true,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom' } },
        scales: {
          y:  { type: 'linear', position: 'left',  beginAtZero: true, title: { display: true, text: 'Engagement' } },
          y1: { type: 'linear', position: 'right', beginAtZero: true, title: { display: true, text: 'Shares' }, grid: { drawOnChartArea: false } }
        }
      }
    });
  }
})();
<?php endif; ?>
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';
