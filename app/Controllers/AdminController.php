<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\StoryThread;
use App\Models\Tag;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\DB;
use App\Services\Flash;
use App\Services\Slug;
use App\Services\RBAC;
use App\Services\RateLimiter;
use App\Services\WebPush;
use App\Services\SocialPoster;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminController extends Controller
{
  public function login(): Response
  {
    if (Auth::check()) return $this->redirect('/admin');
    return $this->render('admin/login', ['csrf' => Csrf::token()]);
  }

  public function loginPost(): Response
  {
    $request = Request::createFromGlobals();
    $ip = $request->getClientIp() ?? '0.0.0.0';

    try {
      $limit = RateLimiter::check($ip);
      if ($limit['blocked']) {
        $mins = (int)ceil(($limit['retry_after'] ?? 900) / 60);
        return $this->render('admin/login', [
          'error' => "Too many failed attempts. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . ".",
          'csrf'  => Csrf::token(),
        ]);
      }
    } catch (\Throwable) {}

    $email    = trim((string)$request->request->get('email', ''));
    $password = (string)$request->request->get('password', '');

    if (Auth::attempt($email, $password)) {
      try { RateLimiter::clearAttempts($ip); } catch (\Throwable) {}
      return $this->redirect('/admin');
    }

    try { RateLimiter::recordFailure($ip, $email); } catch (\Throwable) {}

    return $this->render('admin/login', [
      'error' => 'Invalid credentials',
      'csrf'  => Csrf::token(),
    ]);
  }

  public function dashboard(): Response
  {
    return $this->render('admin/dashboard');
  }

  /**
   * Reader Heatmap API — returns aggregated city data for the D3 world map.
   * GET /admin/api/reader-map?period=30d
   */
  public function readerMapApi(): Response
  {
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $period  = $request->query->get('period', '30d');

    $allowed = ['today', '7d', '30d', '90d', 'all'];
    if (!in_array($period, $allowed, true)) {
      $period = '30d';
    }

    $data = \App\Models\SiteVisitor::readerMapData($period);
    return $this->json($data);
  }

  /**
   * Dashboard Pulse API — lightweight polling endpoint for real-time metrics.
   * GET /admin/api/dashboard-pulse
   * Returns: visitors_today, active_sessions, views_last_hour, recent_pings (for map)
   */
  public function dashboardPulse(): Response
  {
    $pdo = \App\Services\DB::pdo();

    $safe = function (string $sql) use ($pdo) {
      try { return $pdo->query($sql); } catch (\Throwable) { return null; }
    };
    $safeInt = function (string $sql) use ($safe) {
      $r = $safe($sql); return $r ? (int)$r->fetchColumn() : 0;
    };

    // Core live metrics
    $visitorsToday   = $safeInt("SELECT COUNT(*) FROM site_visitors WHERE visit_date = CURRENT_DATE");
    $viewsLastHour   = $safeInt("SELECT COUNT(*) FROM article_views WHERE viewed_at >= NOW() - INTERVAL '1 hour'");
    $activeSessions  = $safeInt("SELECT COUNT(*) FROM active_sessions WHERE last_activity >= NOW() - INTERVAL '30 minutes'");
    $pendingReview   = $safeInt("SELECT COUNT(*) FROM articles WHERE status = 'pending_review'");
    $pendingComments = $safeInt("SELECT COUNT(*) FROM comments WHERE status = 'pending'");

    // Recent geo pings (for live map dots) — last 5 minutes of article_views with geo
    $pings = [];
    try {
      $pings = $pdo->query("
        SELECT sv.city, sv.country, sv.latitude AS lat, sv.longitude AS lng
        FROM article_views av
        JOIN site_visitors sv ON sv.ip_address = av.ip_address
          AND sv.visit_date = av.viewed_at::date
        WHERE av.viewed_at >= NOW() - INTERVAL '5 minutes'
          AND sv.latitude IS NOT NULL
        ORDER BY av.viewed_at DESC
        LIMIT 20
      ")->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable) {}

    return $this->json([
      'visitors_today'   => $visitorsToday,
      'views_last_hour'  => $viewsLastHour,
      'active_sessions'  => $activeSessions,
      'pending_review'   => $pendingReview,
      'pending_comments' => $pendingComments,
      'pings'            => $pings,
      'ts'               => date('c'),
    ]);
  }

  /**
   * Engagement Radar API — aggregated metrics per category for spider chart.
   * GET /admin/api/engagement-radar
   */
  public function engagementRadar(): Response
  {
    $pdo = \App\Services\DB::pdo();

    try {
      $data = $pdo->query("
        SELECT
          c.name AS category,
          COALESCE(AVG(
            GREATEST(1, CEIL(LENGTH(regexp_replace(a.content,'<[^>]*>','','g')) / 1000.0))
          ), 0)::int AS avg_read_min,
          COALESCE(COUNT(DISTINCT cm.id), 0) AS comment_count,
          COALESCE(SUM(a.views), 0) AS total_views,
          COALESCE(COUNT(DISTINCT sm.id), 0) AS social_mentions,
          COUNT(DISTINCT a.id) AS article_count
        FROM categories c
        LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
        LEFT JOIN comments cm ON cm.article_id = a.id AND cm.status = 'approved'
        LEFT JOIN social_mentions sm ON sm.keyword_matched = c.name
        GROUP BY c.id, c.name
        HAVING COUNT(DISTINCT a.id) > 0
        ORDER BY total_views DESC
        LIMIT 8
      ")->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable) {
      $data = [];
    }

    // Normalize each axis to 0-100 for radar display
    $maxRead    = max(array_column($data, 'avg_read_min') ?: [1]);
    $maxComment = max(array_column($data, 'comment_count') ?: [1]);
    $maxViews   = max(array_column($data, 'total_views') ?: [1]);
    $maxSocial  = max(array_column($data, 'social_mentions') ?: [1]);

    foreach ($data as &$row) {
      $row['read_score']    = $maxRead > 0    ? round(($row['avg_read_min'] / $maxRead) * 100) : 0;
      $row['comment_score'] = $maxComment > 0  ? round(($row['comment_count'] / $maxComment) * 100) : 0;
      $row['views_score']   = $maxViews > 0    ? round(($row['total_views'] / $maxViews) * 100) : 0;
      $row['social_score']  = $maxSocial > 0   ? round(($row['social_mentions'] / $maxSocial) * 100) : 0;
    }
    unset($row);

    return $this->json($data);
  }

  /**
   * Traffic Chart API — dual-axis data: daily views + 7-day moving average.
   * GET /admin/api/traffic-chart
   */
  public function trafficChart(): Response
  {
    $pdo = \App\Services\DB::pdo();

    try {
      // Daily article_views for last 30 days
      $rows = $pdo->query("
        SELECT d::date AS day,
               COALESCE(av.cnt, 0) AS views,
               COALESCE(sv.cnt, 0) AS visitors
        FROM generate_series(NOW() - INTERVAL '29 days', NOW(), '1 day') AS d
        LEFT JOIN (
          SELECT viewed_at::date AS v_day, COUNT(*) AS cnt
          FROM article_views
          WHERE viewed_at >= NOW() - INTERVAL '29 days'
          GROUP BY viewed_at::date
        ) av ON av.v_day = d::date
        LEFT JOIN (
          SELECT visit_date, COUNT(*) AS cnt
          FROM site_visitors
          WHERE visit_date >= CURRENT_DATE - INTERVAL '29 days'
          GROUP BY visit_date
        ) sv ON sv.visit_date = d::date
        ORDER BY d
      ")->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable) {
      $rows = [];
    }

    // Compute 7-day moving average
    $viewNums = array_map(fn($r) => (int)$r['views'], $rows);
    $result = [];
    foreach ($rows as $i => $row) {
      $windowStart = max(0, $i - 6);
      $window = array_slice($viewNums, $windowStart, $i - $windowStart + 1);
      $avg7d = count($window) > 0 ? round(array_sum($window) / count($window)) : 0;

      $result[] = [
        'day'      => $row['day'],
        'views'    => (int)$row['views'],
        'visitors' => (int)$row['visitors'],
        'avg_7d'   => $avg7d,
      ];
    }

    return $this->json($result);
  }

  public function articles(): Response
  {
    $request = Request::createFromGlobals();

    $q        = trim((string)$request->query->get('q', ''));
    $status   = trim((string)$request->query->get('status', ''));
    $category = trim((string)$request->query->get('category', ''));
    $page     = max(1, (int)$request->query->get('page', 1));

    $authorId = !RBAC::canManageAll() ? Auth::user()['id'] : null;

    $result = Article::adminList($page, 20, $q, $status, $category, $authorId);

    return $this->render('admin/articles', [
      'articles'      => $result['rows'],
      'categories'    => Category::nameSlugList(),
      'q'             => $q,
      'status'        => $status,
      'category'      => $category,
      'page'          => $result['page'],
      'totalPages'    => $result['totalPages'],
      'flash_success' => Flash::get('success'),
      'flash_error'   => Flash::get('error'),
      'csrf'          => Csrf::token(),
    ]);
  }

  public function articleCreate(): Response
  {
    $storyThreads = [];
    try { $storyThreads = StoryThread::activeDropdown(); } catch (\Throwable) {}

    return $this->render('admin/article_form', [
      'mode' => 'create',
      'article' => [
        'title' => '', 'excerpt' => '', 'content' => '', 'featured_image' => '',
        'status' => 'draft', 'published_at' => '', 'category_id' => '',
        'author_name' => 'Admin', 'display_author' => 'Admin',
        'story_thread_id' => '', 'is_breaking' => false, 'breaking_headline' => '',
      ],
      'categories'   => Category::dropdown(),
      'storyThreads' => $storyThreads,
      'csrf'         => Csrf::token(),
      'flash_error'  => Flash::get('error'),
    ]);
  }

  public function articleStore(): Response
  {
    $request = Request::createFromGlobals();

    $title          = trim((string)$request->request->get('title', ''));
    $excerpt        = trim((string)$request->request->get('excerpt', ''));
    $content        = trim((string)$request->request->get('content', ''));
    $featuredImage  = trim((string)$request->request->get('featured_image', ''));
    $categoryId     = (string)$request->request->get('category_id', '');
    $status         = (string)$request->request->get('status', 'draft');
    $displayAuthor  = trim((string)$request->request->get('author_name', ''));
    if ($displayAuthor === '') $displayAuthor = 'Admin';

    if ($title === '' || $content === '' || $categoryId === '') {
      Flash::set('error', 'Title, Category, and Content are required.');
      return $this->redirect('/admin/articles/create');
    }

    if (!in_array($status, Article::VALID_STATUSES, true)) $status = 'draft';

    $user = Auth::user();

    // ── Role enforcement: authors cannot publish or schedule directly ──
    if (!RBAC::isEditor()) {
        if ($status === 'published' || $status === 'archived' || $status === 'scheduled') {
            $status = 'pending_review';
        }
    }

    // ── Publish/schedule time logic ──
    $publishedAtValue = null;
    if ($status === 'published') {
        $publishedAtValue = date('Y-m-d H:i:s');
    } elseif ($status === 'scheduled') {
        $scheduledAt = trim((string)$request->request->get('scheduled_at', ''));
        if ($scheduledAt !== '' && strtotime($scheduledAt) > time()) {
            $publishedAtValue = date('Y-m-d H:i:s', strtotime($scheduledAt));
        } else {
            // Invalid or past datetime — publish immediately instead
            $status = 'published';
            $publishedAtValue = date('Y-m-d H:i:s');
        }
    }

    $newArticle = Article::store([
      'title'             => $title,
      'slug'              => Slug::unique(Slug::make($title)),
      'content'           => $content,
      'excerpt'           => $excerpt !== '' ? $excerpt : null,
      'author_id'         => $user['id'],
      'category_id'       => $categoryId,
      'featured_image'    => $featuredImage !== '' ? $featuredImage : null,
      'status'            => $status,
      'published_at'      => $publishedAtValue,
      'created_by'        => $user['id'],
      'display_author'    => $displayAuthor,
      'story_thread_id'   => trim((string)$request->request->get('story_thread_id', '')) ?: null,
      'is_breaking'       => $request->request->get('is_breaking') ? true : false,
      'breaking_headline' => trim((string)$request->request->get('breaking_headline', '')) ?: null,
    ]);

    // ── Sync tags ────────────────────────────────────────────────
    if ($newArticle && !empty($newArticle['id'])) {
        $rawTags  = trim((string)$request->request->get('tags', ''));
        $tagNames = $rawTags !== '' ? array_filter(array_map('trim', explode(',', $rawTags))) : [];
        try { \App\Models\Tag::syncForArticle($newArticle['id'], $tagNames); } catch (\Throwable) {}
        try { \App\Models\ArticleRevision::createRevision($newArticle['id'], $user['id'], $title, $content, $excerpt); } catch (\Throwable) {}
    }

    // ── Notify editors when article submitted for review ──
    if ($status === 'pending_review') {
        try {
            Notification::notifyEditors(
                Notification::TYPE_ARTICLE_SUBMITTED,
                'New article for review: ' . $title,
                ($user['username'] ?? 'An author') . ' submitted an article for review.',
                '/admin/review',
                ['author_id' => $user['id']]
            );
        } catch (\Throwable) {} // non-critical
        Flash::set('success', 'Article submitted for review. An editor will review it shortly.');
    } elseif ($status === 'scheduled') {
        Flash::set('success', 'Article scheduled for ' . date('M j, Y \a\t g:i A', strtotime($publishedAtValue)) . '.');
    } else {
        Flash::set('success', 'Article created.');
    }

    return $this->redirect('/admin/articles');
  }

  public function articleEdit(string $id): Response
  {
    $article = Article::find($id);
    if (!$article) return new Response("404 Not Found", 404);
    if (!RBAC::canEditArticle($article)) return new Response("403 Forbidden", 403);

    $article['author_name'] = (string)($article['display_author'] ?? '');
    if (trim((string)$article['author_name']) === '') $article['author_name'] = 'Admin';

    $storyThreads = [];
    try { $storyThreads = StoryThread::activeDropdown(); } catch (\Throwable) {}

    $tags      = [];
    $revisions = [];
    try { $tags      = \App\Models\Tag::forArticle($id); } catch (\Throwable) {}
    try { $revisions = \App\Models\ArticleRevision::forArticle($id); } catch (\Throwable) {}

    return $this->render('admin/article_form', [
      'mode'         => 'edit',
      'article'      => $article,
      'categories'   => Category::dropdown(),
      'storyThreads' => $storyThreads,
      'tags'         => $tags,
      'revisions'    => $revisions,
      'csrf'         => Csrf::token(),
      'flash_error'  => Flash::get('error'),
    ]);
  }

  public function articleUpdate(string $id): Response
  {
    $request  = Request::createFromGlobals();
    $existing = Article::find($id);

    if (!$existing) return new Response("404 Not Found", 404);
    if (!RBAC::canEditArticle($existing)) return new Response("403 Forbidden", 403);

    $title          = trim((string)$request->request->get('title', ''));
    $excerpt        = trim((string)$request->request->get('excerpt', ''));
    $content        = trim((string)$request->request->get('content', ''));
    $featuredImage  = trim((string)$request->request->get('featured_image', ''));
    $categoryId     = (string)$request->request->get('category_id', '');
    $status         = (string)$request->request->get('status', 'draft');
    $displayAuthor  = trim((string)$request->request->get('author_name', ''));
    if ($displayAuthor === '') $displayAuthor = 'Admin';

    if ($title === '' || $content === '' || $categoryId === '') {
      Flash::set('error', 'Title, Category, and Content are required.');
      return $this->redirect('/admin/articles/' . $id . '/edit');
    }

    if (!in_array($status, Article::VALID_STATUSES, true)) $status = 'draft';

    $user = Auth::user();
    $previousStatus = $existing['status'] ?? 'draft';

    // ── Role enforcement: authors cannot publish or schedule directly ──
    if (!RBAC::isEditor()) {
        if ($status === 'published' || $status === 'archived' || $status === 'scheduled') {
            $status = 'pending_review';
        }
    }

    // ── Publish/schedule time logic ──
    $publishedAtValue = $existing['published_at'];
    if ($status === 'published') {
      if (empty($existing['published_at'])) $publishedAtValue = date('Y-m-d H:i:s');
    } elseif ($status === 'scheduled') {
      $scheduledAt = trim((string)$request->request->get('scheduled_at', ''));
      if ($scheduledAt !== '' && strtotime($scheduledAt) > time()) {
          $publishedAtValue = date('Y-m-d H:i:s', strtotime($scheduledAt));
      } else {
          // Invalid or past — publish immediately
          $status = 'published';
          $publishedAtValue = empty($existing['published_at']) ? date('Y-m-d H:i:s') : $existing['published_at'];
      }
    } else {
      $publishedAtValue = null;
    }

    // Re-slug if title changed
    $slug = $existing['slug'];
    if ($title !== $existing['title']) {
      $slug = Slug::unique(Slug::make($title), $id);
    }

    Article::updateArticle($id, [
      'title'             => $title,
      'content'           => $content,
      'excerpt'           => $excerpt !== '' ? $excerpt : null,
      'category_id'       => $categoryId,
      'featured_image'    => $featuredImage !== '' ? $featuredImage : null,
      'status'            => $status,
      'published_at'      => $publishedAtValue,
      'display_author'    => $displayAuthor,
      'story_thread_id'   => trim((string)$request->request->get('story_thread_id', '')) ?: null,
      'is_breaking'       => $request->request->get('is_breaking') ? true : false,
      'breaking_headline' => trim((string)$request->request->get('breaking_headline', '')) ?: null,
    ]);

    // ── Sync tags ────────────────────────────────────────────────
    $rawTags  = trim((string)$request->request->get('tags', ''));
    $tagNames = $rawTags !== '' ? array_filter(array_map('trim', explode(',', $rawTags))) : [];
    try { \App\Models\Tag::syncForArticle($id, $tagNames); } catch (\Throwable) {}
    // ── Create revision on each save ─────────────────────────────
    try { \App\Models\ArticleRevision::createRevision($id, $user['id'], $title, $content, $excerpt); } catch (\Throwable) {}

    // ── Fire publish hooks on first publish only ──────────────────
    if ($status === 'published' && $previousStatus !== 'published') {
        $published = Article::find($id);
        if ($published) {
            try { SocialPoster::postArticle($published); } catch (\Throwable) {}
            try { WebPush::notifyArticle($published);    } catch (\Throwable) {}
        }
    }

    // ── Notify editors when article submitted for review ──
    if ($status === 'pending_review' && $previousStatus !== 'pending_review') {
        try {
            Notification::notifyEditors(
                Notification::TYPE_ARTICLE_SUBMITTED,
                'Article submitted for review: ' . $title,
                ($user['username'] ?? 'An author') . ' submitted an article for review.',
                '/admin/review',
                ['article_id' => $id, 'author_id' => $user['id']]
            );
        } catch (\Throwable) {} // non-critical
        Flash::set('success', 'Article submitted for review. An editor will review it shortly.');
    } elseif ($status === 'scheduled') {
        Flash::set('success', 'Article scheduled for ' . date('M j, Y \a\t g:i A', strtotime($publishedAtValue)) . '.');
    } else {
        Flash::set('success', 'Article updated.');
    }

    return $this->redirect('/admin/articles');
  }

  /** Move article to archive (soft delete). */
  public function articleDelete(string $id): Response
  {
    $row = Article::find($id);
    if (!$row) {
      Flash::set('error', 'Article not found.');
      return $this->redirect('/admin/articles');
    }
    if (!RBAC::canEditArticle($row)) return new Response("403 Forbidden", 403);

    Article::execute("UPDATE articles SET status = 'archived', updated_at = NOW() WHERE id = :id", [':id' => $id]);
    Flash::set('success', 'Article moved to archive.');
    return $this->redirect('/admin/articles');
  }

  /** Archive listing page. */
  public function archive(): Response
  {
    $request = Request::createFromGlobals();
    $q    = trim((string)$request->query->get('q', ''));
    $page = max(1, (int)$request->query->get('page', 1));

    try {
      $result = Article::adminList($page, 20, $q, 'archived', '');
    } catch (\Throwable $e) {
      $result = ['rows' => [], 'page' => 1, 'totalPages' => 1];
      Flash::set('error', 'Failed to load archive: ' . $e->getMessage());
    }

    return $this->render('admin/articles_archive', [
      'articles'      => $result['rows'] ?? [],
      'q'             => $q,
      'page'          => $result['page'] ?? 1,
      'totalPages'    => $result['totalPages'] ?? 1,
      'flash_success' => Flash::get('success'),
      'flash_error'   => Flash::get('error'),
      'csrf'          => Csrf::token(),
    ]);
  }

  /** Restore archived article to draft. */
  public function restoreArticle(string $id): Response
  {
    $row = Article::find($id);
    if (!$row) {
      Flash::set('error', 'Article not found.');
      return $this->redirect('/admin/articles/archive');
    }
    Article::execute("UPDATE articles SET status = 'draft', updated_at = NOW() WHERE id = :id", [':id' => $id]);
    Flash::set('success', 'Article restored as draft.');
    return $this->redirect('/admin/articles/archive');
  }

  /** Permanently delete an archived article (hard delete). */
  public function permanentDeleteArticle(string $id): Response
  {
    $row = Article::find($id);
    if (!$row) {
      Flash::set('error', 'Article not found.');
      return $this->redirect('/admin/articles/archive');
    }
    Article::delete($id);
    Flash::set('success', 'Article permanently deleted.');
    return $this->redirect('/admin/articles/archive');
  }

  /** Toggle breaking news status for an article. */
  public function toggleBreaking(string $id): Response
  {
    $request = Request::createFromGlobals();
    $row = Article::find($id);
    if (!$row) {
      Flash::set('error', 'Article not found.');
      return $this->redirect('/admin/articles');
    }

    $isCurrentlyBreaking = !empty($row['is_breaking_manual']);
    \App\Services\BreakingNewsEngine::setManualBreaking($id, !$isCurrentlyBreaking, 6);

    if (!$isCurrentlyBreaking) {
      Flash::set('success', '🔴 "' . ($row['title'] ?? 'Article') . '" marked as BREAKING for 6 hours.');
    } else {
      Flash::set('success', '"' . ($row['title'] ?? 'Article') . '" removed from breaking news.');
    }

    // Flush cache so homepage updates immediately
    try { \App\Services\Cache::forget('home:breaking'); } catch (\Throwable $e) {}
    try { \App\Services\Cache::forget('home:breaking_cards'); } catch (\Throwable $e) {}

    $referer = $request->headers->get('referer', '/admin/articles');
    return $this->redirect($referer);
  }

  /**
   * Bulk archive actions (restore or delete selected articles).
   *
   * FIX BUG-01: The route only accepts POST (enforced in routes/admin.php), but
   * if someone navigates directly to /admin/articles/archive/bulk via GET (bookmark,
   * browser history, etc.) Symfony returns 405 which the error handler renders as a
   * 500. We add an explicit GET guard here that redirects gracefully instead.
   *
   * POST /admin/articles/archive/bulk
   */
  public function bulkArchiveAction(): Response
  {
    $request = Request::createFromGlobals();

    // ── BUG-01 FIX: Guard against direct GET access ────────────────
    // The route definition allows POST only, but a direct browser visit or
    // misconfigured link hits GET and causes a 405 → 500 chain. Redirect
    // gracefully rather than letting the error handler mishandle it.
    if ($request->getMethod() === 'GET') {
      return $this->redirect('/admin/articles/archive');
    }

    // CSRF validation (POST only — safe to check after GET guard)
    if (!Csrf::validate($request->request->get('_csrf', ''))) {
      Flash::set('error', 'Security token expired. Please try again.');
      return $this->redirect('/admin/articles/archive');
    }

    $action = $request->request->get('action', '');
    $ids    = $request->request->all('ids') ?: [];

    if (empty($ids)) {
      Flash::set('error', 'No articles selected.');
      return $this->redirect('/admin/articles/archive');
    }

    // Validate UUIDs to prevent SQL injection (belt-and-suspenders on top of PDO)
    $ids = array_filter($ids, fn($id) => preg_match('/^[0-9a-f\-]{36}$/i', (string)$id));

    if (empty($ids)) {
      Flash::set('error', 'Invalid article selection.');
      return $this->redirect('/admin/articles/archive');
    }

    $pdo = \App\Models\BaseModel::pdo();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
      if ($action === 'restore') {
        $stmt = $pdo->prepare("UPDATE articles SET status = 'draft', updated_at = NOW() WHERE id IN ({$placeholders}) AND status = 'archived'");
        $stmt->execute(array_values($ids));
        $affected = $stmt->rowCount();
        Flash::set('success', $affected . ' article' . ($affected !== 1 ? 's' : '') . ' restored as draft' . ($affected !== 1 ? 's' : '') . '.');
      } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM articles WHERE id IN ({$placeholders}) AND status = 'archived'");
        $stmt->execute(array_values($ids));
        $affected = $stmt->rowCount();
        Flash::set('success', $affected . ' article' . ($affected !== 1 ? 's' : '') . ' permanently deleted.');
      } else {
        Flash::set('error', 'Unknown action.');
      }
    } catch (\Throwable $e) {
      error_log('[bulkArchiveAction] ' . $e->getMessage());
      Flash::set('error', 'Operation failed. Please try again.');
    }

    return $this->redirect('/admin/articles/archive');
  }

  /**
   * Analytics overview page — traffic, views, top articles.
   * Route: GET /admin/analytics
   */
  public function analytics(): Response
  {
    $pdo  = \App\Models\BaseModel::pdo();
    $safe = function (string $sql) use ($pdo): mixed {
      try { return $pdo->query($sql)->fetchColumn(); } catch (\Throwable) { return 0; }
    };

    // ── Visitor totals ─────────────────────────────────────────────
    $visitorsToday   = (int)$safe("SELECT COUNT(*) FROM site_visitors WHERE created_at >= CURRENT_DATE");
    $visitorsWeek    = (int)$safe("SELECT COUNT(*) FROM site_visitors WHERE created_at >= NOW() - INTERVAL '7 days'");
    $visitorsMonth   = (int)$safe("SELECT COUNT(*) FROM site_visitors WHERE created_at >= NOW() - INTERVAL '30 days'");
    $visitorsTotal   = (int)$safe("SELECT COUNT(*) FROM site_visitors");

    // ── Page views ─────────────────────────────────────────────────
    $viewsToday  = (int)$safe("SELECT COUNT(*) FROM article_views WHERE created_at >= CURRENT_DATE");
    $viewsWeek   = (int)$safe("SELECT COUNT(*) FROM article_views WHERE created_at >= NOW() - INTERVAL '7 days'");
    $viewsMonth  = (int)$safe("SELECT COUNT(*) FROM article_views WHERE created_at >= NOW() - INTERVAL '30 days'");

    // ── Top articles (last 30 days) ────────────────────────────────
    $topArticles = [];
    try {
      $topArticles = $pdo->query(
        "SELECT a.title, a.slug, COUNT(av.id) AS view_count
         FROM articles a JOIN article_views av ON av.article_id = a.id
         WHERE av.created_at >= NOW() - INTERVAL '30 days'
         GROUP BY a.id, a.title, a.slug ORDER BY view_count DESC LIMIT 10"
      )->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable) {}

    // ── Country breakdown ──────────────────────────────────────────
    $countries = [];
    try {
      $countries = $pdo->query(
        "SELECT country, COUNT(*) AS cnt FROM site_visitors
         WHERE created_at >= NOW() - INTERVAL '30 days' AND country IS NOT NULL AND country != ''
         GROUP BY country ORDER BY cnt DESC LIMIT 10"
      )->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable) {}

    // ── Daily traffic (last 14 days) ───────────────────────────────
    $dailyTraffic = [];
    try {
      $dailyTraffic = $pdo->query(
        "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
         FROM site_visitors WHERE created_at >= NOW() - INTERVAL '14 days'
         GROUP BY day ORDER BY day ASC"
      )->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable) {}

    return $this->render('admin/analytics', [
      'visitorsToday'  => $visitorsToday,
      'visitorsWeek'   => $visitorsWeek,
      'visitorsMonth'  => $visitorsMonth,
      'visitorsTotal'  => $visitorsTotal,
      'viewsToday'     => $viewsToday,
      'viewsWeek'      => $viewsWeek,
      'viewsMonth'     => $viewsMonth,
      'topArticles'    => $topArticles,
      'countries'      => $countries,
      'dailyTraffic'   => $dailyTraffic,
      'pageTitle'      => 'Analytics',
    ]);
  }

  // ══════════════════════════════════════════════════════════════
  // Article Revision Restore
  // ══════════════════════════════════════════════════════════════

  public function revisionRestore(string $id, string $rid): Response
  {
    $article = Article::find($id);
    if (!$article) return new Response('404 Not Found', 404);
    if (!RBAC::canEditArticle($article)) return new Response('403 Forbidden', 403);

    $revision = ArticleRevision::findFull((int)$rid);
    if (!$revision || (string)$revision['article_id'] !== (string)$id) {
      Flash::set('error', 'Revision not found.');
      return $this->redirect('/admin/articles/' . $id . '/edit');
    }

    // Save a new revision of current content before overwriting
    $user = Auth::user();
    ArticleRevision::createRevision(
      $id,
      $user['id'] ?? null,
      (string)$article['title'],
      (string)$article['content'],
      (string)($article['excerpt'] ?? '')
    );

    // Apply the restored revision
    $pdo = DB::pdo();
    $stmt = $pdo->prepare(
      "UPDATE articles SET title = :title, content = :content, excerpt = :excerpt, updated_at = NOW()
       WHERE id = :id"
    );
    $stmt->execute([
      ':title'   => $revision['title'],
      ':content' => $revision['content'],
      ':excerpt' => $revision['excerpt'] ?? '',
      ':id'      => $id,
    ]);

    Flash::set('success', 'Revision #' . $revision['revision_number'] . ' restored successfully.');
    return $this->redirect('/admin/articles/' . $id . '/edit');
  }

  // ══════════════════════════════════════════════════════════════
  // Badge counts API — polled every 30s by sidebar JS
  // ══════════════════════════════════════════════════════════════

  public function apiBadges(): Response
  {
    $review  = 0;
    $notif   = 0;
    $crawler = 0;

    try { $review  = (int)\App\Models\Article::queryColumn("SELECT COUNT(*) FROM articles WHERE status = 'pending_review'"); } catch (\Throwable) {}
    try { $notif   = (int)\App\Models\Notification::unreadCount(Auth::user()['id'] ?? ''); } catch (\Throwable) {}
    try { $crawler = (int)\App\Models\CrawlSource::queryColumn("SELECT COUNT(*) FROM crawl_sources WHERE is_active = TRUE"); } catch (\Throwable) {}

    return new Response(
      json_encode(['review' => $review, 'notif' => $notif, 'crawler' => $crawler]),
      200,
      ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']
    );
  }

  // ══════════════════════════════════════════════════════════════
  // Social Post Log
  // ══════════════════════════════════════════════════════════════

  public function socialPostLog(): Response
  {
    $entries  = [];
    $total    = 0;
    $pages    = 1;
    $perPage  = 50;
    $page     = max(1, (int)($_GET['page'] ?? 1));

    try {
      $pdo = DB::pdo();

      // Total count for pagination header and page math
      $total = (int)$pdo->query(
        "SELECT COUNT(*) FROM social_posts_log"
      )->fetchColumn();

      $pages  = max(1, (int)ceil($total / $perPage));
      $page   = min($page, $pages);
      $offset = ($page - 1) * $perPage;

      $entries = $pdo->prepare(
        "SELECT spl.*, a.title AS article_title, a.slug AS article_slug
         FROM social_posts_log spl
         LEFT JOIN articles a ON a.id = spl.article_id
         ORDER BY spl.created_at DESC
         LIMIT :limit OFFSET :offset"
      );
      $entries->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
      $entries->bindValue(':offset', $offset,  \PDO::PARAM_INT);
      $entries->execute();
      $entries = $entries->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
      error_log('[socialPostLog] ' . $e->getMessage());
    }

    return $this->render('admin/social_post_log', [
      'entries'       => $entries,
      'total'         => $total,
      'pages'         => $pages,
      'page'          => $page,
      'pageTitle'     => 'Social Post Log',
      'flash_success' => Flash::get('success'),
      'flash_error'   => Flash::get('error'),
    ]);
  }

  // ══════════════════════════════════════════════════════════════
  // Push Notification & Social Settings
  // ══════════════════════════════════════════════════════════════

  public function pushSettings(): Response
  {
    $push_count         = 0;
    $push_vapid_public  = '';
    $push_vapid_private = '';
    $push_subject       = '';
    $push_enabled       = '0';

    try {
      $push_count         = WebPush::count();
      $push_vapid_public  = get_site_setting('push_vapid_public',  '');
      $push_vapid_private = get_site_setting('push_vapid_private', '');
      $push_subject       = get_site_setting('push_subject',       '');
      $push_enabled       = get_site_setting('push_enabled',       '0');
    } catch (\Throwable $e) {
      error_log('[pushSettings] ' . $e->getMessage());
    }

    return $this->render('admin/push_settings', [
      'push_count'         => $push_count,
      'push_vapid_public'  => $push_vapid_public,
      'push_vapid_private' => $push_vapid_private,
      'push_subject'       => $push_subject,
      'push_enabled'       => $push_enabled,
      'csrf'               => Csrf::token(),
      'pageTitle'          => 'Push & Social Settings',
      'flash_success'      => Flash::get('success'),
      'flash_error'        => Flash::get('error'),
    ]);
  }

  public function pushSettingsSave(): Response
  {
    $req    = Request::createFromGlobals();
    $pdo    = DB::pdo();
    $fields = [
      'push_enabled', 'push_vapid_public', 'push_vapid_private', 'push_subject',
      'social_facebook_enabled', 'social_facebook_page_id', 'social_facebook_token',
      'social_twitter_enabled', 'social_twitter_api_key', 'social_twitter_api_secret',
      'social_twitter_access_token', 'social_twitter_access_secret',
      'social_telegram_enabled', 'social_telegram_bot_token', 'social_telegram_chat_id',
      'social_whatsapp_enabled', 'social_whatsapp_phone', 'social_whatsapp_token',
      'social_linkedin_enabled', 'social_linkedin_token',
    ];

    try {
      foreach ($fields as $key) {
        $isCheckbox = str_ends_with($key, '_enabled');
        $value = $isCheckbox
          ? ($req->request->get($key) === '1' ? '1' : '0')
          : trim((string)$req->request->get($key, ''));

        $stmt = $pdo->prepare(
          "INSERT INTO site_settings (setting_key, setting_value, setting_group)
           VALUES (:key, :value, 'push')
           ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
        );
        $stmt->execute([':key' => $key, ':value' => $value]);
      }

      // Bust settings cache
      $pdo->prepare(
        "INSERT INTO site_settings (setting_key, setting_value, setting_group)
         VALUES ('theme_cache_bust', :v, 'theme')
         ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
      )->execute([':v' => (string)time()]);

      Flash::set('success', 'Push & social settings saved.');
    } catch (\Throwable $e) {
      error_log('[pushSettingsSave] ' . $e->getMessage());
      Flash::set('error', 'Failed to save settings: ' . $e->getMessage());
    }

    return $this->redirect('/admin/push/settings');
  }

  public function pushGenerateKeys(): Response
  {
    try {
      $keys = WebPush::generateVapidKeys();
      $pdo  = DB::pdo();

      foreach (['push_vapid_public' => $keys['public'], 'push_vapid_private' => $keys['private']] as $k => $v) {
        $pdo->prepare(
          "INSERT INTO site_settings (setting_key, setting_value, setting_group)
           VALUES (:key, :value, 'push')
           ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
        )->execute([':key' => $k, ':value' => $v]);
      }

      Flash::set('success', 'VAPID keys generated. Existing subscribers will need to re-subscribe.');
    } catch (\Throwable $e) {
      error_log('[pushGenerateKeys] ' . $e->getMessage());
      Flash::set('error', 'Key generation failed: ' . $e->getMessage());
    }

    return $this->redirect('/admin/push/settings');
  }

  public function logout(): Response
  {
    Auth::logout();
    return $this->redirect('/admin/login');
  }
}