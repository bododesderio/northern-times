<?php
declare(strict_types=1);

$meta = $meta ?? [];

// ── Site settings ────────────────────────────────────────────────
// FIX: Use site_name() which is white-label safe (env-driven fallback,
// never hardcodes brand string). All other settings fall back neutrally.
$siteTitle   = function_exists('site_name') ? site_name() : get_site_setting('site_title', $_ENV['APP_NAME'] ?? 'News');
$siteAbbr    = get_site_setting('site_abbreviation', '')
               ?: mb_strtoupper(mb_substr(preg_replace('/\s+.*/u', '', $siteTitle), 0, 3))
               ?: 'NEWS';
$siteTagline = get_site_setting('site_tagline', '');
$siteLogoUrl = get_site_setting('site_logo_url', '');
$faviconUrl  = get_site_setting('favicon_url',   '');
$ogDefault   = get_site_setting('og_default_image', '/assets/og-default.png');
$contactEmail = get_site_setting('contact_email', '');
$publisherName = get_site_setting('publisher_name');
if ($publisherName === '') $publisherName = $siteTitle;

// Social handles for Twitter Card
$twitterHandle = get_site_setting('twitter_handle', '');

$title       = $meta['title']       ?? $siteTitle;
$description = $meta['description'] ?? $siteTagline;
$canonical   = $meta['canonical']   ?? \app_url('/');
$ogImage     = $meta['og_image']    ?? $ogDefault;

// Richer meta
$metaType      = $meta['type']           ?? 'website';
$publishedTime = $meta['published_time'] ?? null;
$modifiedTime  = $meta['modified_time']  ?? null;
$sectionName   = $meta['section']        ?? null;
$tags          = $meta['tags']           ?? [];
$articleAuthor = $meta['author_name']    ?? null;

// Search query
$searchQ = $q ?? ($_GET['q'] ?? '');

// CSRF
$csrf = \App\Services\Csrf::token();

// Helpers
if (!function_exists('json_attr')) {
  function json_attr($v): string {
    return htmlspecialchars(
      json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ENT_QUOTES | ENT_SUBSTITUTE,
      'UTF-8'
    );
  }
}

// Ensure OG image is absolute
if ($ogImage && is_string($ogImage) && str_starts_with($ogImage, '/')) {
  $ogImage = \app_url($ogImage);
}

// Current path for active nav
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// ── Location label (topbar) ──────────────────────────────────────
$locationMode   = strtolower((string) get_site_setting('location_label_mode',   'auto'));
$locationStatic = (string) get_site_setting('location_label_static', '');
$locationTitle  = (string) get_site_setting('location_label_title',  'Location');
if ($locationMode === 'static' && trim($locationStatic) === '') {
  $locationMode = 'auto';
}

// Server-side GeoIP
$geoLabel   = '';
$geoCountry = '';
if ($locationMode === 'auto') {
  try {
    $geoIp   = \App\Services\GeoIP::clientIP();
    $geoData = \App\Services\GeoIP::lookup($geoIp);
    $geoParts = array_filter([$geoData['city'] ?? '', $geoData['country_name'] ?? '']);
    $geoLabel   = implode(', ', $geoParts);
    $geoCountry = $geoData['country'] ?? '';
  } catch (\Throwable) {}
}

// ── Nav categories ───────────────────────────────────────────────
$navCats = [];
try {
  $pdo      = \App\Services\DB::pdo();
  $cacheKey = 'nt_navCats_v1';
  $cached   = function_exists('apcu_fetch') ? apcu_fetch($cacheKey) : false;

  if (is_array($cached)) {
    $navCats = $cached;
  } else {
    $navCats = $pdo
      ->query("SELECT name, slug FROM categories WHERE show_in_nav = TRUE ORDER BY sort_order ASC, name ASC")
      ->fetchAll() ?: [];
    if (function_exists('apcu_store')) {
      apcu_store($cacheKey, $navCats, 600);
    }
  }
} catch (\Throwable $e) {
  error_log('Layout nav categories: ' . $e->getMessage());
  $navCats = [];
}

// ── Sidebar categories with daily article counts ────────────────
$sidebarCats = [];
try {
  $sbCacheKey = 'nt_sidebarCats_v2';
  $sbCached   = function_exists('apcu_fetch') ? apcu_fetch($sbCacheKey) : false;

  if (is_array($sbCached)) {
    $sidebarCats = $sbCached;
  } else {
    $sidebarCats = $pdo->query("
      SELECT c.name, c.slug,
             COUNT(a.id) FILTER (WHERE a.published_at >= CURRENT_DATE) AS today_count
      FROM categories c
      LEFT JOIN articles a ON a.category_id = c.id
        AND a.status = 'published'
        AND (a.published_at IS NULL OR a.published_at <= NOW())
        AND a.deleted_at IS NULL
      WHERE c.show_in_sidebar = TRUE
      GROUP BY c.id, c.name, c.slug, c.sort_order
      ORDER BY c.sort_order ASC, c.name ASC
    ")->fetchAll() ?: [];
    if (function_exists('apcu_store')) {
      apcu_store($sbCacheKey, $sidebarCats, 300); // 5-min cache
    }
  }
} catch (\Throwable $e) {
  error_log('Layout sidebar categories: ' . $e->getMessage());
  $sidebarCats = [];
}

// ── JSON-LD structured data ──────────────────────────────────────
$siteUrl = rtrim(\app_url('/'), '/');

$ldWebsite = [
  '@context' => 'https://schema.org',
  '@type'    => 'WebSite',
  'name'     => $siteTitle,
  'url'      => $siteUrl,
  'potentialAction' => [
    '@type'  => 'SearchAction',
    'target' => [
      '@type'       => 'EntryPoint',
      'urlTemplate' => $siteUrl . '/search?q={search_term_string}',
    ],
    'query-input' => 'required name=search_term_string',
  ],
];

// UPGRADE: Richer Organization schema with white-label contact fields
$ldOrg = [
  '@context' => 'https://schema.org',
  '@type'    => 'NewsMediaOrganization',
  'name'     => $publisherName,
  'url'      => $siteUrl,
  'logo'     => [
    '@type' => 'ImageObject',
    'url'   => $siteLogoUrl ? \app_url($siteLogoUrl) : $ogImage,
  ],
];
if ($contactEmail !== '') {
  $ldOrg['email'] = $contactEmail;
}

$ldArticle = null;
if ($metaType === 'article') {
  $ldArticle = [
    '@context'         => 'https://schema.org',
    '@type'            => 'NewsArticle',
    'headline'         => $title,
    'description'      => $description,
    'image'            => [$ogImage],
    'datePublished'    => $publishedTime,
    'dateModified'     => $modifiedTime ?: $publishedTime,
    'mainEntityOfPage' => $canonical,
    'publisher'        => [
      '@type' => 'Organization',
      'name'  => $publisherName,
      'logo'  => [
        '@type' => 'ImageObject',
        'url'   => $siteLogoUrl ? \app_url($siteLogoUrl) : $ogImage,
      ],
    ],
  ];
  if ($articleAuthor) {
    $ldArticle['author'] = ['@type' => 'Person', 'name' => $articleAuthor];
  }
}
?>
<!doctype html>
<html lang="en" data-theme="<?= h(get_site_setting('theme_mode', 'system')) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title><?= h($title) ?></title>
  <meta name="description"  content="<?= h($description) ?>" />
  <meta name="robots"       content="index,follow,max-image-preview:large" />
  <link rel="canonical"     href="<?= h($canonical) ?>" />

  <!-- UPGRADE: Security meta tags (belt-and-suspenders alongside HTTP headers) -->
  <meta http-equiv="X-Content-Type-Options" content="nosniff" />
  <meta name="referrer" content="strict-origin-when-cross-origin" />

  <!-- Favicon — dynamic from branding settings -->
  <?php if ($faviconUrl): ?>
    <?php
      $ext  = strtolower(pathinfo($faviconUrl, PATHINFO_EXTENSION));
      $mime = match($ext) {
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'webp' => 'image/webp',
        default => 'image/png',
      };
    ?>
    <link rel="icon"             type="<?= h($mime) ?>" href="<?= h($faviconUrl) ?>" />
    <link rel="apple-touch-icon"                         href="<?= h($faviconUrl) ?>" />
  <?php else: ?>
    <link rel="icon" type="image/svg+xml" href="/assets/icon.svg" />
    <link rel="apple-touch-icon" href="/assets/icon.svg" />
  <?php endif; ?>

  <!-- Open Graph -->
  <meta property="og:site_name"   content="<?= h($siteTitle) ?>" />
  <meta property="og:title"       content="<?= h($title) ?>" />
  <meta property="og:description" content="<?= h($description) ?>" />
  <meta property="og:type"        content="<?= h($metaType) ?>" />
  <meta property="og:url"         content="<?= h($canonical) ?>" />
  <meta property="og:image"       content="<?= h($ogImage) ?>" />
  <meta property="og:locale"      content="<?= h(get_site_setting('og_locale', 'en_US')) ?>" />

  <?php if ($publishedTime): ?>
    <meta property="article:published_time" content="<?= h($publishedTime) ?>" />
  <?php endif; ?>
  <?php if ($modifiedTime): ?>
    <meta property="article:modified_time"  content="<?= h($modifiedTime) ?>" />
  <?php endif; ?>
  <?php if ($sectionName): ?>
    <meta property="article:section"        content="<?= h($sectionName) ?>" />
  <?php endif; ?>
  <?php if (!empty($tags) && is_array($tags)): ?>
    <?php foreach ($tags as $t): ?>
      <meta property="article:tag" content="<?= h((string)$t) ?>" />
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Twitter / X -->
  <meta name="twitter:card"        content="summary_large_image" />
  <?php if ($twitterHandle): ?><meta name="twitter:site" content="<?= h($twitterHandle) ?>" /><?php endif; ?>
  <meta name="twitter:title"       content="<?= h($title) ?>" />
  <meta name="twitter:description" content="<?= h($description) ?>" />
  <meta name="twitter:image"       content="<?= h($ogImage) ?>" />

  <!-- Feeds -->
  <link rel="alternate" type="application/rss+xml" title="<?= h($siteTitle) ?> RSS" href="/rss.xml" />

  <!-- App config -->
  <meta name="theme-color"  content="<?= h(get_site_setting('theme_accent', '#0c0a09')) ?>" />
  <link rel="manifest" href="/manifest.json" />
  <meta name="x-csrf-token" content="<?= h($csrf) ?>" />
  <meta name="x-app-url"    content="<?= h(\app_url('/')) ?>" />

  <!-- Fonts — self-hosted, no external CDN -->
  <link rel="stylesheet" href="/assets/fonts/fonts.css" />
  <!-- Material Symbols removed — all icons now use inline SVGs -->

  <!--
    Theme CSS variables — DB-driven, injected BEFORE app.css.
  -->
  <?= get_theme_css() ?>

  <!-- Main stylesheet (preloaded for faster render) -->
  <link rel="preload" href="/assets/app.css?v=22" as="style" />
  <link rel="stylesheet" href="/assets/app.css?v=22" />
  <link rel="stylesheet" href="/assets/responsive.css?v=2" />

  <!-- Inline article image styles (figure, caption, sizing, alignment) -->
  <link rel="stylesheet" href="/assets/article.css?v=1" />

  <!-- Dark mode (CSS + JS in head to prevent flash of wrong theme) -->
  <link rel="stylesheet" href="/assets/dark-mode.css?v=2" />
  <script src="/assets/dark-mode.js?v=2"></script>

  <!-- Popups -->
  <link rel="stylesheet" href="/assets/popups.css?v=1" />

  <!-- Critical inline styles — CLS prevention + fast first paint -->
  <style>
    html,body{background:var(--color-bg-primary,#0c0a09);color:var(--color-text-primary,#fafafa)}
    html:not(.dark) body{background:var(--color-bg-primary,#fafaf9);color:var(--color-text-primary,#1c1917)}
    .skip{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}
    .skip:focus{left:18px;top:18px;width:auto;height:auto;background:var(--np-surface-1,#1c1917);border:1px solid var(--np-border,#292524);padding:10px 12px;z-index:99999}
    .header-logo{max-height:40px;width:auto;display:block}
    .header-title{font-family:'Newsreader',Georgia,serif;font-size:24px;font-weight:900;line-height:1;text-transform:uppercase;letter-spacing:-.03em}
    img{max-width:100%;height:auto}
    img[loading="lazy"]{content-visibility:auto}
    .img-placeholder{background:linear-gradient(110deg,var(--np-surface-1,#1c1917) 8%,var(--np-surface-2,#282a2b) 18%,var(--np-surface-1,#1c1917) 33%);background-size:200% 100%;animation:shimmer 1.5s linear infinite}
    @keyframes shimmer{to{background-position:-200% 0}}
    @media(max-width:767px){.ad-desktop-only{display:none!important}}
    @media(min-width:768px){.ad-mobile-only{display:none!important}}
  </style>

  <!-- JSON-LD structured data -->
  <script type="application/ld+json"><?= json_encode($ldWebsite, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <script type="application/ld+json"><?= json_encode($ldOrg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <?php if ($ldArticle): ?>
    <script type="application/ld+json"><?= json_encode($ldArticle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <?php endif; ?>

  <?php if (get_site_setting('push_enabled') === '1' && get_site_setting('push_vapid_public') !== ''): ?>
  <meta name="vapid-public-key" content="<?= h(get_site_setting('push_vapid_public')) ?>">
  <meta name="site-title" content="<?= h($siteTitle ?? get_site_setting('site_title', '')) ?>">
  <script src="/assets/push-prompt.js" defer></script>
  <?php endif; ?>
  <?php $gaId = get_site_setting('analytics_id', ''); if ($gaId): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= h($gaId) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= h($gaId) ?>');</script>
  <?php endif; ?>
</head>
<body>

<!-- Reading progress bar (driven by app.js) -->
<div class="progress" aria-hidden="true"><span id="progressBar"></span></div>

<a class="skip" href="#content">Skip to content</a>

<header class="site-header">
  <!-- Top navigation bar -->
  <div class="header-bar">
    <div class="header-bar-inner container">
      <div class="header-left">
        <button class="burger" id="openDrawer" type="button" aria-label="Open menu" aria-controls="drawer" aria-expanded="false">
          <span class="burger-lines" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>
        <a class="header-brand" href="/">
          <?php if ($siteLogoUrl): ?>
            <img src="<?= h($siteLogoUrl) ?>" alt="<?= h($siteTitle) ?>" class="header-logo" fetchpriority="high" decoding="async" />
          <?php else: ?>
            <span class="header-title"><?= h($siteTitle) ?></span>
          <?php endif; ?>
        </a>
        <nav class="header-nav" aria-label="Primary">
          <?php foreach (array_slice($navCats, 0, 6) as $i => $cat): ?>
            <?php
              $catPath  = '/category/' . (string)$cat['slug'];
              $isActive = ($path === $catPath) || str_starts_with($path, $catPath . '/');
            ?>
            <a href="<?= h($catPath) ?>" class="header-nav-link<?= $isActive ? ' active' : '' ?>"><?= h($cat['name']) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="header-right">
        <div class="header-search-box">
          <input type="text" id="headerSearchInput" placeholder="Search..." autocomplete="off" />
        </div>
        <button type="button" class="header-icon-btn" id="searchToggle" aria-label="Toggle search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </button>
        <a href="#newsletter" class="header-subscribe-btn">Subscribe</a>
      </div>
    </div>
  </div>

  <!-- Expandable search bar (hidden by default, mobile + toggle fallback) -->
  <div class="search-expand" id="searchExpand" hidden>
    <form class="search-expand-inner container" action="/search" method="GET" role="search">
      <input type="search" name="q" placeholder="Search <?= h($siteTitle) ?>…" value="<?= h($searchQ) ?>" autofocus />
      <button type="submit" aria-label="Search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button type="button" class="search-close" id="searchClose" aria-label="Close search">&times;</button>
    </form>
  </div>
</header>

<!-- Editorial sidebar (desktop only) -->
<aside class="editorial-sidebar" id="editorialSidebar">
  <div class="sidebar-brand">
    <h3 class="sidebar-edition-label">EDITORIAL</h3>
    <p class="sidebar-edition-sub">Global Edition</p>
  </div>
  <nav class="sidebar-nav">
    <a href="/" class="sidebar-nav-item<?= $path === '/' ? ' active' : '' ?>">
      <span class="sidebar-nav-label">Home</span>
    </a>
    <div class="sidebar-section-title">Categories</div>
    <?php foreach ($sidebarCats as $cat): ?>
      <?php $catPath = '/category/' . (string)$cat['slug']; ?>
      <a href="<?= h($catPath) ?>" class="sidebar-nav-item<?= str_starts_with($path, $catPath) ? ' active' : '' ?>">
        <span class="sidebar-nav-label"><?= h($cat['name']) ?></span>
        <?php if ((int)($cat['today_count'] ?? 0) > 0): ?>
          <span class="sidebar-nav-badge"><?= (int)$cat['today_count'] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>

<div id="drawerBackdrop" class="drawer-backdrop"></div>

<!-- Mobile drawer -->
<aside class="drawer" id="drawer" aria-label="Mobile menu" aria-hidden="true">
  <div class="drawer-head">
    <?php if ($siteLogoUrl): ?>
      <img src="<?= h($siteLogoUrl) ?>"
           alt="<?= h($siteTitle) ?>"
           style="max-height:36px;width:auto"
           decoding="async" />
    <?php else: ?>
      <div class="drawer-title"><?= h($siteTitle) ?></div>
    <?php endif; ?>
    <button class="drawer-close" id="closeDrawer" type="button" aria-label="Close menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <form class="drawer-search" action="/search" method="GET" role="search">
    <input type="search" name="q" placeholder="Search…" value="<?= h($searchQ) ?>" />
  </form>

  <a href="/" <?= $path === '/' ? 'aria-current="page"' : '' ?>>Home</a>
  <?php foreach ($navCats as $cat): ?>
    <?php
      $catPath  = '/category/' . (string)$cat['slug'];
      $isActive = ($path === $catPath) || str_starts_with($path, $catPath . '/');
    ?>
    <a href="<?= h($catPath) ?>"
       <?= $isActive ? 'aria-current="page"' : '' ?>><?= h($cat['name']) ?></a>
  <?php endforeach; ?>

  <div class="drawer-foot">
    <div class="footer-tiny">Quick links</div>
    <div class="footer-links">
      <a href="/rss.xml">RSS</a>
      <a href="/sitemap.xml">Sitemap</a>
      <a href="/admin/login">Admin</a>
    </div>
  </div>
</aside>

<?php
  $isFullWidth = ($path === '/') || str_starts_with($path, '/article/') || str_starts_with($path, '/category/') || str_starts_with($path, '/search') || $path === '/about' || $path === '/contact';
?>
<main id="content" class="main-content<?= $isFullWidth ? '' : ' container' ?>">
  <?= $content ?? '' ?>
</main>

<footer class="site-footer">
  <!-- Newsletter banner -->
  <?php if (get_site_setting('newsletter_enabled', '1') === '1'): ?>
  <?php $nlBg = get_site_setting('newsletter_bg_color', ''); ?>
  <div id="newsletter" class="footer-newsletter"<?= $nlBg ? ' style="background:' . h($nlBg) . '"' : '' ?>>
    <div class="container footer-nl-inner">
      <div class="footer-nl-text">
        <div class="footer-nl-title"><?= h(get_site_setting('newsletter_title', 'Stay informed')) ?></div>
        <p><?= h(get_site_setting('newsletter_intro', 'Get the best stories in your inbox. No spam. No circus.')) ?></p>
      </div>
      <form id="newsletterForm" method="POST" action="/api/newsletter" class="footer-nl-form" novalidate>
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <?php if (get_site_setting('newsletter_show_name', '1') === '1'): ?>
        <input name="name" placeholder="Your name" autocomplete="name">
        <?php endif; ?>
        <input name="email" placeholder="Email address" required autocomplete="email">
        <button type="submit"><?= h(get_site_setting('newsletter_button', 'Subscribe')) ?></button>
      </form>
      <div id="newsletterMsg" style="font-size:13px;margin-top:4px"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Simplified editorial footer -->
  <div class="footer-main">
    <div class="container footer-bottom-flex">
      <div class="footer-brand-block">
        <?php if ($siteLogoUrl): ?>
          <a href="/" class="footer-logo">
            <img src="<?= h($siteLogoUrl) ?>" alt="<?= h($siteTitle) ?>" loading="lazy" decoding="async" />
          </a>
        <?php else: ?>
          <a href="/" class="footer-masthead"><?= h($siteTitle) ?></a>
        <?php endif; ?>
        <p class="footer-copyright"><?= h(copyright_line()) ?></p>
      </div>

      <nav class="footer-links-row">
        <?php
          $footerPolicies = [];
          try {
            $footerPolicies = \App\Services\DB::pdo()
              ->query("SELECT title, slug FROM policy_pages WHERE is_published = TRUE AND show_in_footer = TRUE ORDER BY sort_order ASC, title ASC LIMIT 6")
              ->fetchAll() ?: [];
          } catch (\Throwable) {}
        ?>
        <?php if (!empty($footerPolicies)): ?>
          <?php foreach ($footerPolicies as $fp): ?>
            <a href="/policy/<?= h($fp['slug']) ?>"><?= h($fp['title']) ?></a>
          <?php endforeach; ?>
        <?php else: ?>
          <a href="/policy/editorial-standards">Ethics Policy</a>
          <a href="/policy/terms">Terms of Service</a>
          <a href="/policy/privacy">Privacy</a>
        <?php endif; ?>
        <a href="/about">About</a>
        <a href="/contact">Contact</a>
      </nav>

      <div class="footer-social-icons">
        <?php
          $socials = [
            ['key' => 'social_twitter',   'label' => 'X (Twitter)',   'icon' => 'public'],
            ['key' => 'social_facebook',  'label' => 'Facebook',      'icon' => 'public'],
            ['key' => 'social_instagram', 'label' => 'Instagram',     'icon' => 'public'],
          ];
          $contactEmail = get_site_setting('contact_email', get_site_setting('footer_tip_line', ''));
          if ($contactEmail):
        ?>
          <a href="mailto:<?= h($contactEmail) ?>" class="footer-icon-btn" aria-label="Email">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
          </a>
        <?php endif; ?>
        <a href="/rss.xml" class="footer-icon-btn" aria-label="RSS Feed">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="6.18" cy="17.82" r="2.18"/><path d="M4 4.44v2.83c7.03 0 12.73 5.7 12.73 12.73h2.83c0-8.59-6.97-15.56-15.56-15.56zm0 5.66v2.83c3.9 0 7.07 3.17 7.07 7.07h2.83c0-5.47-4.43-9.9-9.9-9.9z"/></svg>
        </a>
      </div>
    </div>
  </div>
</footer>

<script>
  window.NT = <?= json_attr([
    'csrf'         => $csrf,
    'appUrl'       => \app_url('/'),
    'locationMode' => $locationMode,
    'siteTitle'    => $siteTitle,
    'siteAbbr'     => $siteAbbr,
  ]) ?>;
</script>

<script>
(function () {
  'use strict';

  // ── Drawer: full accessibility ────────────────────────────────
  const drawer  = document.getElementById('drawer');
  const openBtn = document.getElementById('openDrawer');
  const closeBtn = document.getElementById('closeDrawer');

  if (drawer && openBtn && closeBtn) {
    let lastFocus = null;

    function openDrawer() {
      lastFocus = document.activeElement;
      drawer.setAttribute('aria-hidden', 'false');
      openBtn.setAttribute('aria-expanded', 'true');
      drawer.classList.add('open');
      document.body.classList.add('drawer-open');
      closeBtn.focus();
    }

    function closeDrawer() {
      drawer.setAttribute('aria-hidden', 'true');
      openBtn.setAttribute('aria-expanded', 'false');
      drawer.classList.remove('open');
      document.body.classList.remove('drawer-open');
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }

    openBtn.addEventListener('click', openDrawer);
    closeBtn.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && drawer.getAttribute('aria-hidden') === 'false') {
        closeDrawer();
      }
    });

    // Click outside drawer to close
    document.addEventListener('click', (e) => {
      if (drawer.getAttribute('aria-hidden') === 'false') {
        if (!drawer.contains(e.target) && !openBtn.contains(e.target)) {
          closeDrawer();
        }
      }
    });
  }

  // ── Search toggle ────────────────────────────────────────────
  const searchToggle = document.getElementById('searchToggle');
  const searchExpand = document.getElementById('searchExpand');
  const searchClose = document.getElementById('searchClose');
  const headerSearchInput = document.getElementById('headerSearchInput');

  // Desktop inline search — Enter submits
  if (headerSearchInput) {
    headerSearchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && headerSearchInput.value.trim()) {
        window.location.href = '/search?q=' + encodeURIComponent(headerSearchInput.value.trim());
      }
    });
  }

  // Mobile search toggle (expandable fallback)
  if (searchToggle && searchExpand) {
    searchToggle.addEventListener('click', () => {
      const open = !searchExpand.hidden;
      searchExpand.hidden = open;
      if (!open) searchExpand.querySelector('input')?.focus();
    });
    searchClose?.addEventListener('click', () => { searchExpand.hidden = true; });
  }

  // ── Location label (auto mode — JS upgrade only) ──────────────
  // Server already rendered location via PHP GeoIP. JS only upgrades
  // if /api/geo returns better data from a real public IP.
  const locationMode = (window.NT && window.NT.locationMode) || 'off';
  const labelEl = document.getElementById('locationLabel');

  if (locationMode === 'auto' && labelEl) {
    function countryFlag(code) {
      if (!code || code.length !== 2) return '';
      const base = 0x1F1E6;
      return String.fromCodePoint(
        base + code.charCodeAt(0) - 65,
        base + code.charCodeAt(1) - 65
      );
    }

    // Only upgrade if API returns a real city (not empty fallback)
    fetch('/api/geo', { credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (data && data.city && data.country_name) {
          const flag = data.country ? countryFlag(data.country) + ' ' : '';
          labelEl.textContent = flag + data.city + ', ' + data.country_name;
        }
      })
      .catch(() => {}); // keep server-rendered value
  }
})();
</script>

<link rel="preload" href="/assets/app.js?v=2" as="script" />
<script src="/assets/app.js?v=2" defer></script>
<script src="/assets/popups.js?v=1" defer></script>
<script>
// Lazy image fade-in
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.article-body img[loading="lazy"]').forEach(function(img){
    if(img.complete){img.classList.add('loaded')}
    else{img.addEventListener('load',function(){this.classList.add('loaded')})}
  });
});

// Global broken image handler — replaces failed images with gradient placeholder
(function(){
  var abbr=(window.NT&&window.NT.siteAbbr)||'';
  var svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#1a1a2e"/><stop offset="100%" style="stop-color:#16213e"/></linearGradient></defs><rect fill="url(#g)" width="800" height="450"/><text x="400" y="225" fill="#555" font-family="system-ui,sans-serif" font-size="36" text-anchor="middle" font-weight="700">'+abbr+'</text></svg>';
  var placeholder='data:image/svg+xml,'+encodeURIComponent(svg);
  document.addEventListener('error',function(e){
    var t=e.target;
    if(t.tagName==='IMG'&&!t.dataset.fallback){
      t.dataset.fallback='1';
      t.src=placeholder;
      t.style.objectFit='cover';
    }
  },true);
})();
</script>
<script>
(function(){
  if(!window.IntersectionObserver)return;
  var tracked={};
  var obs=new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(!e.isIntersecting)return;
      var id=e.target.dataset.adId;
      if(!id||tracked[id])return;
      tracked[id]=true;
      navigator.sendBeacon('/api/ad-impression',JSON.stringify({ad_id:id,page:location.pathname}));
    });
  },{threshold:0.5});
  document.querySelectorAll('.ad-slot[data-ad-id]').forEach(function(el){
    if(el.dataset.adId)obs.observe(el);
  });
})();
</script>
<script>
(function(){
  if(!navigator.geolocation||sessionStorage.getItem('_geo_sent'))return;
  navigator.geolocation.getCurrentPosition(function(pos){
    sessionStorage.setItem('_geo_sent','1');
    var ct = document.querySelector('meta[name="x-csrf-token"]');
    fetch('/api/visitor-location',{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':ct?ct.content:''},
      body:JSON.stringify({lat:pos.coords.latitude,lon:pos.coords.longitude})
    }).catch(function(){});
  },function(err){
    console.log('Geolocation denied or unavailable:',err.message);
    sessionStorage.setItem('_geo_sent','1');
  },{enableHighAccuracy:true,timeout:10000,maximumAge:300000});
})();
</script>
<script>
(function(){
  document.querySelectorAll('.follow-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      var email = form.querySelector('input[type="email"]').value;
      var type = form.getAttribute('data-type');
      var id = form.getAttribute('data-id');
      var msg = form.querySelector('.follow-msg');
      var csrf = document.querySelector('meta[name="x-csrf-token"]');
      fetch('/api/follow-topic', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':csrf?csrf.content:''},
        body: 'email='+encodeURIComponent(email)+'&type='+encodeURIComponent(type)+'&id='+encodeURIComponent(id)+'&_csrf='+(csrf?encodeURIComponent(csrf.content):'')
      }).then(function(r){return r.json()}).then(function(d){
        msg.textContent = d.message || 'Subscribed!';
        msg.style.color = d.ok ? 'green' : '#c00';
        if(d.ok) form.querySelector('input[type="email"]').value = '';
      }).catch(function(){msg.textContent='Network error';msg.style.color='#c00'});
    });
  });
})();
</script>
</body>
</html>