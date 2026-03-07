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
    if (!$article) return new Response("404 Not Found", 404);

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
      } catch (\Throwable) {}
    }

    // Comments
    $comments = [];
    try { $comments = Comment::forArticle($article['id']); } catch (\Throwable) {}

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
    if (!$tag) return new Response("404 Not Found", 404);

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
    if (!$user) return new Response("404 Not Found", 404);

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
  public function tagSearchApi(): Response
  {
    $q = trim((string)(Request::createFromGlobals()->query->get('q', '')));

    if (mb_strlen($q) < 1) {
      return $this->json([]);
    }

    $tags = Tag::search($q, 10);
    return $this->json($tags);
  }

  /* ================================================================
     CATEGORY listing
     ================================================================ */
  public function category(string $slug): Response
  {
    $category = Category::findBySlug($slug);
    if (!$category) return new Response("404 Not Found", 404);

    $articles = Article::byCategorySlug($slug, 1, 40)['rows'];

    $meta = [
      'title'       => $category['name'] . ' — ' . $this->siteTitle(),
      'description' => $category['description'] ?: ('Latest stories in ' . $category['name'] . '.'),
      'canonical'   => \app_url('/category/' . $slug),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];

    $ads = AdSlot::activeSlots();

    return $this->layout('category', compact('category','articles','ads','meta'));
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

    $meta = [
      'title'       => ($q ? 'Search: ' . $q : 'Search') . ' — ' . $this->siteTitle(),
      'description' => 'Search the ' . $this->siteTitle() . ' archive.',
      'canonical'   => \app_url('/search?q=' . urlencode($q)),
      'og_image'    => \app_url('/assets/og-default.png'),
    ];

    return $this->layout('search', compact('q','articles','meta'));
  }

  /* ================================================================
     LIVE SEARCH API (JSON)
     ================================================================ */
  public function searchApi(): Response
  {
    $request = Request::createFromGlobals();
    $q = trim((string)$request->query->get('q',''));

    if (mb_strlen($q) < 2) {
      return $this->json(['results' => []]);
    }

    return $this->json(['results' => Article::searchApi($q, 8)]);
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
    } catch (\Throwable) {}

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
      } catch (\Throwable) {} // duplicate email is fine
    }

    return $this->json([
      'ok'      => true,
      'message' => 'Comment posted!',
      'comment' => [
        'id'          => $row['id'],
        'author_name' => $name,
        'content'     => $body,
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
                       CASE WHEN a.is_crawled = TRUE THEN '" . (function_exists('get_site_setting') ? get_site_setting('default_crawl_author', get_site_setting('site_title', 'Newsroom')) : 'Newsroom') . "' ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
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
        $feedUrl = $slug ? "{$site}/feed/category/{$slug}" : "{$site}/feed";
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

            $linkUrl = $site . '/' . $it['slug'];
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
    $xml = Cache::remember('sitemap:xml', 1800, function () {
        $site = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        $dom  = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $dom->appendChild($urlset);

        // Helper to add a <url> node
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

        // Homepage — priority 1.0, hourly
        $addUrl($site . '/', gmdate('Y-m-d\TH:i:s\Z'), 'hourly', '1.0');

        // Published articles — priority 0.8, weekly
        $articles = Article::forSitemap(50000);
        foreach ($articles as $r) {
            $lastmod = !empty($r['updated_at'])
                ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string)$r['updated_at']))
                : (!empty($r['published_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string)$r['published_at'])) : null);
            $addUrl($site . '/' . $r['slug'], $lastmod, 'weekly', '0.8');
        }

        // Active categories — priority 0.6, daily
        $categories = Category::allActive();
        foreach ($categories as $cat) {
            $lastmod = !empty($cat['updated_at'])
                ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string)$cat['updated_at']))
                : null;
            $addUrl($site . '/category/' . $cat['slug'], $lastmod, 'daily', '0.6');
        }

        // Tag pages — priority 0.5, daily (Phase 10)
        try {
            $allTags = Tag::trending(100);
            foreach ($allTags as $t) {
                $addUrl($site . '/tag/' . $t['slug'], null, 'daily', '0.5');
            }
        } catch (\Throwable) {}

        // Policy pages — priority 0.4, monthly
        $policies = ['privacy-policy', 'terms-of-service', 'cookie-policy', 'about'];
        foreach ($policies as $pSlug) {
            $addUrl($site . '/' . $pSlug, null, 'monthly', '0.4');
        }

        return $dom->saveXML();
    });

    return new Response($xml, 200, [
      'Content-Type' => 'application/xml; charset=UTF-8',
      'X-Content-Type-Options' => 'nosniff',
      'Cache-Control' => 'public, max-age=1800',
    ]);
  }

  /* ================================================================
     AD CLICK TRACKING
     ================================================================ */
  public function adClick(string $id): Response
  {
    // Log click event
    try {
      $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
      $page = $_SERVER['HTTP_REFERER'] ?? '/';
      \App\Services\DB::pdo()->prepare(
        "INSERT INTO ad_events (ad_slot_id, ip_address, event_type, page_url) VALUES (:id, :ip::inet, 'click', :page)"
      )->execute([':id' => $id, ':ip' => $ip, ':page' => mb_substr($page, 0, 500)]);
    } catch (\Throwable) {}

    $linkUrl = AdSlot::recordClick($id);

    if ($linkUrl && filter_var($linkUrl, FILTER_VALIDATE_URL)) {
      return new Response('', 302, ['Location' => $linkUrl]);
    }

    return new Response('', 302, ['Location' => '/']);
  }

  public function adImpression(): Response
  {
    try {
      $body = json_decode(file_get_contents('php://input'), true);
      $adId = $body['ad_id'] ?? '';
      $page = $body['page'] ?? '/';

      if ($adId && preg_match('/^[0-9a-f\-]{36}$/i', $adId)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        \App\Services\DB::pdo()->prepare(
          "INSERT INTO ad_events (ad_slot_id, ip_address, event_type, page_url) VALUES (:id, :ip::inet, 'impression', :page)"
        )->execute([':id' => $adId, ':ip' => $ip, ':page' => mb_substr($page, 0, 500)]);
      }
    } catch (\Throwable) {}

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
    } catch (\Throwable) {}

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

    try {
      Subscriber::subscribe($email, $name);
      return $this->json(['ok' => true, 'message' => "Subscribed! You'll get the next headlines."]);
    } catch (\Throwable) {
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
}