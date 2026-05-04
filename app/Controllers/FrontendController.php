<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AdSlot;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\RateLimiter;
use App\Services\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FrontendController extends Controller
{
  private function layout(string $view, array $data = []): Response
  {
    extract($data);

    ob_start();
    require __DIR__ . "/../Views/frontend/{$view}.php";
    $content = ob_get_clean();

    ob_start();
    require __DIR__ . "/../Views/frontend/layout.php";
    $html = ob_get_clean();

    return new Response($html, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

  /** White-label site title from admin settings */
  private function siteTitle(): string
  {
    return \site_name();
  }

  /* ================================================================
     HOME — Homepage rebuild v2
     Hero zone (3-col) → Top Stories (full width) → Category sections
     ================================================================ */
  public function home(): Response
  {
    $ads = AdSlot::activeSlots();

    // ── Breaking news (engine-scored, dynamic) ────────────────────
    $breaking = [];
    try {
      $breaking = Cache::remember('home:breaking', 30, fn() => Article::breaking());
    } catch (\Throwable $e) {
      error_log('Home breaking: ' . $e->getMessage());
    }

    $hasManualBreaking = false;
    foreach ($breaking as $b) {
      if (!empty($b['is_breaking_manual'])) { $hasManualBreaking = true; break; }
    }

    // ── Hero articles (national/Northern Uganda headlines) ──────────
    $heroPool = [];
    try {
      $heroPool = Cache::remember('home:hero', 30, fn() => Article::heroArticles(12));
    } catch (\Throwable $e) {
      error_log('Home hero articles: ' . $e->getMessage());
    }

    // ── Latest articles (top stories section below hero) ──────────
    $topStories = [];
    try {
      $topStories = Article::latestPublished(30);
    } catch (\Throwable $e) {
      error_log('Home latest articles: ' . $e->getMessage());
    }

    // ── Latest headlines (right sidebar feed) ─────────────────────
    $latest = [];
    try {
      $latest = Article::latestHeadlines(12);
    } catch (\Throwable $e) {
      error_log('Home latest: ' . $e->getMessage());
    }

    // ── Most read (trending) ──────────────────────────────────────
    $most = [];
    try {
      $most = Cache::remember('home:mostread', 300, fn() => Article::mostRead(10));
    } catch (\Throwable $e) {
      error_log('Home most read: ' . $e->getMessage());
    }

    // ── Category sections (cached) ─────────────────────────────────────────
    $sections = [];
    try {
      $sections = Cache::remember('home:sections', 30, function () {
        $cats = Category::allActive();

        $result = [];
        foreach ($cats as $cat) {
          $articles = Article::byCategoryId($cat['id'], 13);
          if (!empty($articles)) {
            $result[] = [
              'name'     => $cat['name'],
              'slug'     => $cat['slug'],
              'articles' => $articles,
            ];
          }
        }
        return $result;
      });
    } catch (\Throwable $e) {
      error_log('Home category sections FAILED: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }

    // ── Sidebar categories (all categories with article counts) ──
    $sidebarCats = [];
    try {
      $sidebarCats = Cache::remember('home:sidebar_cats', 120, fn() => Category::sidebarCategories());
    } catch (\Throwable $e) {
      error_log('Home sidebar cats: ' . $e->getMessage());
    }

    $meta = [
      'title'       => $this->siteTitle(),
      'description' => \get_site_setting('site_tagline', 'Independent journalism from Northern Uganda and beyond.'),
      'canonical'   => \app_url('/'),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];

    return $this->layout('home', compact(
      'breaking','hasManualBreaking','heroPool','topStories',
      'latest','most','sections','sidebarCats','ads','meta'
    ));
  }

  /* ================================================================
     ARTICLE detail
     ================================================================ */
  public function article(string $slug): Response
  {
    $article = Article::findPublished($slug);
    if (!$article) return abort(404);

    // Debounced view tracking — one view per IP per 30 min
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $sessionKey = 'viewed_' . $article['id'];
    $alreadyViewed = !empty($_SESSION[$sessionKey]);

    if (!$alreadyViewed) {
      try {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        if (!ArticleView::hasRecentView($article['id'], $ip, 30)) {
          ArticleView::record($article['id'], $ip, $referer);
        }
        $_SESSION[$sessionKey] = true;
      } catch (\Throwable) {
        // Fallback: just increment counter
        Article::incrementViews($article['id']);
      }
    }

    $words    = str_word_count(strip_tags((string)$article['content']));
    $readMins = max(1, (int)ceil($words / 200));

    $canonical = \app_url('/article/' . $article['slug']);

    $meta = [
      'title'       => $article['title'] . ' — ' . $this->siteTitle(),
      'description' => $article['excerpt'] ?: excerpt((string)$article['content'], 160),
      'canonical'   => $canonical,
      'og_image'    => $article['featured_image'] ?: \app_url('/assets/og-default.png'),
    ];

    $related = Article::related($article['id'], $article['category_id'], 6);

    // Story thread
    $threadArticles = [];
    if (!empty($article['story_thread_id'])) {
      try {
        $threadArticles = Article::storyThreadArticles($article['story_thread_id'], $article['id'], 8);
      } catch (\Throwable $e) {
        error_log('Article story thread: ' . $e->getMessage());
      }
    }

    // Comments
    $comments = [];
    try { $comments = Comment::forArticle($article['id']); } catch (\Throwable $e) { error_log('Article comments: ' . $e->getMessage()); }

    // Crawled articles always show the super_admin's live profile.
    // Own articles show the publishing user's live profile.
    // Both are fetched fresh from the users table so any profile update
    // is reflected immediately — no stale cached data.
    if (!empty($article['is_crawled'])) {
      $authorRow = \App\Models\User::getSuperAdmin();
    } else {
      $authorRow = !empty($article['author_id'])
        ? \App\Models\User::findById($article['author_id'])
        : null;
    }

    $author = [
      'name'             => !empty($authorRow['display_name']) ? $authorRow['display_name'] : ($authorRow['username'] ?? null),
      'username'         => $authorRow['username']         ?? null,
      'role'             => $authorRow['role']             ?? null,
      'bio'              => $authorRow['bio']              ?? null,
      'avatar_url'       => $authorRow['avatar_url']       ?? null,
      'twitter_handle'   => $authorRow['twitter_handle']   ?? null,
      'facebook_url'     => $authorRow['facebook_url']     ?? null,
      'linkedin_url'     => $authorRow['linkedin_url']     ?? null,
      'instagram_handle' => $authorRow['instagram_handle'] ?? null,
      'whatsapp_number'  => $authorRow['whatsapp_number']  ?? null,
      'website_url'      => $authorRow['website_url']      ?? null,
    ];

    $ads = AdSlot::activeSlots();

    return $this->layout('article', compact(
      'article','readMins','related','meta','author',
      'threadArticles','comments','ads'
    ));
  }

  /* ================================================================
     TAG PAGE — /tag/{slug}  (Phase 10)
     ================================================================ */
  public function tag(string $slug): Response
  {
    $tag = Tag::findBy('slug', $slug);
    if (!$tag) return abort(404);

    $page = max(1, (int)(Request::createFromGlobals()->query->get('page', 1)));
    $result = Tag::articles($slug, $page, 20);

    $meta = [
      'title'       => '#' . $tag['name'] . ' — ' . $this->siteTitle(),
      'description' => 'Articles tagged with ' . $tag['name'] . ' on ' . $this->siteTitle() . '.',
      'canonical'   => \app_url('/tag/' . $slug),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];

    $ads = AdSlot::activeSlots();

    $articles = $result['rows'];
    $total    = $result['total'];
    $pages    = $result['pages'];

    $result = compact('total', 'page', 'pages');

    return $this->layout('tag', compact(
      'tag','articles','result','ads','meta'
    ));
  }

  /* ================================================================
     AUTHOR PAGE — /author/{username}  (Phase 10)
     ================================================================ */
  public function author(string $username): Response
  {
    $user = User::findByUsername($username);
    if (!$user) return abort(404);

    $page    = max(1, (int)(Request::createFromGlobals()->query->get('page', 1)));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    // Count total published articles by this author
    $countRow = Article::queryOne(
      "SELECT COUNT(*) AS cnt FROM articles
       WHERE author_id = :uid AND status = 'published'
         AND (published_at IS NULL OR published_at <= NOW())",
      [':uid' => $user['id']]
    );
    $total = (int)($countRow['cnt'] ?? 0);
    $pages = (int)ceil($total / max(1, $perPage));

    // Fetch articles for the current page
    $articles = Article::query(
      "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
              a.published_at, a.reading_time, a.views,
              c.name AS category, c.slug AS category_slug,
              COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') AS author
       FROM articles a
       JOIN categories c ON c.id = a.category_id
       LEFT JOIN users u ON u.id = a.author_id
       WHERE a.author_id = :uid AND a.status = 'published'
         AND (a.published_at IS NULL OR a.published_at <= NOW())
       ORDER BY a.published_at DESC NULLS LAST
       LIMIT " . (int)$perPage . " OFFSET " . (int)$offset,
      [':uid' => $user['id']]
    );

    $displayName = $user['display_name'] ?? $user['username'];

    $meta = [
      'title'       => $displayName . ' — ' . $this->siteTitle(),
      'description' => 'Articles by ' . $displayName . ' on ' . $this->siteTitle() . '.',
      'canonical'   => \app_url('/author/' . $username),
      'og_image'    => $user['avatar_url'] ?? \app_url('/assets/og-default.png'),
    ];

    $ads = AdSlot::activeSlots();

    $author = $user;
    $result = compact('total', 'page', 'pages');

    return $this->layout('author', compact(
      'author','articles','result','ads','meta'
    ));
  }

  /* ================================================================
     TAG SEARCH API — /api/tags/search?q=...  (Phase 10)
     ================================================================ */
  public function tagsApi(): Response
  {
    $tags = \App\Models\Tag::allNames();
    return $this->json($tags, 200, [
      'Cache-Control' => 'public, max-age=300',
    ]);
  }

  public function tagSearchApi(): Response
  {
    $q = trim((string)(Request::createFromGlobals()->query->get('q', '')));

    if (mb_strlen($q) < 1) {
      return $this->json([]);
    }

    $tags = Tag::search($q, 10);
    return $this->json($tags, 200, [
      'Cache-Control' => 'public, max-age=300',
    ]);
  }

  /* ================================================================
     CATEGORY listing
     ================================================================ */
  public function category(string $slug): Response
  {
    $category = Category::findBySlug($slug);
    if (!$category) return abort(404);

    $articles = Article::byCategorySlug($slug, 1, 40)['rows'];

    // Most-read articles for sidebar
    $most = [];
    try { $most = Article::mostRead(10); } catch (\Throwable $e) { error_log('Category most read: ' . $e->getMessage()); }

    $meta = [
      'title'       => $category['name'] . ' — ' . $this->siteTitle(),
      'description' => $category['description'] ?: ('Latest stories in ' . $category['name'] . '.'),
      'canonical'   => \app_url('/category/' . $slug),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];

    $ads = AdSlot::activeSlots();

    return $this->layout('category', compact('category','articles','ads','most','meta'));
  }

  /* ================================================================
     SEARCH
     ================================================================ */
  public function search(): Response
  {
    $request = Request::createFromGlobals();
    $q = trim((string)$request->query->get('q',''));

    $articles = [];
    if ($q !== '') {
      $articles = Article::search($q, 1, 50)['rows'];
    }

    // Sidebar data
    $trending = [];
    try { $trending = Article::mostRead(5); } catch (\Throwable $e) { error_log('Search trending: ' . $e->getMessage()); }
    $tags = [];
    try { $tags = \App\Models\Tag::trending(8); } catch (\Throwable $e) { error_log('Search tags: ' . $e->getMessage()); }

    $meta = [
      'title'       => ($q ? 'Search: ' . $q : 'Search') . ' — ' . $this->siteTitle(),
      'description' => 'Search the ' . $this->siteTitle() . ' archive.',
      'canonical'   => \app_url('/search?q=' . urlencode($q)),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];

    return $this->layout('search', compact('q','articles','trending','tags','meta'));
  }

  /* ================================================================
     LIVE SEARCH API (JSON)
     ================================================================ */
  public function searchApi(): Response
  {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('api:search:' . $ip, 30, 60)) {
        return new Response(json_encode(['error' => 'Rate limit exceeded']), 429, [
            'Content-Type' => 'application/json',
            'Retry-After' => '60',
        ]);
    }

    $request = Request::createFromGlobals();
    $q = trim((string)$request->query->get('q',''));

    if (mb_strlen($q) < 2) {
      return $this->json(['results' => []]);
    }

    return $this->json(['results' => Article::searchApi($q, 8)], 200, [
      'Cache-Control' => 'public, max-age=60',
    ]);
  }

  /* ================================================================
     COMMENT POST (AJAX)
     ================================================================ */
  public function commentPost(): Response
  {
    $request   = Request::createFromGlobals();
    $articleId = trim((string)$request->request->get('article_id',''));
    $name      = trim((string)$request->request->get('author_name',''));
    $email     = trim((string)$request->request->get('author_email',''));
    $body      = trim((string)$request->request->get('content',''));
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if ($articleId === '' || $name === '' || $body === '') {
      return $this->json(['ok' => false, 'message' => 'Name and comment are required.'], 422);
    }
    if (mb_strlen($body) > 5000) {
      return $this->json(['ok' => false, 'message' => 'Comment too long (max 5 000 chars).'], 422);
    }

    // Rate limit
    try {
      if (Comment::recentCountByIp($ip) >= 5) {
        return $this->json(['ok' => false, 'message' => 'Too many comments. Try again later.'], 429);
      }
    } catch (\Throwable $e) {
      error_log('Comment rate limit check: ' . $e->getMessage());
    }

    // Verify article exists
    $art = Article::queryOne(
      "SELECT id FROM articles WHERE id=:id AND status='published' LIMIT 1",
      [':id' => $articleId]
    );
    if (!$art) {
      return $this->json(['ok' => false, 'message' => 'Article not found.'], 404);
    }

    $row = Comment::post([
      'article_id'   => $articleId,
      'author_name'  => $name,
      'author_email' => $email !== '' ? $email : '',
      'content'      => $body,
      'ip_address'   => $ip,
    ]);

    // Comment -> Newsletter pipeline: auto-subscribe if opted in
    $subscribe = (bool)$request->request->get('subscribe_newsletter', false);
    if ($subscribe && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      try {
        Subscriber::subscribe($email, $name !== '' ? $name : null);
        // Tag the source as 'comment'
        $pdo = \App\Services\DB::pdo();
        $pdo->prepare("UPDATE newsletter_subscribers SET source = 'comment' WHERE email = :email AND (source IS NULL OR source = 'manual')")
            ->execute([':email' => mb_strtolower($email)]);
      } catch (\Throwable $e) {
        // Duplicate email is expected; log unexpected errors
        if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'unique') === false) {
          error_log('Comment newsletter subscribe: ' . $e->getMessage());
        }
      }
    }

    return $this->json([
      'ok'      => true,
      'message' => 'Comment posted!',
      'comment' => [
        'id'          => $row['id'],
        'author_name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'content'     => htmlspecialchars($body, ENT_QUOTES, 'UTF-8'),
        'created_at'  => $row['created_at'],
        'time_label'  => 'Just now',
      ],
    ]);
  }

  /* ================================================================
     RSS / FEED — RSS 2.0 with Dublin Core + Media RSS
     ================================================================ */
  public function feed(?string $slug = null): Response
  {
    $cacheKey = $slug ? "feed:cat:{$slug}" : 'feed:main';

    $xml = Cache::remember($cacheKey, 900, function () use ($slug) {
        $site  = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        $title = $this->siteTitle();
        $desc  = \get_site_setting('site_tagline', 'Independent journalism from Northern Uganda and beyond.');
        $limit = (int)\get_site_setting('rss_item_count', '50');
        $fullText = \get_site_setting('rss_full_text', 'false') === 'true';

        $category = null;
        if ($slug) {
            $category = Category::findBySlug($slug);
            if (!$category) return null;
            $title .= ' — ' . $category['name'];
            $desc = "Latest {$category['name']} articles from {$this->siteTitle()}";
        }

        $items = $slug && $category
            ? Article::query("
                SELECT a.title, a.slug, a.excerpt, a.content, a.published_at, a.updated_at,
                       a.featured_image,
                       CASE WHEN a.is_crawled = TRUE THEN (SELECT COALESCE(NULLIF(su.display_name,''), su.username, 'Staff') FROM users su WHERE su.role = 'super_admin' ORDER BY su.id LIMIT 1) ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
                       c.name AS category
                FROM articles a
                JOIN categories c ON c.id = a.category_id
                LEFT JOIN users u ON u.id = a.author_id
                WHERE a.status = 'published' AND (a.published_at IS NULL OR a.published_at <= NOW())
                  AND a.category_id = :cid
                ORDER BY a.published_at DESC NULLS LAST
                LIMIT " . (int)$limit . "
              ", [':cid' => $category['id']])
            : Article::forRss($limit);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $rss->setAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $rss->setAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $dom->appendChild($rss);

        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        $channel->appendChild($dom->createElement('title', $title));
        $channel->appendChild($dom->createElement('link', $site));
        $channel->appendChild($dom->createElement('description', $desc));
        $channel->appendChild($dom->createElement('language', 'en'));
        $channel->appendChild($dom->createElement('lastBuildDate', gmdate(DATE_RSS)));
        $channel->appendChild($dom->createElement('generator', $this->siteTitle() . ' CMS'));

        // Atom self link
        $feedUrl = $slug ? "{$site}/category/{$slug}/rss.xml" : "{$site}/rss.xml";
        $atomLink = $dom->createElement('atom:link');
        $atomLink->setAttribute('href', $feedUrl);
        $atomLink->setAttribute('rel', 'self');
        $atomLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomLink);

        foreach ($items as $it) {
            $item = $dom->createElement('item');

            $titleEl = $dom->createElement('title');
            $titleEl->appendChild($dom->createTextNode((string)$it['title']));
            $item->appendChild($titleEl);

            $linkUrl = $site . '/article/' . $it['slug'];
            $item->appendChild($dom->createElement('link', $linkUrl));

            $guid = $dom->createElement('guid', $linkUrl);
            $guid->setAttribute('isPermaLink', 'true');
            $item->appendChild($guid);

            // Description (excerpt)
            $descEl = $dom->createElement('description');
            $descEl->appendChild($dom->createCDATASection((string)($it['excerpt'] ?? '')));
            $item->appendChild($descEl);

            // Full text (optional)
            if ($fullText && !empty($it['content'])) {
                $contentEl = $dom->createElement('content:encoded');
                $contentEl->appendChild($dom->createCDATASection((string)$it['content']));
                $item->appendChild($contentEl);
            }

            // Dublin Core author
            if (!empty($it['author'])) {
                $item->appendChild($dom->createElement('dc:creator', (string)$it['author']));
            }

            // Category
            if (!empty($it['category'])) {
                $item->appendChild($dom->createElement('category', (string)$it['category']));
            }

            // pubDate
            $ts  = $it['published_at'] ?: ($it['updated_at'] ?? null);
            $pub = $ts ? gmdate(DATE_RSS, strtotime((string)$ts)) : gmdate(DATE_RSS);
            $item->appendChild($dom->createElement('pubDate', $pub));

            // Featured image as enclosure + media:content
            if (!empty($it['featured_image'])) {
                $imgUrl = $it['featured_image'];
                if (strpos($imgUrl, 'http') !== 0) {
                    $imgUrl = $site . '/' . ltrim($imgUrl, '/');
                }
                $enc = $dom->createElement('enclosure');
                $enc->setAttribute('url', $imgUrl);
                $enc->setAttribute('type', 'image/jpeg');
                $enc->setAttribute('length', '0');
                $item->appendChild($enc);

                $media = $dom->createElement('media:content');
                $media->setAttribute('url', $imgUrl);
                $media->setAttribute('medium', 'image');
                $item->appendChild($media);
            }

            $channel->appendChild($item);
        }

        return $dom->saveXML();
    });

    if ($xml === null) {
        return new Response('Category not found', 404);
    }

    return new Response($xml, 200, [
      'Content-Type' => 'application/rss+xml; charset=UTF-8',
      'X-Content-Type-Options' => 'nosniff',
      'Cache-Control' => 'public, max-age=900',
    ]);
  }

  /** Alias: /rss.xml → feed */
  public function rss(): Response
  {
    return $this->feed();
  }

  /* ================================================================
     SITEMAP — full spec with categories, tags, policies, priority
     ================================================================ */
  public function robotsTxt(): Response
  {
    $siteUrl = rtrim(app_url('/'), '/');
    $body = "User-agent: *
Allow: /

";
    $body .= "# Admin area
Disallow: /admin/
Disallow: /admin

";
    $body .= "# API endpoints
Disallow: /api/

";
    $body .= "# Search results
Disallow: /search?

";
    $body .= "Sitemap: {$siteUrl}/sitemap.xml
";
    return new Response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
  }

  public function sitemap(): Response
  {
    $request = Request::createFromGlobals();
    $month = $request->query->get('month');

    if ($month) {
      return $this->sitemapMonth($month);
    }

    return $this->sitemapIndex();
  }

  private function sitemapIndex(): Response
  {
    $xml = Cache::remember('sitemap:index', 3600, function () {
        $site = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        $dom  = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $index = $dom->createElement('sitemapindex');
        $index->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $dom->appendChild($index);

        // Get distinct months that have published articles
        $pdo = \App\Services\DB::pdo();
        $months = $pdo->query("
            SELECT DISTINCT TO_CHAR(published_at, 'YYYY-MM') AS month
            FROM articles
            WHERE status = 'published' AND published_at IS NOT NULL
            ORDER BY month DESC
        ")->fetchAll(\PDO::FETCH_COLUMN);

        // Static pages sitemap
        $sm = $dom->createElement('sitemap');
        $sm->appendChild($dom->createElement('loc', $site . '/sitemap.xml?month=static'));
        $index->appendChild($sm);

        // Monthly article sitemaps
        foreach ($months as $m) {
            $sm = $dom->createElement('sitemap');
            $sm->appendChild($dom->createElement('loc', $site . '/sitemap.xml?month=' . $m));
            $sm->appendChild($dom->createElement('lastmod', $m . '-01T00:00:00Z'));
            $index->appendChild($sm);
        }

        return $dom->saveXML();
    });

    return new Response($xml, 200, [
      'Content-Type' => 'application/xml; charset=UTF-8',
      'X-Content-Type-Options' => 'nosniff',
      'Cache-Control' => 'public, max-age=3600',
    ]);
  }

  private function sitemapMonth(string $month): Response
  {
    // Validate month format
    if ($month !== 'static' && !preg_match('/^\d{4}-\d{2}$/', $month)) {
      return new Response('Invalid month', 400);
    }

    $cacheKey = 'sitemap:month:' . $month;
    $xml = Cache::remember($cacheKey, 3600, function () use ($month) {
        $site = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        $dom  = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $dom->appendChild($urlset);

        $addUrl = function (string $loc, ?string $lastmod, string $changefreq, string $priority) use ($dom, $urlset) {
            $u = $dom->createElement('url');
            $u->appendChild($dom->createElement('loc', $loc));
            if ($lastmod) {
                $u->appendChild($dom->createElement('lastmod', $lastmod));
            }
            $u->appendChild($dom->createElement('changefreq', $changefreq));
            $u->appendChild($dom->createElement('priority', $priority));
            $urlset->appendChild($u);
        };

        if ($month === 'static') {
            // Homepage
            $addUrl($site . '/', gmdate('Y-m-d\TH:i:s\Z'), 'hourly', '1.0');

            // Categories
            $categories = Category::allActive();
            foreach ($categories as $cat) {
                $lastmod = !empty($cat['updated_at'])
                    ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string)$cat['updated_at']))
                    : null;
                $addUrl($site . '/category/' . $cat['slug'], $lastmod, 'daily', '0.6');
            }

            // Tags
            try {
                $allTags = Tag::trending(100);
                foreach ($allTags as $t) {
                    $addUrl($site . '/tag/' . $t['slug'], null, 'daily', '0.5');
                }
            } catch (\Throwable $e) {
                error_log('Sitemap tags: ' . $e->getMessage());
            }

            // Policy pages
            $policies = ['privacy-policy', 'terms-of-service', 'cookie-policy'];
            foreach ($policies as $pSlug) {
                $addUrl($site . '/policy/' . $pSlug, null, 'monthly', '0.4');
            }

            // Standalone pages
            $addUrl($site . '/about', null, 'monthly', '0.6');
            $addUrl($site . '/contact', null, 'monthly', '0.6');
        } else {
            // Articles for specific month
            $pdo = \App\Services\DB::pdo();
            $stmt = $pdo->prepare("
                SELECT slug, updated_at, published_at
                FROM articles
                WHERE status = 'published'
                  AND published_at IS NOT NULL
                  AND TO_CHAR(published_at, 'YYYY-MM') = :month
                ORDER BY published_at DESC
            ");
            $stmt->execute([':month' => $month]);
            $articles = $stmt->fetchAll();

            foreach ($articles as $r) {
                $lastmod = !empty($r['updated_at'])
                    ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string)$r['updated_at']))
                    : (!empty($r['published_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string)$r['published_at'])) : null);
                $addUrl($site . '/article/' . $r['slug'], $lastmod, 'weekly', '0.8');
            }
        }

        return $dom->saveXML();
    });

    return new Response($xml, 200, [
      'Content-Type' => 'application/xml; charset=UTF-8',
      'X-Content-Type-Options' => 'nosniff',
      'Cache-Control' => 'public, max-age=3600',
    ]);
  }

  /* ================================================================
     AD CLICK TRACKING
     ================================================================ */
  public function adClick(string $id): Response
  {
    // Rate limit: 30 clicks per minute per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('ad_click:' . $ip, 30, 60)) {
      return new Response('', 429);
    }

    // Log click event
    try {
      $page = $_SERVER['HTTP_REFERER'] ?? '/';
      \App\Services\DB::pdo()->prepare(
        "INSERT INTO ad_events (ad_slot_id, ip_address, event_type, page_url) VALUES (:id, :ip::inet, 'click', :page)"
      )->execute([':id' => $id, ':ip' => $ip, ':page' => mb_substr($page, 0, 500)]);
    } catch (\Throwable $e) {
      error_log('Ad click tracking: ' . $e->getMessage());
    }

    $linkUrl = AdSlot::recordClick($id);

    if ($linkUrl && filter_var($linkUrl, FILTER_VALIDATE_URL)) {
      return new Response('', 302, ['Location' => $linkUrl]);
    }

    return new Response('', 302, ['Location' => '/']);
  }

  public function adImpression(): Response
  {
    // Rate limit: 120 impressions per minute per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('ad_impression:' . $ip, 120, 60)) {
      return new Response('', 429);
    }

    try {
      $body = json_decode(file_get_contents('php://input'), true);
      $adId = $body['ad_id'] ?? '';
      $page = $body['page'] ?? '/';
      \App\Services\DB::pdo()->prepare(
        "INSERT INTO ad_events (ad_slot_id, ip_address, event_type, page_url) VALUES (:id, :ip::inet, 'impression', :page)"
      )->execute([':id' => $adId, ':ip' => $ip, ':page' => mb_substr($page, 0, 500)]);
    } catch (\Throwable $e) {
      error_log('Ad impression tracking: ' . $e->getMessage());
    }

    return new Response('', 204);
  }

  /* ================================================================
     BROWSER GEOLOCATION — GPS-level accuracy override
     ================================================================ */
  public function visitorLocation(): Response
  {
    try {
      $body = json_decode(file_get_contents('php://input'), true);
      $lat = (float) ($body['lat'] ?? 0);
      $lon = (float) ($body['lon'] ?? 0);

      if ($lat == 0.0 && $lon == 0.0) {
        return new Response('', 204);
      }

      // Validate coordinate ranges
      if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        return new Response('', 204);
      }

      $ip = \App\Services\GeoIP::clientIP();
      if (!$ip || $ip === '0.0.0.0') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
      }

      // Resolve GPS to nearest known city (local DB first, Nominatim fallback)
      $geo = \App\Services\CityResolver::resolve($lat, $lon);

      $pdo = \App\Services\DB::pdo();
      $stmt = $pdo->prepare("
        UPDATE site_visitors
        SET latitude = :lat,
            longitude = :lon,
            city = COALESCE(:city, city),
            country = COALESCE(:country, country),
            country_code = COALESCE(:cc, country_code),
            region = COALESCE(:region, region)
        WHERE ip_address = :ip::inet
          AND visit_date = CURRENT_DATE
      ");
      $stmt->execute([
        ':lat'     => $lat,
        ':lon'     => $lon,
        ':city'    => $geo['city'] ?? null,
        ':country' => $geo['country_name'] ?? null,
        ':cc'      => $geo['country'] ?? null,
        ':region'  => $geo['region'] ?? null,
        ':ip'      => $ip,
      ]);
    } catch (\Throwable $e) {
      error_log('Visitor geolocation: ' . $e->getMessage());
    }

    return new Response('', 204);
  }

  /* ================================================================
     UNSUBSCRIBE
     ================================================================ */
  public function unsubscribe(): Response
  {
    $request = Request::createFromGlobals();
    $token   = trim((string)$request->query->get('token', ''));

    if ($token === '') {
      return $this->layout('unsubscribe', [
        'status'  => 'error',
        'message' => 'Invalid unsubscribe link — no token provided.',
        'meta'    => ['title' => 'Unsubscribe — ' . $this->siteTitle()],
      ]);
    }

    // POST — actually unsubscribe
    if ($request->getMethod() === 'POST') {
      $sub = Subscriber::findByToken($token);

      if (!$sub) {
        return $this->layout('unsubscribe', [
          'status'  => 'error',
          'message' => 'Invalid or expired unsubscribe link.',
          'meta'    => ['title' => 'Unsubscribe — ' . $this->siteTitle()],
        ]);
      }

      if ($sub['status'] !== 'active') {
        return $this->layout('unsubscribe', [
          'status'  => 'already',
          'message' => 'This email is already unsubscribed.',
          'meta'    => ['title' => 'Already Unsubscribed — ' . $this->siteTitle()],
        ]);
      }

      Subscriber::unsubscribeByToken($token);

      return $this->layout('unsubscribe', [
        'status'  => 'success',
        'message' => 'You have been successfully unsubscribed. You will no longer receive our newsletter.',
        'meta'    => ['title' => 'Unsubscribed — ' . $this->siteTitle()],
      ]);
    }

    // GET — show confirmation form
    $sub = Subscriber::findByToken($token);

    if (!$sub) {
      return $this->layout('unsubscribe', [
        'status'  => 'error',
        'message' => 'Invalid or expired unsubscribe link.',
        'meta'    => ['title' => 'Unsubscribe — ' . $this->siteTitle()],
      ]);
    }

    if ($sub['status'] !== 'active') {
      return $this->layout('unsubscribe', [
        'status'  => 'already',
        'message' => 'This email is already unsubscribed.',
        'meta'    => ['title' => 'Already Unsubscribed — ' . $this->siteTitle()],
      ]);
    }

    return $this->layout('unsubscribe', [
      'status' => 'confirm',
      'email'  => $sub['email'],
      'token'  => $token,
      'meta'   => ['title' => 'Unsubscribe — ' . $this->siteTitle()],
    ]);
  }

  /* ================================================================
    NEWSLETTER subscription
     ================================================================ */
  public function newsletter(): Response
  {
    $request = Request::createFromGlobals();

    $email = trim((string)$request->request->get('email', ''));
    $name  = trim((string)$request->request->get('name', ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return $this->json(['ok' => false, 'message' => 'Please enter a valid email address.'], 422);
    }
    $email = mb_strtolower($email);
    if ($name === '') $name = null;

    // Simple rate limit via session
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    $now = time();
    $key = '_nl_rate';
    $attempts = $_SESSION[$key] ?? [];
    $attempts = array_filter($attempts, fn($t) => $t > $now - 600); // 10-min window
    if (count($attempts) >= 3) {
      return $this->json(['ok' => false, 'message' => 'Too many requests. Please try again later.'], 429);
    }
    $attempts[] = $now;
    $_SESSION[$key] = $attempts;

    try {
      Subscriber::subscribe($email, $name);

      // Send welcome email immediately
      try {
        $pdo = \App\Services\DB::pdo();
        $stmt = $pdo->prepare("SELECT unsub_token FROM newsletter_subscribers WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $token = $stmt->fetchColumn();
        if ($token) {
          $displayName = $name ?: 'there';
          $html = \App\Services\Mailer::welcomeEmail($displayName, $token);
          \App\Services\Mailer::send($email, 'Welcome to ' . (site_name()), $html);
        }
      } catch (\Throwable $e) {
        error_log('Newsletter welcome email failed: ' . $e->getMessage());
      }

      return $this->json(['ok' => true, 'message' => "Subscribed! Check your inbox for a welcome message."]);
    } catch (\Throwable $e) {
      error_log('Newsletter subscribe: ' . $e->getMessage());
      return $this->json(['ok' => false, 'message' => 'Subscription failed. Please try again.'], 500);
    }
  }

  /* ================================================================
     PUSH SUBSCRIBE — /api/push/subscribe  (Phase 11)
     ================================================================ */
  public function pushSubscribe(): Response
  {
    $request = Request::createFromGlobals();
    $body    = json_decode($request->getContent(), true) ?? [];

    $endpoint = trim((string)($body['endpoint'] ?? ''));
    $p256dh   = trim((string)($body['p256dh']   ?? ''));
    $auth     = trim((string)($body['auth']      ?? ''));

    if (!$endpoint || !$p256dh || !$auth) {
      return $this->json(['ok' => false, 'message' => 'Missing subscription fields'], 422);
    }

    $ua = $request->headers->get('User-Agent');
    $ok = \App\Services\WebPush::subscribe($endpoint, $p256dh, $auth, $ua);

    return $this->json(['ok' => $ok]);
  }

  /* ================================================================
     PUSH UNSUBSCRIBE — /api/push/unsubscribe  (Phase 11)
     ================================================================ */
  public function pushUnsubscribe(): Response
  {
    $request  = Request::createFromGlobals();
    $body     = json_decode($request->getContent(), true) ?? [];
    $endpoint = trim((string)($body['endpoint'] ?? ''));

    if ($endpoint) {
      \App\Services\WebPush::unsubscribe($endpoint);
    }

    return $this->json(['ok' => true]);
  }

  /* ================================================================
     FOLLOW TOPIC — /api/follow-topic  (Email Alerts)
     ================================================================ */
  public function followTopic(): Response
  {
    $request = Request::createFromGlobals();
    $email = filter_var(trim((string)$request->request->get('email', '')), FILTER_VALIDATE_EMAIL);
    $type = trim((string)$request->request->get('type', ''));
    $id = trim((string)$request->request->get('id', ''));

    if (!$email || !in_array($type, ['category', 'tag'], true) || !$id) {
      return $this->json(['ok' => false, 'message' => 'Invalid request.'], 400);
    }

    // Rate limit
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('follow:' . $ip, 10, 60)) {
      return $this->json(['ok' => false, 'message' => 'Too many requests.'], 429);
    }

    try {
      $pdo = \App\Services\DB::pdo();
      $token = bin2hex(random_bytes(32));
      $pdo->prepare("
        INSERT INTO topic_follows (email, follow_type, follow_id, unsub_token)
        VALUES (:email, :type, :id, :token)
        ON CONFLICT (email, follow_type, follow_id) DO UPDATE
          SET is_active = TRUE,
              unsub_token = COALESCE(topic_follows.unsub_token, EXCLUDED.unsub_token)
      ")->execute([':email' => $email, ':type' => $type, ':id' => $id, ':token' => $token]);

      return $this->json(['ok' => true, 'message' => 'You will be notified about new articles.']);
    } catch (\Throwable $e) {
      return $this->json(['ok' => false, 'message' => 'Failed to subscribe.'], 500);
    }
  }

  public function unfollowTopic(): Response
  {
    $request = Request::createFromGlobals();
    $token = trim((string)$request->query->get('token', ''));

    if (strlen($token) < 32) {
      return new Response('Invalid token.', 400);
    }

    try {
      $pdo = \App\Services\DB::pdo();
      $pdo->prepare("UPDATE topic_follows SET is_active = FALSE WHERE unsub_token = :token")
        ->execute([':token' => $token]);
    } catch (\Throwable) {}

    return $this->layout('unsubscribe', [
      'status' => 'success',
      'message' => 'You have been unsubscribed from this topic.',
      'meta' => ['title' => 'Unsubscribed — ' . $this->siteTitle()],
    ]);
  }

  /* ================================================================
     SHARE TRACKING — /api/share-track
     ================================================================ */
  public function shareTrack(): Response
  {
    // Rate limit: 20 shares per minute per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('share:' . $ip, 20, 60)) {
      return new Response('', 429);
    }

    try {
      $body = json_decode(file_get_contents('php://input'), true);
      $articleId = $body['article_id'] ?? '';
      $platform = $body['platform'] ?? '';

      if (!$articleId || !$platform || !preg_match('/^[0-9a-f\-]{36}$/i', $articleId)) {
        return new Response('', 400);
      }

      $allowed = ['twitter', 'facebook', 'whatsapp', 'linkedin', 'email', 'copy'];
      if (!in_array($platform, $allowed, true)) {
        return new Response('', 400);
      }

      $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
      $pdo = \App\Services\DB::pdo();

      $pdo->prepare("INSERT INTO article_shares (article_id, platform, ip_address) VALUES (:aid, :p, :ip::inet)")
        ->execute([':aid' => $articleId, ':p' => $platform, ':ip' => $ip]);

      $pdo->prepare("UPDATE articles SET share_count = share_count + 1 WHERE id = :id")
        ->execute([':id' => $articleId]);
    } catch (\Throwable $e) {
      error_log('Share tracking: ' . $e->getMessage());
    }

    return new Response('', 204);
  }

  /* ================================================================
     ENGAGEMENT TRACKING — /api/engagement
     ================================================================ */
  public function engagementTrack(): Response
  {
    // Rate limit: 30 engagement events per minute per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('engagement:' . $ip, 30, 60)) {
      return new Response('', 429);
    }

    try {
      $body = json_decode(file_get_contents('php://input'), true);
      $articleId = $body['article_id'] ?? '';
      $scrollDepth = min(100, max(0, (float)($body['scroll_depth'] ?? 0)));
      $timeOnPage = min(3600, max(0, (int)($body['time_on_page'] ?? 0)));

      if (!$articleId || !preg_match('/^[0-9a-f\-]{36}$/i', $articleId)) {
        return new Response('', 400);
      }

      if ($scrollDepth < 5 || $timeOnPage < 3) {
        return new Response('', 204); // Ignore bounces
      }

      $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
      $pdo = \App\Services\DB::pdo();

      $pdo->prepare("INSERT INTO engagement_events (article_id, ip_address, scroll_depth, time_on_page) VALUES (:aid, :ip::inet, :sd, :tp)")
        ->execute([':aid' => $articleId, ':ip' => $ip, ':sd' => $scrollDepth, ':tp' => $timeOnPage]);

      // Update article aggregate every 10th event (single CTE to avoid 5 subqueries)
      $count = $pdo->prepare("SELECT COUNT(*) FROM engagement_events WHERE article_id = :aid");
      $count->execute([':aid' => $articleId]);
      if ((int)$count->fetchColumn() % 10 === 0) {
        $pdo->prepare("
          WITH agg AS (
            SELECT AVG(scroll_depth) AS avg_sd, AVG(time_on_page)::integer AS avg_tp
            FROM engagement_events WHERE article_id = :aid1
          )
          UPDATE articles SET
            avg_scroll_depth = agg.avg_sd,
            avg_time_on_page = agg.avg_tp,
            engagement_score = agg.avg_sd * 0.4 + LEAST(agg.avg_tp, 300) / 3.0 * 0.3 + LEAST(share_count, 100) * 0.3
          FROM agg
          WHERE id = :aid2
        ")->execute([':aid1' => $articleId, ':aid2' => $articleId]);
      }
    } catch (\Throwable $e) {
      error_log('Engagement tracking: ' . $e->getMessage());
    }

    return new Response('', 204);
  }

  /* ================================================================
     TRENDING API — /api/trending
     ================================================================ */
  public function trending(): Response
  {
    $articles = Cache::remember('api:trending', 300, function () {
      $pdo = \App\Services\DB::pdo();
      return $pdo->query("
        SELECT a.title, a.slug, a.excerpt, a.featured_image, a.published_at,
               a.views, a.share_count, a.engagement_score,
               c.name AS category, c.slug AS category_slug,
               CASE WHEN a.published_at > NOW() - INTERVAL '6 hours'
                    THEN a.views * 4
                    WHEN a.published_at > NOW() - INTERVAL '24 hours'
                    THEN a.views * 2
                    ELSE a.views
               END AS trending_score
        FROM articles a
        JOIN categories c ON c.id = a.category_id
        WHERE a.status = 'published'
          AND a.published_at >= NOW() - INTERVAL '3 days'
          AND a.deleted_at IS NULL
        ORDER BY trending_score DESC, a.published_at DESC
        LIMIT 10
      ")->fetchAll() ?: [];
    });

    return $this->json($articles, 200, ['Cache-Control' => 'public, max-age=300']);
  }

  // ── Syndication API (v1) ─────────────────────────────────────

  public function syndicationApi(): Response
  {
    $request = Request::createFromGlobals();
    $page = max(1, (int)$request->query->get('page', 1));
    $perPage = min(50, max(1, (int)$request->query->get('per_page', 20)));
    $category = trim((string)$request->query->get('category', ''));
    $since = trim((string)$request->query->get('since', ''));

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('api:syndication:' . $ip, 60, 60)) {
      return $this->json(['error' => 'Rate limit exceeded'], 429, ['Retry-After' => '60']);
    }

    $where = ["a.status = 'published'", "a.deleted_at IS NULL", "(a.published_at IS NULL OR a.published_at <= NOW())"];
    $params = [];

    if ($category && preg_match('/^[a-z0-9\-]+$/', $category)) {
      $where[] = "c.slug = :cat";
      $params[':cat'] = $category;
    }
    if ($since && preg_match('/^\d{4}-\d{2}-\d{2}/', $since)) {
      $where[] = "a.published_at >= :since";
      $params[':since'] = $since;
    }

    $whereSql = implode(' AND ', $where);
    $offset = ($page - 1) * $perPage;

    $pdo = \App\Services\DB::pdo();

    $countSql = "SELECT COUNT(*) FROM articles a JOIN categories c ON c.id = a.category_id WHERE {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT a.title, a.slug, a.excerpt, a.content, a.featured_image,
                   a.published_at, a.updated_at, a.views, a.ai_summary,
                   c.name AS category, c.slug AS category_slug,
                   CASE WHEN a.is_crawled = TRUE THEN cs.name ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            LEFT JOIN crawl_sources cs ON cs.id = a.crawl_source_id
            WHERE {$whereSql}
            ORDER BY a.published_at DESC NULLS LAST
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll() ?: [];

    $siteUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
    $result = array_map(function($a) use ($siteUrl) {
      return [
        'title' => $a['title'],
        'slug' => $a['slug'],
        'url' => $siteUrl . '/article/' . $a['slug'],
        'excerpt' => $a['excerpt'],
        'summary' => $a['ai_summary'],
        'featured_image' => $a['featured_image'],
        'published_at' => $a['published_at'],
        'updated_at' => $a['updated_at'],
        'category' => $a['category'],
        'category_slug' => $a['category_slug'],
        'author' => $a['author'],
      ];
    }, $articles);

    return $this->json([
      'data' => $result,
      'meta' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => (int)ceil($total / $perPage),
        'site' => $siteUrl,
        'name' => function_exists('site_name') ? site_name() : 'News',
      ],
    ], 200, [
      'Cache-Control' => 'public, max-age=300',
      'Access-Control-Allow-Origin' => '*',
    ]);
  }

  public function syndicationArticle(string $slug): Response
  {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('api:syndication:' . $ip, 60, 60)) {
      return $this->json(['error' => 'Rate limit exceeded'], 429);
    }

    $article = Article::findPublished($slug);
    if (!$article) {
      return $this->json(['error' => 'Article not found'], 404);
    }

    $siteUrl = rtrim($_ENV['APP_URL'] ?? '', '/');

    return $this->json([
      'data' => [
        'title' => $article['title'],
        'slug' => $article['slug'],
        'url' => $siteUrl . '/article/' . $article['slug'],
        'content' => $article['content'],
        'excerpt' => $article['excerpt'],
        'summary' => $article['ai_summary'] ?? null,
        'featured_image' => $article['featured_image'],
        'published_at' => $article['published_at'],
        'updated_at' => $article['updated_at'],
        'category' => $article['category'] ?? null,
        'author' => $article['author'] ?? null,
        'tags' => array_map(function($t) { return $t['name']; }, \App\Models\Tag::forArticle($article['id'])),
      ],
      'meta' => [
        'site' => $siteUrl,
        'name' => function_exists('site_name') ? site_name() : 'News',
        'license' => 'All rights reserved. Attribution required for syndication.',
      ],
    ], 200, [
      'Cache-Control' => 'public, max-age=300',
      'Access-Control-Allow-Origin' => '*',
    ]);
  }

  /* ================================================================
     ABOUT PAGE
     ================================================================ */
  public function about(): Response
  {
    $meta = [
      'title'       => 'About Us — ' . $this->siteTitle(),
      'description' => 'Learn about ' . $this->siteTitle() . ' — independent journalism from Northern Uganda and beyond.',
      'canonical'   => \app_url('/about'),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];

    return $this->layout('about', compact('meta'));
  }

  /* ================================================================
     CONTACT PAGE
     ================================================================ */
  public function contact(): Response
  {
    $meta = [
      'title'       => 'Contact Us — ' . $this->siteTitle(),
      'description' => 'Get in touch with ' . $this->siteTitle() . '. Send tips, inquiries, or feedback.',
      'canonical'   => \app_url('/contact'),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];

    return $this->layout('contact', compact('meta'));
  }

  public function contactPost(): Response
  {
    $request = Request::createFromGlobals();

    $name    = trim((string)$request->request->get('name', ''));
    $email   = trim((string)$request->request->get('email', ''));
    $subject = trim((string)$request->request->get('subject', ''));
    $message = trim((string)$request->request->get('message', ''));

    $errors = [];
    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($subject === '') $errors[] = 'Subject is required.';
    if ($message === '') $errors[] = 'Message is required.';
    if (mb_strlen($message) > 10000) $errors[] = 'Message too long (max 10,000 characters).';

    // Rate limit
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::allow('contact:' . $ip, 5, 3600)) {
      $errors[] = 'Too many messages. Please try again later.';
    }

    if (!empty($errors)) {
      $meta = [
        'title'       => 'Contact Us — ' . $this->siteTitle(),
        'description' => 'Get in touch with ' . $this->siteTitle() . '.',
        'canonical'   => \app_url('/contact'),
        'og_image'    => \app_url('/assets/og-default.png'),
      ];
      $formError = implode(' ', $errors);
      $formData  = compact('name', 'email', 'subject', 'message');
      return $this->layout('contact', compact('meta', 'formError', 'formData'));
    }

    // Store in contact_messages table
    try {
      $pdo = \App\Services\DB::pdo();

      $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, ip_address) VALUES (:name, :email, :subject, :message, :ip::inet)");
      $stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':subject' => $subject,
        ':message' => $message,
        ':ip'      => $ip,
      ]);

      // Email notification to admin
      $adminEmail = function_exists('get_site_setting')
          ? get_site_setting('contact_email', $_ENV['MAIL_FROM_ADDRESS'] ?? '')
          : ($_ENV['MAIL_FROM_ADDRESS'] ?? '');
      if ($adminEmail) {
        try {
          \App\Services\Mailer::send(
            $adminEmail,
            "[Contact Form] {$subject}",
            "<h3>New contact form submission</h3>"
            . "<p><strong>From:</strong> " . htmlspecialchars($name) . " &lt;" . htmlspecialchars($email) . "&gt;</p>"
            . "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>"
            . "<hr><p>" . nl2br(htmlspecialchars($message)) . "</p>"
            . "<hr><p style='color:#999;font-size:12px;'>IP: {$ip} | " . date('Y-m-d H:i:s') . "</p>"
          );
        } catch (\Throwable $mailErr) {
          error_log('Contact form email notification failed: ' . $mailErr->getMessage());
        }
      }
    } catch (\Throwable $e) {
      error_log('Contact form error: ' . $e->getMessage());
    }

    $meta = [
      'title'       => 'Message Sent — ' . $this->siteTitle(),
      'description' => 'Your message has been sent.',
      'canonical'   => \app_url('/contact'),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];
    $formSuccess = true;
    return $this->layout('contact', compact('meta', 'formSuccess'));
  }

  /* ================================================================
     HEALTH CHECK — /api/health
     ================================================================ */
  public function health(): Response
  {
    $checks = ['status' => 'ok', 'timestamp' => gmdate('c')];

    // Database
    try {
      \App\Services\DB::pdo()->query('SELECT 1');
      $checks['database'] = 'ok';
    } catch (\Throwable) {
      $checks['database'] = 'error';
      $checks['status'] = 'degraded';
    }

    // Redis
    try {
      $checks['redis'] = Cache::available() ? 'ok' : 'unavailable';
    } catch (\Throwable) {
      $checks['redis'] = 'error';
      $checks['status'] = 'degraded';
    }

    // Last crawl
    try {
      $row = \App\Services\DB::pdo()->query(
        "SELECT finished_at FROM crawl_logs WHERE finished_at IS NOT NULL ORDER BY finished_at DESC LIMIT 1"
      )->fetch();
      $checks['last_crawl'] = $row ? $row['finished_at'] : null;
      if ($row) {
        $elapsed = time() - strtotime($row['finished_at']);
        if ($elapsed > 3600) {
          $checks['crawl_status'] = 'stale';
        } else {
          $checks['crawl_status'] = 'ok';
        }
      }
    } catch (\Throwable) {
      $checks['last_crawl'] = null;
    }

    // Disk
    $free = @disk_free_space('/var/www/html/storage');
    if ($free !== false) {
      $checks['disk_free_mb'] = round($free / 1048576);
      if ($free < 104857600) { // < 100MB
        $checks['status'] = 'degraded';
      }
    }

    $code = $checks['status'] === 'ok' ? 200 : 503;
    return new Response(json_encode($checks, JSON_PRETTY_PRINT), $code, [
      'Content-Type' => 'application/json; charset=UTF-8',
      'Cache-Control' => 'no-store',
    ]);
  }
}