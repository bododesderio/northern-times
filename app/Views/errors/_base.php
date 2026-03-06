<?php
/**
 * Shared error page template — newspaper editorial design.
 *
 * FIX (T-05): body background was hardcoded to #0a0a0a (always dark).
 *             Now reads theme via get_theme_css() and CSS variables.
 *             Page respects the DB theme_mode setting just like the
 *             frontend layout — light, dark, or system.
 *
 * Expected variables (set by the including file):
 *   $code    — HTTP status code (404, 500, etc.)
 *   $title   — Short error title
 *   $message — Friendly explanation
 *   $actions — Array of ['href'=>..., 'label'=>..., 'icon'=>'<svg...>', 'primary'=>bool]
 *   $detail  — (optional) debug detail, shown only when APP_DEBUG=true
 */

// White-label safe: use site_name() helper if available, fall back to DB then env.
if (function_exists('site_name')) {
    $siteName = site_name();
} elseif (function_exists('get_site_setting')) {
    $siteName = get_site_setting('site_title', $_ENV['APP_NAME'] ?? '');
} else {
    $siteName = $_ENV['APP_NAME'] ?? '';
}

// Logo — same logic as frontend layout
$siteLogoUrl = function_exists('get_site_setting') ? get_site_setting('site_logo_url', '') : '';

// Favicon
$faviconUrl = function_exists('get_site_setting') ? get_site_setting('favicon_url', '') : '';

// Accent colour for rule and badge (falls back to brand red if no theme loaded)
$accent = function_exists('get_site_setting') ? get_site_setting('theme_accent', '#cc0000') : '#cc0000';

$isDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Copyright line — white-label safe
$copyrightLine = function_exists('copyright_line')
    ? copyright_line()
    : ('© ' . date('Y') . ($siteName ? ' ' . $siteName . '.' : '') . ' All rights reserved.');
?>
<!DOCTYPE html>
<!--
  FIX (T-05): data-theme is set from DB theme_mode so error pages participate
  in the same three-tier theme architecture as the main frontend.
-->
<html lang="en" data-theme="<?= htmlspecialchars(
    function_exists('get_site_setting') ? get_site_setting('theme_mode', 'light') : 'light'
) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= (int)$code ?> — <?= htmlspecialchars($title) ?><?= $siteName ? ' | ' . htmlspecialchars($siteName) : '' ?></title>

<?php if ($faviconUrl): ?>
  <?php
    $fext  = strtolower(pathinfo($faviconUrl, PATHINFO_EXTENSION));
    $fmime = match($fext) { 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'webp' => 'image/webp', default => 'image/png' };
  ?>
  <link rel="icon" type="<?= htmlspecialchars($fmime) ?>" href="<?= htmlspecialchars($faviconUrl) ?>">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">

<!--
  FIX (T-05): get_theme_css() emits the :root CSS variable block driven by
  the DB theme settings. This must load before the <style> block below so
  that var(--paper), var(--ink) etc. resolve to the correct theme values.
  When DB is unavailable (bootstrap error), get_theme_css() returns ''
  and the fallback values in the CSS var() calls below take effect.
-->
<?php if (function_exists('get_theme_css')) echo get_theme_css(); ?>

<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  /*
    FIX (T-05): background and color now use CSS variables from get_theme_css().
    Fallback values match the light-mode defaults so the page is never
    stuck dark when the DB setting is light.

    The error page keeps its newspaper aesthetic in BOTH themes:
    - Light mode: off-white paper (#fdfdfd) with dark ink — clean, editorial
    - Dark mode:  deep charcoal (#1a1a1a) with light ink — same editorial feel
    - System:     follows OS via the @media block emitted by get_theme_css()
  */
  body {
    font-family: "Source Sans 3", system-ui, sans-serif;
    background: var(--paper, #fdfdfd);
    color: var(--ink, #121212);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
    position: relative;
    transition: background 220ms ease, color 220ms ease;
  }

  /* Newspaper texture overlay — adapts opacity to theme */
  body::before {
    content: '';
    position: fixed; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='60' height='60' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
    pointer-events: none; z-index: 0;
  }

  /* Accent rule — uses DB accent colour via CSS variable */
  .rule-top { height: 4px; background: var(--accent, <?= htmlspecialchars($accent) ?>); width: 100%; position: relative; z-index: 1; }
  .rule-top::after { content: ''; display: block; height: 1px; background: var(--accent, <?= htmlspecialchars($accent) ?>); margin-top: 3px; }

  /* Header */
  .err-header { text-align: center; padding: 24px 24px 0; position: relative; z-index: 1; }
  .err-header a {
    font-family: "Playfair Display", Georgia, serif;
    font-size: 22px; font-weight: 900;
    color: var(--ink, #121212);
    text-decoration: none; letter-spacing: 1px; text-transform: uppercase;
  }
  .err-header .dateline {
    font-size: 11px; color: var(--muted, #666666); margin-top: 6px;
    text-transform: uppercase; letter-spacing: 2px; font-weight: 600;
  }
  .err-logo-img { max-height: 40px; width: auto; display: block; margin: 0 auto; }

  /* Divider */
  .err-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border, #e2e2e2) 20%, var(--border, #e2e2e2) 80%, transparent);
    margin: 20px auto; max-width: 600px;
  }

  /* Main */
  .err-main {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 0 24px 48px; position: relative; z-index: 1;
  }

  /* Giant error code watermark */
  .err-code {
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(100px, 20vw, 220px);
    font-weight: 900; color: var(--accent, <?= htmlspecialchars($accent) ?>);
    line-height: 0.85; letter-spacing: -6px;
    opacity: 0.10; position: absolute;
    top: 50%; transform: translateY(-65%);
    user-select: none;
  }

  /* Content block */
  .err-content {
    position: relative; text-align: center; max-width: 520px;
    animation: fadeUp 0.6s ease-out;
  }
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* Error badge */
  .err-badge {
    display: inline-block;
    background: var(--accent, <?= htmlspecialchars($accent) ?>); color: #fff;
    font-size: 11px; font-weight: 700;
    padding: 4px 14px; border-radius: 2px;
    text-transform: uppercase; letter-spacing: 2px;
    margin-bottom: 20px;
  }

  /* Headline */
  .err-headline {
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(28px, 5vw, 44px);
    font-weight: 900; line-height: 1.1;
    margin-bottom: 16px;
    color: var(--ink, #121212);
  }

  /* Body text */
  .err-body { font-size: 17px; line-height: 1.7; color: var(--muted, #666666); margin-bottom: 32px; }

  /* Action buttons */
  .err-actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }

  .err-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 28px; border-radius: 6px;
    font-size: 14px; font-weight: 600; text-decoration: none;
    transition: all 0.2s; border: none; cursor: pointer; font-family: inherit;
  }
  .err-btn-primary {
    background: var(--accent, <?= htmlspecialchars($accent) ?>); color: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.2);
  }
  .err-btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
  .err-btn-secondary {
    background: var(--surface, #ffffff);
    color: var(--ink, #121212);
    border: 1px solid var(--border, #e2e2e2);
  }
  .err-btn-secondary:hover { background: var(--paper, #fdfdfd); border-color: var(--muted, #666666); }
  .err-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

  /* Debug block */
  .err-debug {
    margin-top: 32px; padding: 16px;
    background: var(--surface, #f6f6f6);
    border: 1px solid var(--border, #e2e2e2);
    border-radius: 6px; text-align: left; max-width: 600px; width: 100%;
    font-family: "SF Mono", Consolas, monospace; font-size: 12px;
    color: var(--muted, #666666); overflow-x: auto; word-break: break-all;
    animation: fadeUp 0.8s ease-out;
  }
  .err-debug strong { color: var(--accent, <?= htmlspecialchars($accent) ?>); }

  /* Footer */
  .err-footer {
    text-align: center; padding: 20px;
    font-size: 12px; color: var(--muted, #666666); position: relative; z-index: 1;
  }

  /* Decorative side lines */
  .err-main::before, .err-main::after {
    content: ''; position: absolute; top: 20%; height: 60%;
    width: 1px;
    background: linear-gradient(180deg, transparent, var(--border, #e2e2e2) 30%, var(--border, #e2e2e2) 70%, transparent);
  }
  .err-main::before { left: 5%; }
  .err-main::after  { right: 5%; }

  /* Dark mode component adjustments — mirrors app.css html.dark pattern */
  html.dark .err-btn-secondary,
  html[data-theme="dark"] .err-btn-secondary {
    background: var(--surface, #242424);
    border-color: var(--border, #3a3a3a);
    color: var(--ink, #e0e0e0);
  }

  @media (max-width: 640px) {
    .err-main::before, .err-main::after { display: none; }
    .err-code { font-size: 120px; letter-spacing: -3px; }
  }
</style>

<!--
  dark-mode.js reads data-theme from <html> (set above from DB).
  This ensures the JS toggle on error pages works correctly and
  doesn't apply OS dark when the DB setting is 'light'.
  The script is only loaded if the asset exists — error pages may
  render before the app is fully bootstrapped.
-->
<script>
// Inline theme bootstrap — mirrors dark-mode.js logic without requiring the asset.
// Prevents FOUC on error pages where dark-mode.js may not be loadable.
(function(){
  var stored = null;
  try { stored = localStorage.getItem('nt-theme'); } catch(e){}
  var serverMode = document.documentElement.getAttribute('data-theme') || 'light';
  var apply = false;
  if (stored === 'dark') {
    apply = true;
  } else if (!stored && serverMode === 'dark') {
    apply = true;
  } else if (!stored && serverMode === 'system') {
    apply = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  if (apply) document.documentElement.classList.add('dark');
})();
</script>
</head>
<body>
  <div class="rule-top"></div>

  <div class="err-header">
    <a href="/">
      <?php if ($siteLogoUrl): ?>
        <img src="<?= htmlspecialchars($siteLogoUrl) ?>"
             alt="<?= htmlspecialchars($siteName) ?>"
             class="err-logo-img"
             decoding="async">
      <?php else: ?>
        <?= htmlspecialchars($siteName) ?>
      <?php endif; ?>
    </a>
    <div class="dateline"><?= date('l, F j, Y') ?></div>
  </div>

  <div class="err-divider"></div>

  <main class="err-main">
    <div class="err-code"><?= (int)$code ?></div>
    <div class="err-content">
      <span class="err-badge">Error <?= (int)$code ?></span>
      <h1 class="err-headline"><?= htmlspecialchars($title) ?></h1>
      <p class="err-body"><?= htmlspecialchars($message) ?></p>
      <div class="err-actions">
        <?php foreach ($actions as $i => $act): ?>
          <a href="<?= htmlspecialchars($act['href']) ?>"
             class="err-btn <?= ($act['primary'] ?? ($i === 0)) ? 'err-btn-primary' : 'err-btn-secondary' ?>">
            <?= $act['icon'] ?? '' ?>
            <?= htmlspecialchars($act['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($isDebug && !empty($detail)): ?>
        <div class="err-debug">
          <strong>Debug:</strong><br>
          <?= nl2br(htmlspecialchars($detail)) ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <div class="err-footer">
    <?= htmlspecialchars($copyrightLine) ?>
  </div>
</body>
</html>