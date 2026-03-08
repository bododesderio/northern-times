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
} catch (\Throwable) {
  $navCats = [];
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
<html lang="en">
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
  <meta name="theme-color"  content="<?= h(get_site_setting('theme_accent', '#cc0000')) ?>" />
  <meta name="x-csrf-token" content="<?= h($csrf) ?>" />
  <meta name="x-app-url"    content="<?= h(\app_url('/')) ?>" />

  <!-- DNS prefetch for external services -->
  <link rel="dns-prefetch" href="//fonts.googleapis.com" />

  <!-- Fonts — preconnect + swap to prevent FOIT -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;600;700&family=UnifrakturMaguntia&display=swap" />
  <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;600;700&family=UnifrakturMaguntia&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
  <noscript><link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;600;700&family=UnifrakturMaguntia&display=swap" rel="stylesheet"></noscript>

  <!--
    Theme CSS variables — DB-driven, injected BEFORE app.css.
  -->
  <?= get_theme_css() ?>

  <!-- Main stylesheet (preloaded for faster render) -->
  <link rel="preload" href="/assets/app.css?v=15" as="style" />
  <link rel="stylesheet" href="/assets/app.css?v=15" />
  <link rel="stylesheet" href="/assets/responsive.css?v=1" />

  <!-- Inline article image styles (figure, caption, sizing, alignment) -->
  <link rel="stylesheet" href="/assets/article.css?v=1" />

  <!-- Dark mode (CSS + JS in head to prevent flash of wrong theme) -->
  <link rel="stylesheet" href="/assets/dark-mode.css?v=1" />
  <script src="/assets/dark-mode.js?v=1"></script>

  <!-- Popups -->
  <link rel="stylesheet" href="/assets/popups.css?v=1" />

  <!-- Critical inline styles — CLS prevention + fast first paint -->
  <style>
    .skip{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}
    .skip:focus{left:18px;top:18px;width:auto;height:auto;background:var(--surface,#fff);border:1px solid var(--border,#e2e2e2);padding:10px 12px;border-radius:12px;z-index:99999}
    .masthead-logo-img{max-height:80px;width:auto;max-width:360px;display:block;margin:0 auto}
    /* Prevent layout shift on topbar location */
    #locationLabel{min-width:80px;display:inline-block}
    /* Prevent image CLS */
    img{max-width:100%;height:auto}
    img[loading="lazy"]{content-visibility:auto}
    /* Skeleton placeholder while images load */
    .img-placeholder{background:linear-gradient(110deg,#f0f0f0 8%,#e8e8e8 18%,#f0f0f0 33%);background-size:200% 100%;animation:shimmer 1.5s linear infinite}
    @keyframes shimmer{to{background-position:-200% 0}}
    /* Ad device targeting */
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
  <div class="topbar">
    <div class="topbar-inner container">
      <div class="topbar-left">
        <?php if ($locationMode !== 'off'): ?>
          <?php if ($locationMode === 'static'): ?>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="opacity:.6;vertical-align:-1px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span class="tiny" title="<?= h($locationTitle) ?>"><?= h($locationStatic) ?></span>
          <?php else: ?>
            <!-- Auto mode: server-side GeoIP, JS upgrades if browser has better data -->
            <span class="tiny" id="locationLabel" title="<?= h($locationTitle) ?>"><?php
              // Render flag emoji + location directly from PHP
              if ($geoCountry !== '' && strlen($geoCountry) === 2) {
                // Regional indicator symbols are in supplementary Unicode plane (U+1F1E6–U+1F1FF)
                // mb_chr() may fail on some PHP builds, so use pack() + mb_convert_encoding()
                $flag = '';
                for ($fi = 0; $fi < 2; $fi++) {
                  $cp = 0x1F1E6 + ord(strtoupper($geoCountry[$fi])) - 65;
                  $flag .= mb_convert_encoding(pack('N', $cp), 'UTF-8', 'UTF-32BE');
                }
                echo $flag . ' ';
              }
              echo h($geoLabel ?: 'Online');
            ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <form class="topbar-search" action="/search" method="GET" role="search" aria-label="Site search">
        <input type="search" name="q"
               placeholder="Search <?= h($siteTitle) ?>…"
               value="<?= h($searchQ) ?>" />
        <button type="submit" aria-label="Search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></button>
      </form>

      <div class="topbar-right">
        <button type="button" id="darkToggle" class="dark-toggle" aria-label="Toggle dark mode" title="Toggle dark/light mode">
          <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
        <span class="tiny"><?= h(date('l, F j, Y')) ?></span>
      </div>
    </div>
  </div>

  <!-- Masthead: ad-left | logo | ad-right -->
  <div class="masthead container">
    <div class="masthead-inner">
      <div class="masthead-ad-left">
        <?= render_ad($ads ?? [], 'masthead-left', 'ad-masthead-left') ?>
      </div>
      <div class="masthead-center">
        <?php if ($siteLogoUrl): ?>
          <a href="/" aria-label="<?= h($siteTitle) ?> — Home">
            <img src="<?= h($siteLogoUrl) ?>"
                 alt="<?= h($siteTitle) ?>"
                 class="masthead-logo-img"
                 fetchpriority="high"
                 decoding="async" />
          </a>
        <?php else: ?>
          <a class="masthead-title" href="/"><?= h($siteTitle) ?></a>
        <?php endif; ?>
        <div class="masthead-tagline"><?= h($siteTagline) ?></div>
      </div>
      <div class="masthead-ad-right">
        <?= render_ad($ads ?? [], 'masthead-right', 'ad-masthead-right') ?>
      </div>
    </div>
  </div>

  <nav class="nav" aria-label="Primary">
    <!-- Mobile row -->
    <div class="container nav-mobile">
      <button class="burger"
              id="openDrawer"
              type="button"
              aria-label="Open menu"
              aria-controls="drawer"
              aria-expanded="false">
        <span class="burger-lines" aria-hidden="true"><span></span><span></span><span></span></span>
        <span>Menu</span>
      </button>
      <a class="nav-item" href="/search">Search</a>
    </div>

    <!-- Desktop row — dynamic from DB, with active state -->
    <div class="container nav-inner">
      <a href="/" class="nav-item"
         <?= $path === '/' ? 'aria-current="page"' : '' ?>>Home</a>
      <?php foreach ($navCats as $cat): ?>
        <?php
          $catPath  = '/category/' . (string)$cat['slug'];
          $isActive = ($path === $catPath) || str_starts_with($path, $catPath . '/');
        ?>
        <a href="<?= h($catPath) ?>"
           class="nav-item"
           <?= $isActive ? 'aria-current="page"' : '' ?>><?= h($cat['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>

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
    <button class="drawer-close" id="closeDrawer" type="button" aria-label="Close menu">Close</button>
  </div>

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

<main id="content" class="container<?= $path === '/' ? ' container-home' : '' ?>">
  <?= $content ?? '' ?>
</main>

<!-- ═══ FOOTER SEPARATOR ═══ -->
<div class="footer-separator"><div class="container"><div class="footer-sep-line"></div></div></div>

<footer class="site-footer">
  <!-- Newsletter banner -->
  <?php if (get_site_setting('newsletter_enabled', '1') === '1'): ?>
  <?php $nlBg = get_site_setting('newsletter_bg_color', ''); ?>
  <div class="footer-newsletter"<?= $nlBg ? " style=\"background:{$nlBg}\"" : '' ?>>
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

  <!-- Main footer grid -->
  <div class="footer-main">
    <div class="container footer-grid">
      <div class="footer-col footer-brand-col">
        <?php if ($siteLogoUrl): ?>
          <a href="/" class="footer-logo">
            <img src="<?= h($siteLogoUrl) ?>" alt="<?= h($siteTitle) ?>" loading="lazy" decoding="async" />
          </a>
        <?php else: ?>
          <a href="/" class="footer-masthead"><?= h($siteTitle) ?></a>
        <?php endif; ?>
        <p class="footer-about">
          <?= nl2br(h(get_site_setting('footer_about', "Old-school newsroom values, modern delivery. We publish with clarity, not noise."))) ?>
        </p>
        <div class="footer-contact">
          <a href="mailto:<?= h(get_site_setting('footer_tip_line', 'news@example.com')) ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22,6 12,13 2,6"/></svg>
            <?= h(get_site_setting('footer_tip_line', 'news@example.com')) ?>
          </a>
          <a href="mailto:<?= h(get_site_setting('footer_ads', 'ads@example.com')) ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            <?= h(get_site_setting('footer_ads', 'ads@example.com')) ?>
          </a>
        </div>
      </div>

      <div class="footer-col">
        <div class="footer-heading">Sections</div>
        <nav class="footer-nav">
          <?php
            $footerCats = [];
            try {
              $footerCats = \App\Services\DB::pdo()->query("SELECT name, slug FROM categories WHERE show_in_nav = TRUE ORDER BY sort_order ASC, name ASC LIMIT 8")->fetchAll() ?: [];
            } catch (\Throwable $e) {}
          ?>
          <?php if (!empty($footerCats)): ?>
            <?php foreach ($footerCats as $fc): ?>
              <a href="/category/<?= h($fc['slug']) ?>"><?= h($fc['name']) ?></a>
            <?php endforeach; ?>
          <?php else: ?>
            <a href="/category/world">World</a>
            <a href="/category/politics">Politics</a>
            <a href="/category/business">Business</a>
            <a href="/category/technology">Technology</a>
            <a href="/category/opinion">Opinion</a>
            <a href="/category/health">Health</a>
          <?php endif; ?>
        </nav>
      </div>

      <div class="footer-col">
        <div class="footer-heading">Company</div>
        <nav class="footer-nav">
          <?php
            $footerPolicies = [];
            try {
              $footerPolicies = \App\Services\DB::pdo()
                ->query("SELECT title, slug FROM policy_pages WHERE is_published = TRUE AND show_in_footer = TRUE ORDER BY sort_order ASC, title ASC LIMIT 10")
                ->fetchAll() ?: [];
            } catch (\Throwable) {}
          ?>
          <?php foreach ($footerPolicies as $fp): ?>
            <a href="/policy/<?= h($fp['slug']) ?>"><?= h($fp['title']) ?></a>
          <?php endforeach; ?>
          <?php if (empty($footerPolicies)): ?>
            <a href="/policy/editorial-standards">Editorial Standards</a>
            <a href="/policy/privacy">Privacy Policy</a>
            <a href="/policy/terms">Terms of Use</a>
          <?php endif; ?>
        </nav>
      </div>

      <div class="footer-col">
        <div class="footer-heading">Connect</div>
        <div class="footer-socials">
          <?php
            $socials = [
              ['key' => 'social_twitter',   'label' => 'X (Twitter)', 'svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'],
              ['key' => 'social_facebook',  'label' => 'Facebook',    'svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'],
              ['key' => 'social_instagram', 'label' => 'Instagram',   'svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/></svg>'],
              ['key' => 'social_youtube',   'label' => 'YouTube',     'svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12z"/></svg>'],
            ];
            foreach ($socials as $s):
              $url = get_site_setting($s['key'], '');
              if ($url === '') continue;
          ?>
            <a href="<?= h($url) ?>" class="footer-social-link" aria-label="<?= h($s['label']) ?>" target="_blank" rel="noopener">
              <?= $s['svg'] ?>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="footer-tagline">
          Based in Northern Uganda.<br>
          Reporting for everyone who still believes words matter.
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom bar -->
  <div class="footer-bottom-bar">
    <div class="container footer-bottom-inner">
      <span><?= h(copyright_line()) ?></span>
      <span class="footer-motto">The truth shall set you free.</span>
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
</body>
</html>