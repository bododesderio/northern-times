<?php
use App\Services\Auth;
use App\Services\DB;

$user      = Auth::user();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

$pdo = DB::pdo();

// ── Safe query helpers (tables may not exist yet) ─────────────
$safeQuery = function(string $sql) use ($pdo) {
    try { return $pdo->query($sql); } catch (\PDOException $e) { return null; }
};
$safeCol = function(string $sql) use ($safeQuery) {
    $r = $safeQuery($sql); return $r ? (int)$r->fetchColumn() : 0;
};

// ═══════════════════════════════════════════════════════════════
//  DATA: Core Counts
// ═══════════════════════════════════════════════════════════════
$totalArticles = (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
$published     = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn();
$drafts        = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='draft'")->fetchColumn();
$pendingReview = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='pending_review'")->fetchColumn();
$archived      = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='archived'")->fetchColumn();
$categoryCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$mediaCount    = (int)$pdo->query("SELECT COUNT(*) FROM media_library")->fetchColumn();
$subTotal      = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
$subActive     = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'")->fetchColumn();
$userCount     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=TRUE")->fetchColumn();
$totalViews    = (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM articles WHERE status='published'")->fetchColumn();

// ── Visitors (unique IPs per day — may not exist) ─────────────
$visitorsTotal     = $safeCol("SELECT COUNT(*) FROM site_visitors");
$visitorsToday     = $safeCol("SELECT COUNT(*) FROM site_visitors WHERE visit_date = CURRENT_DATE");
$visitorsThisWeek  = $safeCol("SELECT COUNT(*) FROM site_visitors WHERE visit_date >= CURRENT_DATE - INTERVAL '7 days'");
$visitorsThisMonth = $safeCol("SELECT COUNT(*) FROM site_visitors WHERE visit_date >= CURRENT_DATE - INTERVAL '30 days'");

// ── Reader Hours ──────────────────────────────────────────────
$readerRow = $pdo->query("
    SELECT COALESCE(SUM(
      views * GREATEST(1, CEIL(LENGTH(regexp_replace(content,'<[^>]*>','','g')) / 1000.0))
    ), 0) AS reader_mins
    FROM articles WHERE status='published' AND views > 0
")->fetch();
$readerMinutes = (int)($readerRow['reader_mins'] ?? 0);
$readerHours   = $readerMinutes > 0 ? round($readerMinutes / 60, 1) : 0;
$avgReadMin    = $totalViews > 0 ? max(1, (int)round($readerMinutes / $totalViews)) : 0;

// ── Comments ──────────────────────────────────────────────────
$commentTotal   = $safeCol("SELECT COUNT(*) FROM comments");
$commentPending = $safeCol("SELECT COUNT(*) FROM comments WHERE status='pending'");

// ── Velocity ──────────────────────────────────────────────────
$pubThisWeek   = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE published_at >= NOW() - INTERVAL '7 days' AND status='published'")->fetchColumn();
$pubThisMonth  = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE published_at >= NOW() - INTERVAL '30 days' AND status='published'")->fetchColumn();
$subsThisWeek  = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE created_at >= NOW() - INTERVAL '7 days'")->fetchColumn();
$subsThisMonth = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE created_at >= NOW() - INTERVAL '30 days'")->fetchColumn();

// ═══════════════════════════════════════════════════════════════
//  DATA: Charts
// ═══════════════════════════════════════════════════════════════

// Daily views (article_views table)
$dailyViews = []; $hasViewsTable = false;
$stmt = $safeQuery("
    SELECT d::date AS day, COALESCE(cnt,0) AS cnt FROM generate_series(NOW()-INTERVAL '13 days',NOW(),'1 day') AS d
    LEFT JOIN (SELECT viewed_at::date AS v_day, COUNT(*) AS cnt FROM article_views WHERE viewed_at>=NOW()-INTERVAL '13 days' GROUP BY viewed_at::date) av ON av.v_day=d::date ORDER BY d
");
if ($stmt) { $dailyViews = $stmt->fetchAll(); $hasViewsTable = true; }

// Daily visitors
$dailyVisitors = [];
$stmt2 = $safeQuery("
    SELECT d::date AS day, COALESCE(cnt,0) AS cnt FROM generate_series(NOW()-INTERVAL '13 days',NOW(),'1 day') AS d
    LEFT JOIN (SELECT visit_date, COUNT(*) AS cnt FROM site_visitors WHERE visit_date>=CURRENT_DATE-INTERVAL '13 days' GROUP BY visit_date) sv ON sv.visit_date=d::date ORDER BY d
");
if ($stmt2) $dailyVisitors = $stmt2->fetchAll();

// Daily published articles
$dailyArticles = $pdo->query("
    SELECT d::date AS day, COALESCE(cnt,0) AS cnt FROM generate_series(NOW()-INTERVAL '13 days',NOW(),'1 day') AS d
    LEFT JOIN (SELECT DATE(published_at) AS pub_day, COUNT(*) AS cnt FROM articles WHERE status='published' AND published_at>=NOW()-INTERVAL '13 days' GROUP BY DATE(published_at)) a ON a.pub_day=d::date ORDER BY d
")->fetchAll();
$maxDailyArt = max(array_column($dailyArticles,'cnt') ?: [1]); if ($maxDailyArt < 1) $maxDailyArt = 1;

// Decide which chart to show: views > visitors > articles
$chartData = $dailyArticles; $chartMax = $maxDailyArt; $chartLabel = 'article'; $chartTitle = 'Publishing Activity';
if (!empty($dailyVisitors) && max(array_column($dailyVisitors,'cnt') ?: [0]) > 0) {
    $chartData = $dailyVisitors; $chartMax = max(array_column($dailyVisitors,'cnt') ?: [1]); $chartLabel = 'visitor'; $chartTitle = 'Daily Visitors';
}
if ($hasViewsTable && !empty($dailyViews) && max(array_column($dailyViews,'cnt') ?: [0]) > 0) {
    $chartData = $dailyViews; $chartMax = max(array_column($dailyViews,'cnt') ?: [1]); $chartLabel = 'view'; $chartTitle = 'Daily Page Views';
}
if ($chartMax < 1) $chartMax = 1;

// Categories by views
$catViews = $pdo->query("
    SELECT c.name, COALESCE(SUM(a.views),0) AS total_views, COUNT(a.id) AS articles
    FROM categories c LEFT JOIN articles a ON a.category_id=c.id AND a.status='published'
    GROUP BY c.id, c.name ORDER BY total_views DESC LIMIT 6
")->fetchAll();
$maxCatViews = max(array_column($catViews,'total_views') ?: [1]); if ($maxCatViews < 1) $maxCatViews = 1;

// ═══════════════════════════════════════════════════════════════
//  DATA: Lists
// ═══════════════════════════════════════════════════════════════
$topArticles = $pdo->query("
    SELECT a.id, a.title, a.slug, a.views, a.published_at, c.name AS category,
           COALESCE(NULLIF(a.display_author,''),u.username) AS author
    FROM articles a LEFT JOIN categories c ON c.id=a.category_id LEFT JOIN users u ON u.id=a.author_id
    WHERE a.status='published' ORDER BY a.views DESC, a.published_at DESC NULLS LAST LIMIT 5
")->fetchAll();

$recentArticles = $pdo->query("
    SELECT a.id, a.title, a.status, a.views, a.published_at, a.updated_at,
           COALESCE(NULLIF(a.display_author,''),u.username) AS author
    FROM articles a LEFT JOIN users u ON u.id=a.author_id ORDER BY a.updated_at DESC LIMIT 6
")->fetchAll();

$recentSubs = $pdo->query("SELECT email, status, created_at FROM newsletter_subscribers ORDER BY created_at DESC LIMIT 5")->fetchAll();

$recentComments = [];
$cmStmt = $safeQuery("SELECT c.id, c.author_name, c.content, c.status, c.created_at, a.title AS article_title FROM comments c JOIN articles a ON a.id=c.article_id ORDER BY c.created_at DESC LIMIT 5");
if ($cmStmt) $recentComments = $cmStmt->fetchAll();

$authorStats = $pdo->query("
    SELECT u.username, u.avatar_url, u.role, COUNT(a.id) AS articles, COALESCE(SUM(a.views),0) AS total_views,
           CASE WHEN SUM(LENGTH(regexp_replace(a.content,'<[^>]*>','','g'))) > 0
                THEN ROUND(
                  (SUM(a.views * GREATEST(1, CEIL(LENGTH(regexp_replace(a.content,'<[^>]*>','','g')) / 1000.0))))::numeric
                  / NULLIF(SUM(LENGTH(regexp_replace(a.content,'<[^>]*>','','g')) / 200.0), 0)
                , 1)
                ELSE 0 END AS mins_per_word
    FROM users u LEFT JOIN articles a ON a.author_id=u.id AND a.status='published'
    WHERE u.is_active=TRUE GROUP BY u.id, u.username, u.avatar_url, u.role
    ORDER BY total_views DESC, articles DESC LIMIT 5
")->fetchAll();

// ── Active Sessions (for live pulse) ──────────────────────────
$activeSessionCount = $safeCol("SELECT COUNT(*) FROM active_sessions WHERE last_activity >= NOW() - INTERVAL '30 minutes'");

// ── Engagement Radar (top categories, pre-computed for SSR) ───
$radarData = [];
try {
    $radarData = $pdo->query("
        SELECT c.name AS category,
               COALESCE(AVG(GREATEST(1, CEIL(LENGTH(regexp_replace(a.content,'<[^>]*>','','g')) / 1000.0))), 0)::int AS avg_read_min,
               COALESCE(COUNT(DISTINCT cm.id), 0) AS comment_count,
               COALESCE(SUM(a.views), 0) AS total_views
        FROM categories c
        LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
        LEFT JOIN comments cm ON cm.article_id = a.id AND cm.status = 'approved'
        GROUP BY c.id, c.name
        HAVING COUNT(DISTINCT a.id) > 0
        ORDER BY total_views DESC LIMIT 6
    ")->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable) {}

// ── Chart.js data (daily views + visitors + 7-day avg) ────────
$chartJsLabels = []; $chartJsViews = []; $chartJsVisitors = []; $chartJsAvg = [];
foreach ($chartData as $i => $d) {
    $chartJsLabels[]   = date('M j', strtotime((string)$d['day']));
    $val = (int)$d['cnt'];
    $chartJsViews[]    = $val;
    // 7-day moving average
    $window = array_slice(array_map(fn($x) => (int)$x['cnt'], $chartData), max(0, $i - 6), min(7, $i + 1));
    $chartJsAvg[]      = count($window) > 0 ? round(array_sum($window) / count($window)) : 0;
}
// Daily visitors for overlay
foreach ($dailyVisitors as $dv) {
    $chartJsVisitors[] = (int)$dv['cnt'];
}
// Pad visitors array if shorter
while (count($chartJsVisitors) < count($chartJsLabels)) array_unshift($chartJsVisitors, 0);

$storageRow = $safeQuery("SELECT COALESCE(SUM(file_size),0) AS total FROM media_library");
$storageMB = $storageRow ? round((int)$storageRow->fetch()['total'] / (1024*1024), 1) : 0;

// ═══════════════════════════════════════════════════════════════
//  Helpers
// ═══════════════════════════════════════════════════════════════
$fmtNum = function(int $n): string {
    if ($n >= 1000000) return number_format($n/1000000,1).'M';
    if ($n >= 1000)    return number_format($n/1000,1).'K';
    return number_format($n);
};
$greeting = match(true) {
    (int)date('H') < 12  => 'Good morning',
    (int)date('H') < 17  => 'Good afternoon',
    default               => 'Good evening',
};
$timeAgo = function(string $dt): string {
    $d = time()-strtotime($dt);
    if ($d<60) return 'just now'; if ($d<3600) return (int)floor($d/60).'m ago';
    if ($d<86400) return (int)floor($d/3600).'h ago'; if ($d<604800) return (int)floor($d/86400).'d ago';
    return date('M j', strtotime($dt));
};


ob_start();
?>

<!-- Dashboard CSS (extracted) -->
<link rel="stylesheet" href="/assets/admin/dashboard.css">

<!-- ═══════════════════════════════════════════════════════════════
     MASTER BENTO GRID — every card is an independent grid child
     Rule of thirds: Chart at 1/3 · Map at 2/3 · Footer anchors
     ═══════════════════════════════════════════════════════════════ -->
<div class="dash-bento">

<!-- ─── GREETING BAR ─── span 12 ──────────────────────────── -->
<div class="dash-greet dcard span-12 anim-item">
  <div class="dash-greet-text">
    <h1><?= $greeting ?>, <?= h($user['username'] ?? 'Admin') ?></h1>
    <p>Here's what's happening at <strong><?= h(site_name()) ?></strong> today.</p>
  </div>
  <div class="dash-greet-actions">
    <div class="live-pulse" id="ntLivePulse" title="Live dashboard — updates every 30s">
      <span class="live-dot"></span>
      <span class="live-label">Live</span>
      <span class="live-sessions" id="ntActiveSessions"><?= $activeSessionCount ?> online</span>
    </div>
    <a class="btn" href="/admin/articles/create">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Article
    </a>
    <a class="btn light" href="/" target="_blank" rel="noopener">View Site ↗</a>
  </div>
</div>

<!-- ─── HERO METRICS (3 × span-4) — larger cards ─────────── -->
<a href="/admin" class="metric-card hero mc-visitors dcard span-4 anim-item" title="Unique visitors based on IP">
  <div class="metric-icon">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
  </div>
  <div class="metric-body">
    <span class="metric-value" data-count="<?= $visitorsTotal ?>"><?= $fmtNum($visitorsTotal) ?></span>
    <span class="metric-label">Site Visitors</span>
  </div>
  <div class="metric-footer">
    <?php if ($visitorsToday > 0): ?>
      <span class="metric-badge up"><?= $visitorsToday ?> today</span>
    <?php else: ?>
      <span class="metric-sub"><?= $visitorsThisWeek ?> this week</span>
    <?php endif; ?>
  </div>
</a>

<a href="/admin/articles" class="metric-card hero mc-views dcard span-4 anim-item" title="Total page views across all articles">
  <div class="metric-icon">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
  </div>
  <div class="metric-body">
    <span class="metric-value" data-count="<?= $totalViews ?>"><?= $fmtNum($totalViews) ?></span>
    <span class="metric-label">Page Views</span>
  </div>
  <div class="metric-footer"><span class="metric-sub">total article reads</span></div>
</a>

<a href="/admin/articles" class="metric-card hero mc-hours dcard span-4 anim-item" title="Estimated total reading time">
  <div class="metric-icon">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
  </div>
  <div class="metric-body">
    <span class="metric-value" data-count="<?= (int)$readerHours ?>"><?= number_format($readerHours,0) ?></span>
    <span class="metric-label">Reader Hours</span>
  </div>
  <div class="metric-footer"><span class="metric-sub"><?= $avgReadMin > 0 ? "~{$avgReadMin} min avg" : 'No reads yet' ?></span></div>
</a>

<!-- ─── SECONDARY METRICS (4 × span-3) — compact cards ───── -->
<a href="/admin/articles" class="metric-card compact mc-published dcard span-3 anim-item">
  <div class="metric-icon">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
  </div>
  <div class="metric-body">
    <span class="metric-value" data-count="<?= $published ?>"><?= number_format($published) ?></span>
    <span class="metric-label">Published</span>
  </div>
  <div class="metric-footer">
    <?php if ($pubThisWeek > 0): ?><span class="metric-badge up">+<?= $pubThisWeek ?> this week</span>
    <?php else: ?><span class="metric-sub"><?= $drafts ?> drafts</span><?php endif; ?>
  </div>
</a>

<a href="/admin/articles?status=draft" class="metric-card compact mc-drafts dcard span-3 anim-item">
  <div class="metric-icon">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
  </div>
  <div class="metric-body">
    <span class="metric-value" data-count="<?= $drafts ?>"><?= number_format($drafts) ?></span>
    <span class="metric-label">Drafts</span>
  </div>
  <div class="metric-footer"><span class="metric-sub"><?= $archived ?> archived · <?= $totalArticles ?> total</span></div>
</a>

<a href="/admin/review" class="metric-card compact mc-review dcard span-3 anim-item">
  <div class="metric-icon">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><path d="M9 15l2 2 4-4"/></svg>
  </div>
  <div class="metric-body">
    <span class="metric-value" data-count="<?= $pendingReview ?>"><?= number_format($pendingReview) ?></span>
    <span class="metric-label">Pending Review</span>
  </div>
  <div class="metric-footer">
    <?php if ($pendingReview > 0): ?><span class="metric-badge warn"><?= $pendingReview ?> awaiting</span>
    <?php else: ?><span class="metric-sub">all clear</span><?php endif; ?>
  </div>
</a>

<a href="/admin/comments" class="metric-card compact mc-comments dcard span-3 anim-item">
  <div class="metric-icon">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
  </div>
  <div class="metric-body">
    <span class="metric-value" data-count="<?= $commentTotal ?>"><?= number_format($commentTotal) ?></span>
    <span class="metric-label">Comments</span>
  </div>
  <div class="metric-footer">
    <?php if ($commentPending > 0): ?><span class="metric-badge warn"><?= $commentPending ?> pending</span>
    <?php else: ?><span class="metric-sub"><?= $commentTotal > 0 ? 'all reviewed' : 'awaiting first comment' ?></span><?php endif; ?>
  </div>
</a>

<!-- ─── FOCAL POINT #1: CHART (8) + CATEGORIES (4) ────────── -->
<div class="dcard span-8 anim-item">
  <div class="card-head">
    <h3><?= $chartTitle ?> vs 7-Day Avg</h3>
    <span class="card-head-sub">Last 14 days</span>
  </div>
  <div class="velocity-grid">
    <div class="vel-pill"><strong><?= $pubThisWeek ?></strong><span>Published / week</span></div>
    <div class="vel-pill"><strong><?= $pubThisMonth ?></strong><span>Published / month</span></div>
    <div class="vel-pill"><strong><?= $pubThisMonth > 0 ? number_format($pubThisMonth/4,1) : '0' ?></strong><span>Avg / week</span></div>
    <div class="vel-pill"><strong><?= $drafts ?></strong><span>Drafts</span></div>
    <div class="vel-pill"><strong><?= $pendingReview ?></strong><span>In review</span></div>
  </div>
  <div style="height:220px;position:relative;">
    <canvas id="ntAreaChart"></canvas>
  </div>
</div>

<div class="dcard span-4 anim-item">
  <div class="card-head">
    <h3>Categories by Views</h3>
    <a href="/admin/categories" class="card-head-link">Manage →</a>
  </div>
  <?php if (!empty($catViews)): ?>
    <div class="cat-chart">
      <?php foreach ($catViews as $ci => $cv): ?>
        <?php $pct = $maxCatViews > 0 ? ((int)$cv['total_views']/$maxCatViews)*100 : 0; ?>
        <div class="cat-row" style="animation-delay:<?= $ci*60 ?>ms">
          <span class="cat-name"><?= h($cv['name']) ?></span>
          <div class="cat-track"><div class="cat-fill" style="--cat-w:<?= max(1,$pct) ?>%"></div></div>
          <span class="cat-num"><?= $fmtNum((int)$cv['total_views']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">No published articles yet.</div>
  <?php endif; ?>

  <div class="card-divider"></div>

  <!-- Engagement Radar -->
  <div class="card-head" style="margin-bottom:10px">
    <h3>Story Impact Radar</h3>
    <span class="card-head-sub">Read Time · Comments · Views</span>
  </div>
  <?php if (!empty($radarData)): ?>
    <div style="height:220px;position:relative;">
      <canvas id="ntRadarChart"></canvas>
    </div>
  <?php else: ?>
    <div class="empty-state">Publish articles to see engagement patterns.</div>
  <?php endif; ?>
</div>

<!-- ─── TOP PERFORMING ARTICLES ─── span 12 ───────────────── -->
<div class="dcard span-12 anim-item">
  <div class="card-head">
    <h3>Top Performing Articles</h3>
    <a href="/admin/articles" class="card-head-link">All articles →</a>
  </div>
  <?php if (!empty($topArticles)): ?>
    <div class="top-list">
      <?php foreach ($topArticles as $i => $ta): ?>
        <a href="/admin/articles/<?= h($ta['id']) ?>/edit" class="top-row" style="animation-delay:<?= $i*50 ?>ms">
          <span class="top-rank <?= $i===0?'rank-gold':'' ?>"><?= $i+1 ?></span>
          <div class="top-info">
            <span class="top-title"><?= h($ta['title']) ?></span>
            <span class="top-meta"><?= h($ta['author']??'—') ?> <span class="dot">·</span> <?= h($ta['category']??'') ?><?php if($ta['published_at']): ?> <span class="dot">·</span> <?= date('M j',strtotime($ta['published_at'])) ?><?php endif; ?></span>
          </div>
          <div class="top-stat"><strong><?= $fmtNum((int)$ta['views']) ?></strong><span>views</span></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">No published articles yet. <a href="/admin/articles/create">Write your first story</a>.</div>
  <?php endif; ?>
</div>

<!-- ─── QUICK ACTIONS ─── span 12 (internal 4-col grid) ───── -->
<div class="dcard span-12 anim-item">
  <div class="qa-grid">
    <a href="/admin/articles/create" class="qa-card"><div class="qa-icon qa-write"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div><span class="qa-title">Write Article</span><span class="qa-desc">Create a story</span></a>
    <a href="/admin/review" class="qa-card"><div class="qa-icon qa-review"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><path d="M9 15l2 2 4-4"/></svg></div><span class="qa-title">Review Queue</span><span class="qa-desc"><?= $pendingReview > 0 ? $pendingReview.' pending' : 'All clear' ?></span></a>
    <a href="/admin/comments" class="qa-card"><div class="qa-icon qa-comments"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><span class="qa-title">Comments</span><span class="qa-desc"><?= $commentPending > 0 ? $commentPending.' pending' : 'Moderate' ?></span></a>
    <a href="/admin/media" class="qa-card"><div class="qa-icon qa-media"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div><span class="qa-title">Media</span><span class="qa-desc"><?= number_format($mediaCount) ?> files</span></a>
    <a href="/admin/newsletter/compose" class="qa-card"><div class="qa-icon qa-subs"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div><span class="qa-title">Send Newsletter</span><span class="qa-desc"><?= number_format($subActive) ?> subscribers</span></a>
    <a href="/admin/categories" class="qa-card"><div class="qa-icon qa-cat"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div><span class="qa-title">Categories</span><span class="qa-desc"><?= $categoryCount ?> sections</span></a>
    <a href="/admin/subscribers" class="qa-card"><div class="qa-icon qa-subs"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div><span class="qa-title">Subscribers</span><span class="qa-desc"><?= number_format($subTotal) ?> total</span></a>
    <a href="/admin/settings" class="qa-card"><div class="qa-icon qa-settings"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div><span class="qa-title">Settings</span><span class="qa-desc">Theme & config</span></a>
  </div>
</div>

<!-- ─── FOCAL POINT #2: ACTIVITY (8) + TEAM (4) ──────────── -->
<div class="dcard span-8 anim-item">
  <div class="card-head"><h3>Recent Activity</h3><a href="/admin/articles" class="card-head-link">View all →</a></div>
  <?php if (!empty($recentArticles)): ?>
    <div class="activity-list">
      <?php foreach ($recentArticles as $ai => $a): ?>
        <a href="/admin/articles/<?= h($a['id']) ?>/edit" class="act-row" style="animation-delay:<?= $ai*40 ?>ms">
          <span class="act-dot <?= $a['status']==='published'?'dot-green':($a['status']==='draft'?'dot-gray':'dot-orange') ?>"></span>
          <div class="act-info">
            <span class="act-title"><?= h($a['title']) ?></span>
            <span class="act-meta"><?= h($a['author']??'—') ?> <span class="dot">·</span> <?= ucfirst($a['status']) ?> <span class="dot">·</span> <?= $timeAgo($a['updated_at']) ?><?php if((int)($a['views']??0)>0): ?> <span class="dot">·</span> <?= $fmtNum((int)$a['views']) ?> views<?php endif; ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">No articles yet.</div>
  <?php endif; ?>

  <?php if (!empty($recentComments)): ?>
    <div class="card-divider"></div>
    <div class="card-head" style="margin-bottom:10px"><h3>Recent Comments</h3><a href="/admin/comments" class="card-head-link">Moderate →</a></div>
    <div class="activity-list">
      <?php foreach ($recentComments as $ci => $c): ?>
        <a href="/admin/comments?status=<?= h($c['status']) ?>" class="act-row" style="animation-delay:<?= $ci*40 ?>ms">
          <span class="act-dot <?= $c['status']==='approved'?'dot-green':'dot-orange' ?>"></span>
          <div class="act-info">
            <span class="act-title"><?= h(mb_strimwidth($c['content'],0,80,'…')) ?></span>
            <span class="act-meta"><?= h($c['author_name']) ?> <span class="dot">·</span> <?= $timeAgo($c['created_at']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif ($commentTotal === 0): ?>
    <div class="card-divider"></div>
    <div class="smart-empty">
      <div class="se-icon">💬</div>
      <p>No comments yet — readers will engage as traffic grows</p>
    </div>
  <?php endif; ?>
</div>

<div class="dcard span-4 anim-item">
  <div class="card-head"><h3>Team Leaderboard</h3><a href="/admin/users" class="card-head-link"><?= $userCount ?> users →</a></div>
  <?php if (!empty($authorStats)): ?>
    <div class="author-list">
      <div class="auth-header">
        <span class="auth-hcol" style="width:22px"></span>
        <span class="auth-hcol" style="width:34px"></span>
        <span class="auth-hcol" style="flex:1">Author</span>
        <span class="auth-hcol" style="width:70px;text-align:right;font-size:10px;color:var(--muted)">Efficiency</span>
        <span class="auth-hcol" style="width:80px"></span>
      </div>
      <?php foreach ($authorStats as $idx => $as): ?>
        <div class="auth-row" style="animation-delay:<?= $idx*50 ?>ms">
          <span class="auth-rank"><?= $idx+1 ?></span>
          <div class="auth-av" style="background:<?= ['#c00','#1a6bbf','#059669','#7c3aed','#d97706'][$idx%5] ?>">
            <?php if(!empty($as['avatar_url'])): ?><img src="<?= h($as['avatar_url']) ?>" alt="">
            <?php else: ?><?= strtoupper(mb_substr($as['username'],0,1)) ?><?php endif; ?>
          </div>
          <div class="auth-info"><span class="auth-name"><?= h($as['username']) ?></span><span class="auth-meta"><?= (int)$as['articles'] ?> articles · <?= $fmtNum((int)$as['total_views']) ?> views</span></div>
          <?php $eff = (float)($as['mins_per_word'] ?? 0); ?>
          <span class="auth-efficiency <?= $eff >= 2 ? 'eff-high' : ($eff >= 1 ? 'eff-mid' : 'eff-low') ?>" title="Reader minutes generated per 200 words written">
            <?= $eff > 0 ? number_format($eff, 1) : '—' ?>
            <small>min/w</small>
          </span>
          <span class="auth-role"><?= h(str_replace('_',' ',$as['role'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card-divider"></div>

  <!-- Subscribers -->
  <div class="card-head" style="margin-bottom:10px">
    <h3>Subscribers</h3>
    <a href="/admin/subscribers" class="card-head-link"><?= number_format($subTotal) ?> total →</a>
  </div>
  <?php if ($subTotal > 0): ?>
    <div class="velocity-grid cols-3">
      <div class="vel-pill"><strong><?= $subActive ?></strong><span>Active</span></div>
      <div class="vel-pill"><strong><?= $subsThisWeek ?></strong><span>This week</span></div>
      <div class="vel-pill"><strong><?= $subsThisMonth ?></strong><span>This month</span></div>
    </div>
  <?php else: ?>
    <div class="smart-empty">
      <div class="se-icon">📧</div>
      <p>No subscribers yet</p>
      <a href="/admin/newsletter/compose" class="btn sm">Launch your first campaign →</a>
    </div>
  <?php endif; ?>

  <div class="card-divider"></div>
  <div class="card-head" style="margin-bottom:10px"><h3>New Subscribers</h3><a href="/admin/subscribers" class="card-head-link">View all →</a></div>
  <?php if (!empty($recentSubs)): ?>
    <div class="sub-list">
      <?php foreach ($recentSubs as $sub): ?>
        <div class="sub-row"><span class="sub-email"><?= h($sub['email']) ?></span><span class="sub-badge <?= $sub['status']==='active'?'sb-active':'sb-inactive' ?>"><?= $sub['status']==='active'?'Active':'Inactive' ?></span><span class="sub-date"><?= $timeAgo($sub['created_at']) ?></span></div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="smart-empty">
      <div class="se-icon">👥</div>
      <p>Grow your audience</p>
      <a href="/admin/popups" class="btn sm light">Create a signup popup →</a>
    </div>
  <?php endif; ?>
</div>

<!-- ─── READER HEATMAP ─── span 12 ────────────────────────── -->
<div id="nt-reader-map" class="dcard span-12 anim-item">
  <div class="card-head" style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;">
    <h3 style="margin:0;">🌍 Readers Worldwide</h3>
    <div style="display:flex;gap:4px;margin-left:auto;">
      <button class="rm-period-btn" data-period="today">Today</button>
      <button class="rm-period-btn" data-period="7d">7 days</button>
      <button class="rm-period-btn active" data-period="30d">30 days</button>
      <button class="rm-period-btn" data-period="90d">90 days</button>
      <button class="rm-period-btn" data-period="all">All time</button>
    </div>
    <span class="rm-total" style="font-size:13px;color:var(--muted);font-weight:600;">0 visitors</span>
  </div>
  <div style="position:relative;">
    <div class="rm-map-wrap" style="width:100%;min-height:350px;margin:12px 0;border-radius:10px;overflow:hidden;"></div>
    <div style="position:absolute;bottom:12px;right:12px;display:flex;gap:4px;">
      <button class="rm-zoom-in rm-zoom-btn" title="Zoom In">+</button>
      <button class="rm-zoom-out rm-zoom-btn" title="Zoom Out">−</button>
      <button class="rm-zoom-reset rm-zoom-btn" title="Reset" style="font-size:11px;">↻</button>
    </div>
  </div>
  <div class="rm-top-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px;padding:4px 0;"></div>
</div>

<!-- ─── FOOTER STATS ─── 4 × span-3 ──────────────────────── -->
<a href="/admin/categories" class="dcard foot-card span-3 anim-item"><span class="foot-num" data-count="<?= $categoryCount ?>"><?= $categoryCount ?></span><span class="foot-label">Categories</span><span class="foot-link">Manage →</span></a>
<a href="/admin/media" class="dcard foot-card span-3 anim-item"><span class="foot-num" data-count="<?= $mediaCount ?>"><?= $mediaCount ?></span><span class="foot-label">Media Files</span><span class="foot-link">Library →</span></a>
<div class="dcard foot-card span-3 anim-item"><span class="foot-num"><?= $storageMB >= 1024 ? number_format($storageMB/1024,1).' GB' : $storageMB.' MB' ?></span><span class="foot-label">Storage Used</span><a href="/admin/media" class="foot-link">Upload →</a></div>
<a href="/admin/users" class="dcard foot-card span-3 anim-item"><span class="foot-num" data-count="<?= $userCount ?>"><?= $userCount ?></span><span class="foot-label">Active Users</span><span class="foot-link">Team →</span></a>

</div><!-- /.dash-bento -->

<!-- ═══ COUNTER ANIMATION JS ══════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count, 10);
    if (!target || target < 1) return;
    const display = el.textContent;
    const duration = 900;
    const start = performance.now();
    const fmt = n => {
      if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
      if (n >= 1000) return (n/1000).toFixed(1) + 'K';
      return n.toLocaleString();
    };
    el.textContent = '0';
    const tick = now => {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(target * ease);
      el.textContent = progress >= 1 ? display : fmt(current);
      if (progress < 1) requestAnimationFrame(tick);
    };
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) { requestAnimationFrame(tick); observer.unobserve(el); }
      });
    }, { threshold: 0.3 });
    observer.observe(el);
  });
});
</script>

<!-- ═══ BAR ANIMATION JS ═══════════════════════════════════════
     Drives .cat-fill and .bar-fill widths/heights directly via JS.
     CSS animation approach was unreliable due to !important cascade
     conflicts and cross-browser @keyframes custom-property bugs.
     This reads the --cat-w / --bar-h CSS custom property from each
     element's computed style and sets style.width/height directly,
     which triggers the CSS transition cleanly in all browsers.
     ══════════════════════════════════════════════════════════ -->
<script>
(function () {
  function animateBars() {
    // Category fill bars (.cat-fill — width driven by --cat-w)
    document.querySelectorAll('.cat-fill').forEach(function (el) {
      var w = getComputedStyle(el).getPropertyValue('--cat-w').trim();
      if (w) {
        requestAnimationFrame(function () {
          el.style.width = w;
        });
      }
    });
    // Vertical bar fills (.bar-fill — height driven by --bar-h)
    document.querySelectorAll('.bar-fill').forEach(function (el) {
      var h = getComputedStyle(el).getPropertyValue('--bar-h').trim();
      if (h) {
        requestAnimationFrame(function () {
          el.style.height = h;
        });
      }
    });
  }

  // Fire after initial paint so CSS transitions play visibly
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      requestAnimationFrame(function () { setTimeout(animateBars, 80); });
    });
  } else {
    requestAnimationFrame(function () { setTimeout(animateBars, 80); });
  }
})();
</script>

<!-- ═══ D3.js Reader Heatmap ═══════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/topojson/3.0.2/topojson.min.js"></script>
<script src="/assets/admin/reader-map.js"></script>

<!-- ═══ Chart.js v4 ═══════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- ═══ DASHBOARD INTELLIGENCE HUB JS ═════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', () => {

  // ── Dark mode colour resolver for Chart.js ───────────────
  const isDark = () =>
    document.documentElement.getAttribute('data-adm-theme') === 'dark' ||
    document.documentElement.classList.contains('dark');

  const chartColors = () => isDark() ? {
    tick:       '#888',
    tickAlt:    '#666',
    grid:       'rgba(255,255,255,.06)',
    gridRadar:  'rgba(255,255,255,.08)',
    angleLines: 'rgba(255,255,255,.08)',
    pointLabel: '#bbb',
    tooltip:    'rgba(10,10,14,.92)',
  } : {
    tick:       '#999',
    tickAlt:    '#aaa',
    grid:       'rgba(0,0,0,.04)',
    gridRadar:  'rgba(0,0,0,.07)',
    angleLines: 'rgba(0,0,0,.07)',
    pointLabel: '#555',
    tooltip:    'rgba(0,0,0,.85)',
  };

  // ── 1. DUAL-AXIS AREA CHART (Traffic vs 7-Day Avg) ────────
  const areaCtx = document.getElementById('ntAreaChart');
  if (areaCtx) {
    const labels   = <?= json_encode($chartJsLabels) ?>;
    const views    = <?= json_encode($chartJsViews) ?>;
    const avg7d    = <?= json_encode($chartJsAvg) ?>;
    const visitors = <?= json_encode($chartJsVisitors) ?>;

    const areaChart = new Chart(areaCtx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Daily <?= $chartLabel === "view" ? "Views" : ucfirst($chartLabel) . "s" ?>',
            data: views,
            borderColor: '#c00',
            backgroundColor: 'rgba(204,0,0,.08)',
            fill: true, tension: 0.35, borderWidth: 2.5,
            pointRadius: 0, pointHitRadius: 12, pointHoverRadius: 5,
            pointHoverBackgroundColor: '#c00', yAxisID: 'y', order: 1
          },
          {
            label: '7-Day Average',
            data: avg7d,
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124,58,237,.06)',
            fill: true, tension: 0.4, borderWidth: 2, borderDash: [6, 3],
            pointRadius: 0, pointHitRadius: 12, yAxisID: 'y', order: 2
          },
          {
            label: 'Visitors',
            data: visitors,
            borderColor: '#3b82f6',
            backgroundColor: 'transparent',
            fill: false, tension: 0.35, borderWidth: 1.5,
            pointRadius: 0, pointHitRadius: 12, yAxisID: 'y1', order: 3
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        animation: { duration: 600, easing: 'easeOutQuart' },
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16, font: { size: 11, family: "'Libre Franklin',sans-serif" } } },
          tooltip: { backgroundColor: chartColors().tooltip, titleFont: { size: 12, family: "'Libre Franklin',sans-serif" }, bodyFont: { size: 12, family: "'Libre Franklin',sans-serif" }, cornerRadius: 8, padding: 10, displayColors: true, boxPadding: 4 }
        },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 10 }, color: chartColors().tick, maxRotation: 0 } },
          y: { position: 'left', grid: { color: chartColors().grid, drawBorder: false }, ticks: { font: { size: 10 }, color: chartColors().tick, callback: v => v >= 1000 ? (v/1000).toFixed(0)+'K' : v }, beginAtZero: true, title: { display: true, text: '<?= ucfirst($chartLabel) ?>s', font: { size: 10 }, color: chartColors().tickAlt } },
          y1: { position: 'right', grid: { display: false }, ticks: { font: { size: 10 }, color: '#3b82f6' }, beginAtZero: true, title: { display: true, text: 'Visitors', font: { size: 10 }, color: '#3b82f6' } }
        }
      }
    });
    window.ntAreaChart = areaChart;
  }

  // ── 2. ENGAGEMENT RADAR CHART ─────────────────────────────
  const radarCtx = document.getElementById('ntRadarChart');
  if (radarCtx) {
    const radarRaw = <?= json_encode($radarData) ?>;
    if (radarRaw.length > 0) {
      const maxRead    = Math.max(...radarRaw.map(r => +r.avg_read_min || 1));
      const maxComment = Math.max(...radarRaw.map(r => +r.comment_count || 1));
      const maxViews   = Math.max(...radarRaw.map(r => +r.total_views || 1));
      const top = radarRaw.slice(0, 4);
      const colors = [
        { bg: 'rgba(204,0,0,.12)', border: '#c00' },
        { bg: 'rgba(59,130,246,.12)', border: '#3b82f6' },
        { bg: 'rgba(5,150,105,.12)', border: '#059669' },
        { bg: 'rgba(124,58,237,.12)', border: '#7c3aed' }
      ];
      new Chart(radarCtx, {
        type: 'radar',
        data: {
          labels: ['Read Time', 'Comments', 'Views'],
          datasets: top.map((cat, i) => ({
            label: cat.category,
            data: [
              Math.round((+cat.avg_read_min / maxRead) * 100),
              Math.round((+cat.comment_count / maxComment) * 100),
              Math.round((+cat.total_views / maxViews) * 100)
            ],
            backgroundColor: colors[i % 4].bg, borderColor: colors[i % 4].border,
            borderWidth: 2, pointRadius: 3, pointHoverRadius: 6,
            pointBackgroundColor: colors[i % 4].border
          }))
        },
        options: {
          responsive: true, maintainAspectRatio: false, animation: { duration: 500 },
          layout: { padding: { top: 10, bottom: 4, left: 10, right: 10 } },
          plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14, font: { size: 11, family: "'Libre Franklin',sans-serif" } } } },
          scales: { r: { beginAtZero: true, max: 100, ticks: { display: false, stepSize: 25 }, grid: { color: chartColors().gridRadar }, angleLines: { color: chartColors().angleLines }, pointLabels: { padding: 8, font: { size: 11, weight: '600', family: "'Libre Franklin',sans-serif" }, color: chartColors().pointLabel } } }
        }
      });
    }
  }

  // ── 3. LIVE DASHBOARD POLLING (30s interval) ──────────────
  const POLL_INTERVAL = 30000;
  let pollTimer = null;

  async function dashboardPulse() {
    try {
      const res = await fetch('/admin/api/dashboard-pulse');
      if (!res.ok) return;
      const data = await res.json();
      const sessEl = document.getElementById('ntActiveSessions');
      if (sessEl) sessEl.textContent = data.active_sessions + ' online';
      const visitorsCard = document.querySelector('.mc-visitors .metric-footer');
      if (visitorsCard && data.visitors_today > 0) {
        visitorsCard.innerHTML = '<span class="metric-badge up">' + data.visitors_today + ' today</span>';
      }
      const reviewBadge = document.querySelector('.mc-comments .metric-footer .metric-badge.warn');
      if (reviewBadge && data.pending_comments > 0) {
        reviewBadge.textContent = data.pending_comments + ' pending';
      }
      if (data.pings && data.pings.length > 0 && window.ntReaderMap && window.ntReaderMap.flashPings) {
        window.ntReaderMap.flashPings(data.pings);
      }
    } catch (e) {}
  }

  pollTimer = setInterval(dashboardPulse, POLL_INTERVAL);
  setTimeout(dashboardPulse, 5000);

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearInterval(pollTimer); pollTimer = null;
    } else {
      dashboardPulse();
      pollTimer = setInterval(dashboardPulse, POLL_INTERVAL);
    }
  });

});
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';