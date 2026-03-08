<?php
/**
 * Admin Content Performance
 * Route: GET /admin/performance
 */
$pageTitle = $pageTitle ?? 'Content Performance';
$activeNav = $activeNav ?? 'performance';
$topArticles = $topArticles ?? [];
$categoryStats = $categoryStats ?? [];
$authorStats = $authorStats ?? [];
$peakHours = $peakHours ?? [];
$dailyTrend = $dailyTrend ?? [];
$summary = $summary ?? [];
ob_start();
?>

<div class="nt-page-header">
  <h1 class="nt-page-title">Content Performance</h1>
  <p class="nt-page-sub">Last 30 days performance metrics</p>
</div>

<!-- Summary Cards -->
<div class="dash-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px">
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Total Views</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($summary['total_views_30d'] ?? 0) ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Articles Published</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($summary['total_articles_30d'] ?? 0) ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Total Shares</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($summary['total_shares_30d'] ?? 0) ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Avg Engagement Score</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= $summary['avg_engagement'] ?? 0 ?></div>
  </div>
</div>

<?php if (!empty($dailyTrend)): ?>
<div class="dcard" style="margin-bottom:28px;padding:20px">
  <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:16px">Daily Trend</div>
  <canvas id="dailyTrendChart" height="80"></canvas>
</div>
<?php endif; ?>

<!-- Top Articles -->
<div class="dcard" style="margin-bottom:28px;padding:20px">
  <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">Top Articles</div>
  <div style="overflow-x:auto">
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Article</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Category</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Published</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Views</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Shares</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Engagement</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Comments</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topArticles as $a): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:8px"><a href="/article/<?= h($a['slug']) ?>" target="_blank" style="color:var(--accent,#cc0000);text-decoration:none;font-weight:500"><?= h(mb_substr($a['title'], 0, 60)) ?><?= mb_strlen($a['title']) > 60 ? '...' : '' ?></a></td>
          <td style="padding:8px"><?= h($a['category'] ?? '') ?></td>
          <td style="padding:8px"><?= h(!empty($a['published_at']) ? date('M j', strtotime($a['published_at'])) : '') ?></td>
          <td style="text-align:right;padding:8px;font-weight:700"><?= number_format((int)($a['views'] ?? 0)) ?></td>
          <td style="text-align:right;padding:8px"><?= number_format((int)($a['share_count'] ?? 0)) ?></td>
          <td style="text-align:right;padding:8px"><?= round((float)($a['engagement_score'] ?? 0), 1) ?></td>
          <td style="text-align:right;padding:8px"><?= (int)($a['comment_count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Category + Author side by side -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px">
  <div class="dcard" style="padding:20px">
    <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">Category Performance</div>
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="text-align:left;padding:6px 0;color:var(--muted);font-weight:600">Category</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Articles</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Views</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Avg</th>
          <th style="text-align:right;padding:6px 0;color:var(--muted);font-weight:600">Shares</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categoryStats as $c): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:8px 0;font-weight:500"><?= h($c['name']) ?></td>
          <td style="text-align:right;padding:8px"><?= (int)$c['article_count'] ?></td>
          <td style="text-align:right;padding:8px"><?= number_format((int)($c['total_views'] ?? 0)) ?></td>
          <td style="text-align:right;padding:8px"><?= number_format((int)($c['avg_views'] ?? 0)) ?></td>
          <td style="text-align:right;padding:8px 0"><?= number_format((int)($c['total_shares'] ?? 0)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="dcard" style="padding:20px">
    <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">Author Leaderboard</div>
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="text-align:left;padding:6px 0;color:var(--muted);font-weight:600">Author</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Articles</th>
          <th style="text-align:right;padding:6px 8px;color:var(--muted);font-weight:600">Views</th>
          <th style="text-align:right;padding:6px 0;color:var(--muted);font-weight:600">Avg</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($authorStats as $a): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:8px 0;font-weight:500"><?= h($a['author_name']) ?></td>
          <td style="text-align:right;padding:8px"><?= (int)$a['article_count'] ?></td>
          <td style="text-align:right;padding:8px"><?= number_format((int)($a['total_views'] ?? 0)) ?></td>
          <td style="text-align:right;padding:8px 0"><?= number_format((int)($a['avg_views'] ?? 0)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($dailyTrend)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
var ctx = document.getElementById('dailyTrendChart');
if (ctx) {
  var data = <?= json_encode($dailyTrend) ?>;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.map(function(d) { return d.stat_date; }),
      datasets: [
        { label: 'Views', data: data.map(function(d) { return d.total_views; }), borderColor: '#cc0000', tension: 0.3, fill: false },
        { label: 'Visitors', data: data.map(function(d) { return d.unique_visitors; }), borderColor: '#0066cc', tension: 0.3, fill: false },
        { label: 'Articles', data: data.map(function(d) { return d.articles_published; }), borderColor: '#28a745', tension: 0.3, fill: false }
      ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
  });
}
</script>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';
