<?php
declare(strict_types=1);

use App\Services\Auth;

$user         = Auth::user();
$activeNav    = $activeNav    ?? '';
$pageTitle    = $pageTitle    ?? 'Admin';
$hideAdminNav = $hideAdminNav ?? false;

// White-label safe: site_name() falls back to APP_NAME env, never hardcodes brand.
$siteTitle  = function_exists('site_name') ? site_name() : ($_ENV['APP_NAME'] ?? 'Admin');
$adminLogo     = function_exists('get_site_setting') ? get_site_setting('admin_logo', get_site_setting('site_logo_url', '')) : '';
$sidebarTitle  = function_exists('get_site_setting') ? get_site_setting('admin_sidebar_title', '') : '';
$sidebarTitle  = $sidebarTitle !== '' ? $sidebarTitle : $siteTitle;
$sidebarSub    = function_exists('get_site_setting') ? get_site_setting('admin_sidebar_subtitle', '') : '';
$sidebarSub    = $sidebarSub !== '' ? $sidebarSub : (get_site_setting('site_abbreviation', 'NT') . ' Newsroom');
$faviconUrl = function_exists('get_site_setting') ? get_site_setting('favicon_url', '') : '';

$role         = $user['role'] ?? 'author';
$isSuperAdmin = $role === 'super_admin';
$initials     = strtoupper(substr($user['username'] ?? 'A', 0, 1));

$roleColors = [
  'super_admin' => ['bg' => '#c00',    'label' => 'Super Admin'],
  'editor'      => ['bg' => '#1a6bbf', 'label' => 'Editor'],
  'author'      => ['bg' => '#2d8a4e', 'label' => 'Author'],
  'contributor' => ['bg' => '#7c5cbf', 'label' => 'Contributor'],
];
$roleStyle = $roleColors[$role] ?? $roleColors['author'];

// ── FIX (T-01, T-02): Resolve admin theme mode ───────────────────
// Priority:
//   1. Admin-specific pref cookie 'nt-admin-theme' = 'light' | 'dark'
//   2. Global DB theme_mode setting
//   3. Default: 'light' (admin defaults to light for editorial clarity)
//
// This replaces the old @media(prefers-color-scheme:dark) !important block
// (Bug T-02) which hardcoded OS-dark overrides that bypassed DB settings,
// and the hardcoded --sbg:#0f0f0f (Bug T-01) which locked the sidebar dark.
//
// The admin sidebar intentionally stays dark-toned in both light and dark mode
// (editorial convention: dark sidebar = focused writing environment).
// Only the main content area switches with the theme.
$adminThemeCookie = $_COOKIE['nt-admin-theme'] ?? '';
$dbThemeMode      = function_exists('get_site_setting') ? get_site_setting('theme_mode', 'light') : 'light';
if ($adminThemeCookie === 'dark' || $adminThemeCookie === 'light') {
    $adminThemeMode = $adminThemeCookie;
} elseif ($dbThemeMode === 'dark') {
    $adminThemeMode = 'dark';
} else {
    $adminThemeMode = 'light'; // admin defaults light regardless of system/auto setting
}
$adminIsDark = ($adminThemeMode === 'dark');

function adminSvg(string $name): string {
  $icons = [
    'dashboard'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
    'articles'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>',
    'archive'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
    'media'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>',
    'categories'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
    'comments'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'ads'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
    'subscribers' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'policies'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'settings'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    'users'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'roles'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'review'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><path d="M9 15l2 2 4-4"/></svg>',
    'newsletter'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'notifications'=> '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
    'analytics'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
    'popups'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="13" y2="13"/></svg>',
    'popup-analytics' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>',
    'crawler'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
    'crawler-logs' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    'crawler-settings' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    'crawler-seo'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/><line x1="11" y1="8" x2="11" y2="14"/></svg>',
    'crawler-social'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
    'social-posts'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>',
    'push-settings'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
    'logout'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    'system'        => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
    'login-quotes'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>',
    'performance' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>',
    'engagement'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    'syndication' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>',
    'followers'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
    'external'    => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
    'burger'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
    'theme-light' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>',
    'theme-dark'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>',
  ];
  return $icons[$name] ?? '';
}

function sidebarLink(string $key, string $label, string $href, string $active, bool $newTab = false, string $badgeCount = ''): string {
  $icon  = adminSvg($key);
  $cls   = ($active === $key) ? 'nt-nav-link active' : 'nt-nav-link';
  $ext   = $newTab ? ' target="_blank" rel="noopener"' : '';
  $esc   = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $extBadge = $newTab ? '<span class="nt-ext-icon">'.adminSvg('external').'</span>' : '';
  $cntBadge = $badgeCount !== '' ? '<span class="nt-nav-badge red">'.$esc($badgeCount).'</span>' : '';
  return '<a href="'.$esc($href).'" class="'.$esc($cls).'"'.$ext.'>'
       . '<span class="nt-nav-icon">'.$icon.'</span>'
       . '<span class="nt-nav-label">'.$esc($label).'</span>'
       . $cntBadge
       . $extBadge
       . '</a>';
}
?>
<!doctype html>
<!--
  FIX (T-01): --sbg is no longer hardcoded to #0f0f0f.
              The sidebar design intentionally stays dark-toned, but the
              value is now a CSS variable (--adm-sb-bg) controlled by the
              theme system, allowing white-label overrides via site settings.
  FIX (T-02): The @media(prefers-color-scheme:dark) block with !important
              overrides has been REMOVED. OS dark no longer bypasses DB
              settings. Admin theme is now controlled by:
                1. nt-admin-theme cookie (user's manual toggle in topbar)
                2. DB theme_mode setting
                3. Default: light
-->
<html lang="en" data-adm-theme="<?= $adminIsDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="csrf-token" content="<?= h(\App\Services\Csrf::token()) ?>"/>
  <title><?= h($pageTitle) ?> — <?= h($siteTitle) ?> Admin</title>

  <?php if ($faviconUrl): ?>
    <?php
      $ext  = strtolower(pathinfo($faviconUrl, PATHINFO_EXTENSION));
      $mime = match($ext) { 'ico' => 'image/x-icon', 'svg' => 'image/svg+xml', 'png' => 'image/png', default => 'image/png' };
    ?>
    <link rel="icon" type="<?= h($mime) ?>" href="<?= h($faviconUrl) ?>" />
  <?php endif; ?>

  <link rel="stylesheet" href="/assets/fonts/fonts.css"/>

  <?= get_theme_css() ?>

  <style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  /* ── ADMIN THEME TOKENS ──────────────────────────────────────────
     Light defaults. html[data-adm-theme="dark"] overrides below.
     --adm-sb-bg is the sidebar background. INTENTIONALLY dark-toned
     in both light and dark mode — but no longer hardcoded to #0f0f0f.
     White-label installations can override via get_theme_css() or a
     site_settings key like admin_sidebar_color in a future migration.
     ────────────────────────────────────────────────────────────── */
  :root {
    --sw:           260px;

    /* FIX T-01: --sbg replaced with --adm-sb-bg token system.
       Sidebar stays intentionally dark but is no longer hardcoded.
       In light mode: deep charcoal. In dark mode: deepens slightly.
       These values are overridden by html[data-adm-theme="dark"] below. */
    --adm-sb-bg:    #1a1b1e;
    --sborder:      rgba(255,255,255,.08);
    --shover:       rgba(255,255,255,.06);
    --sactive:      rgba(255,255,255,.13);
    --stext:        rgba(255,255,255,.82);
    --smuted:       rgba(255,255,255,.38);

    /* Main content area — light defaults */
    --adm-bg:       #f2f2f3;
    --adm-surface:  #ffffff;
    --adm-ink:      #1a1a1a;
    --adm-border:   #e2e2e2;
    --adm-muted:    #666666;

    --adm-shadow:   0 4px 18px rgba(0,0,0,.07);
    --adm-shadow-lg:0 12px 40px rgba(0,0,0,.10);
  }

  /* ── ADMIN DARK MODE ─────────────────────────────────────────────
     Applied via PHP-resolved data-adm-theme="dark" on <html>.
     No !important. No @media query. No OS bypass.
     Controlled cleanly by DB setting + admin pref cookie only.
     ────────────────────────────────────────────────────────────── */
  html[data-adm-theme="dark"] {
    --adm-sb-bg:    #111113;
    --adm-bg:       #111113;
    --adm-surface:  #1c1c1e;
    --adm-ink:      #e8e8ea;
    --adm-border:   #2c2c2e;
    --adm-muted:    #8e8e93;
    color-scheme: dark;
  }

  /* ── BASE ────────────────────────────────────────────────────── */
  html, body {
    height: 100%;
    background: var(--adm-bg);
    color: var(--adm-ink);
    font-family: 'Libre Franklin', system-ui, -apple-system, sans-serif;
    font-size: 15px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    transition: background 220ms ease, color 220ms ease;
  }
  a { color: inherit; text-decoration: none; }
  img { max-width: 100%; display: block; object-fit: cover; }
  button, input, select, textarea { font: inherit; }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition: none !important; animation: none !important; }
  }

  /* ── LAYOUT SHELL ───────────────────────────────────────────── */
  .nt-shell { display: flex; min-height: 100vh; }

  /* ── SIDEBAR ─────────────────────────────────────────────────
     Uses --adm-sb-bg which is now adaptive (not hardcoded #0f0f0f).
     Sidebar stays dark-toned in both themes by design.
     ────────────────────────────────────────────────────────── */
  .nt-sidebar {
    width: var(--sw);
    background: var(--adm-sb-bg);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 200;
    transition: transform var(--speed, .18s) var(--ease, cubic-bezier(.2,.8,.2,1)),
                background 220ms ease;
    scrollbar-width: none;
  }
  .nt-sidebar::-webkit-scrollbar { display: none; }

  .nt-logo { padding: 22px 20px 18px; border-bottom: 1px solid var(--sborder); flex-shrink: 0; }
  .nt-logo a { display: block; }
  .nt-logo-text { font-family: 'UnifrakturMaguntia', Georgia, serif; font-size: 19px; color: #fff; line-height: 1; letter-spacing: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 200px; transition: opacity var(--speed, .18s); }
  .nt-logo a:hover .nt-logo-text { opacity: .75; }
  .nt-logo-img { max-height: 36px; width: auto; max-width: 100%; object-fit: contain; filter: brightness(0) invert(1); }
  .nt-logo-sub { margin-top: 5px; font-size: 10px; color: var(--smuted); letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; }

  .nt-nav { flex: 1; padding: 14px 10px; display: flex; flex-direction: column; gap: 1px; }
  .nt-nav-section { margin: 14px 0 3px; padding: 0 10px; font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--smuted); }
  .nt-nav-divider { height: 1px; background: var(--sborder); margin: 8px 0; }
  .nt-nav-link { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px; color: var(--stext); font-size: 14px; font-weight: 500; transition: background var(--speed, .18s) var(--ease, cubic-bezier(.2,.8,.2,1)), color var(--speed, .18s) var(--ease, cubic-bezier(.2,.8,.2,1)), transform var(--speed, .18s); position: relative; overflow: hidden; }
  .nt-nav-link::before { content: ''; position: absolute; left: 0; top: 18%; bottom: 18%; width: 3px; border-radius: 0 3px 3px 0; background: var(--accent, #cc0000); transform: scaleY(0) scaleX(0); transition: transform var(--speed, .18s) var(--ease, cubic-bezier(.2,.8,.2,1)); }
  .nt-nav-link:hover { background: var(--shover); color: #fff; }
  .nt-nav-link:hover::before { transform: scaleY(1) scaleX(1); }
  .nt-nav-link.active { background: var(--sactive); color: #fff; }
  .nt-nav-link.active::before { transform: scaleY(1) scaleX(1); }
  .nt-nav-link:active { transform: scale(.97); }

  .admin-avatar, .nt-user-avatar { border-radius: 50%; object-fit: cover; flex-shrink: 0; }

  .nt-nav-icon { display: flex; align-items: center; flex-shrink: 0; opacity: .65; transition: opacity var(--speed, .18s); }
  .nt-nav-link:hover .nt-nav-icon, .nt-nav-link.active .nt-nav-icon { opacity: 1; }
  .nt-nav-label { flex: 1; }
  .nt-nav-badge { font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px; display: flex; align-items: center; justify-content: center; padding: 0 5px; flex-shrink: 0; }
  .nt-nav-badge.green { background: #22c55e22; color: #22c55e; }
  .nt-nav-badge.red   { background: #cc000022; color: #cc0000; }
  .nt-nav-group { margin: 0; }
  .nt-nav-group-toggle { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px; color: var(--stext); font-size: 14px; font-weight: 500; cursor: pointer; background: none; border: none; width: 100%; text-align: left; transition: background var(--speed, .18s); }
  .nt-nav-group-toggle:hover { background: var(--shover); color: #fff; }
  .nt-nav-group-arrow { display: flex; transition: transform .2s ease; }
  .nt-nav-group.open .nt-nav-group-arrow { transform: rotate(90deg); }
  .nt-nav-group-items { max-height: 0; overflow: hidden; transition: max-height .3s ease; }
  .nt-nav-group.open .nt-nav-group-items { max-height: 600px; }
  .nt-nav-group-items .nt-nav-link { padding-left: 38px; font-size: 13px; }
  .nt-ext-icon { display: flex; align-items: center; opacity: .35; }

  .nt-profile-wrap { padding: 12px 10px; border-top: 1px solid var(--sborder); flex-shrink: 0; }
  .nt-profile-card { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px; background: rgba(255,255,255,.04); transition: background var(--speed, .18s); }
  .nt-profile-card:hover { background: rgba(255,255,255,.09); }
  .nt-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; color: #fff; }
  .nt-profile-info { flex: 1; min-width: 0; }
  .nt-profile-name { color: #fff; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .nt-role-badge { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .04em; padding: 2px 7px; border-radius: 99px; margin-top: 2px; }
  .nt-logout { margin: 6px 10px 18px; display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px; color: rgba(255,110,110,.65); font-size: 14px; font-weight: 500; transition: background var(--speed, .18s), color var(--speed, .18s); cursor: pointer; }
  .nt-logout:hover { background: rgba(200,0,0,.14); color: #ff7070; }

  /* ── OVERLAY (mobile) ───────────────────────────────────────── */
  .nt-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 199; opacity: 0; transition: opacity var(--speed, .18s); }

  /* ── MAIN AREA ──────────────────────────────────────────────── */
  .nt-main { flex: 1; margin-left: var(--sw); display: flex; flex-direction: column; min-height: 100vh; background: var(--adm-bg); transition: background 220ms ease; }

  /* ── TOPBAR ─────────────────────────────────────────────────── */
  .nt-topbar {
    position: sticky; top: 0; z-index: 100;
    background: var(--adm-surface);
    border-bottom: 1px solid var(--adm-border);
    padding: 0 26px; height: 56px;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    transition: background 220ms ease, border-color 220ms ease;
  }
  .nt-topbar-left { display: flex; align-items: center; gap: 12px; }
  .nt-burger { display: none; background: none; border: 1px solid var(--adm-border); border-radius: 8px; padding: 7px; cursor: pointer; color: var(--adm-ink); transition: background var(--speed, .18s); align-items: center; justify-content: center; }
  .nt-burger:hover { background: var(--adm-border); }
  .nt-breadcrumb { font-size: 14px; color: var(--adm-muted); }
  .nt-breadcrumb strong { color: var(--adm-ink); font-weight: 600; }
  .nt-topbar-right { display: flex; align-items: center; gap: 12px; }
  .nt-live-btn { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--adm-muted); border: 1px solid var(--adm-border); border-radius: 8px; padding: 6px 12px; transition: all var(--speed, .18s); }
  .nt-live-btn:hover { color: var(--adm-ink); border-color: var(--adm-muted); background: var(--adm-bg); }
  .nt-tb-avatar { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 99px; border: 1px solid var(--adm-border); transition: all var(--speed, .18s); color: var(--adm-ink); }
  .nt-tb-avatar:hover { background: var(--adm-bg); border-color: var(--adm-muted); }
  .nt-tb-av-circle { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0; }
  .nt-tb-av-name { font-size: 13px; font-weight: 500; }

  /* Theme is admin-controlled via Settings > Appearance (light/dark/os). No user toggle. */

  /* ── CONTENT ─────────────────────────────────────────────────── */
  .nt-content {
    flex: 1; padding: 28px 32px;
    display: flex; flex-direction: column; align-items: center;
    animation: ntFadeUp .2s var(--ease, cubic-bezier(.2,.8,.2,1)) both;
    background: var(--adm-bg);
    transition: background 220ms ease;
  }
  .nt-content > * { width: 100%; max-width: 1200px; }
  @keyframes ntFadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes ntSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

  /* ── LOGIN LAYOUT ───────────────────────────────────────────── */
  .nt-shell.login .nt-sidebar, .nt-shell.login .nt-topbar { display: none; }
  .nt-shell.login .nt-main { margin-left: 0; }
  .nt-shell.login .nt-content { padding: 0; align-items: stretch; }
  .nt-shell.login .nt-content > * { max-width: none; }

  /* ── CARDS ──────────────────────────────────────────────────── */
  .card {
    background: var(--adm-surface);
    border: 1px solid var(--adm-border);
    border-radius: var(--radius, 12px);
    padding: 22px;
    box-shadow: var(--adm-shadow);
    transition: box-shadow var(--speed, .18s), transform var(--speed, .18s), background 220ms ease, border-color 220ms ease;
  }
  .card:hover { box-shadow: var(--adm-shadow-lg); }

  /* ── BUTTONS ────────────────────────────────────────────────── */
  .btn { display: inline-flex; align-items: center; gap: 6px; background: var(--adm-ink); color: var(--adm-surface); padding: 9px 16px; border-radius: 9px; border: 1px solid transparent; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: all var(--speed, .18s); line-height: 1; text-decoration: none; white-space: nowrap; }
  .btn:hover { opacity: .87; transform: translateY(-1px); }
  .btn:active { transform: scale(.97); }
  .btn.light { background: var(--adm-surface); color: var(--adm-ink); border-color: var(--adm-border); }
  .btn.light:hover { background: var(--adm-bg); border-color: var(--adm-muted); opacity: 1; }
  .btn.danger { background: #c00; border-color: #c00; color: #fff; }
  .btn.danger:hover { background: #a00; opacity: 1; }
  .btn.success { background: #16a34a; border-color: #16a34a; color: #fff; }
  .btn.sm { padding: 6px 12px; font-size: 13px; border-radius: 7px; }
  .btn.xs { padding: 4px 9px; font-size: 12px; border-radius: 6px; }

  /* ── FLASH MESSAGES ─────────────────────────────────────────── */
  .flash { display: flex; align-items: flex-start; gap: 10px; border-radius: 11px; padding: 13px 16px; margin-bottom: 18px; font-size: 14px; font-weight: 500; animation: ntSlideDown .22s var(--ease, cubic-bezier(.2,.8,.2,1)) both; }
  .flash.ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
  .flash.bad { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
  .flash.info{ background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
  html[data-adm-theme="dark"] .flash.ok   { background: rgba(5,150,105,.15); border-color: rgba(52,211,153,.3); color: #34d399; }
  html[data-adm-theme="dark"] .flash.bad  { background: rgba(185,28,28,.15); border-color: rgba(252,165,165,.3); color: #fca5a5; }
  html[data-adm-theme="dark"] .flash.info { background: rgba(37,99,235,.15); border-color: rgba(96,165,250,.3);  color: #60a5fa; }

  /* ── FORMS ──────────────────────────────────────────────────── */
  .form-group { margin-bottom: 18px; }
  .form-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--adm-ink); }
  .form-label .req { color: var(--accent, #cc0000); margin-left: 2px; }
  .form-control { width: 100%; padding: 10px 13px; border: 1px solid var(--adm-border); border-radius: 9px; font-size: 14px; background: var(--adm-surface); color: var(--adm-ink); transition: border-color var(--speed, .18s), box-shadow var(--speed, .18s), background 220ms ease; outline: none; }
  .form-control:focus { border-color: var(--accent, #cc0000); box-shadow: 0 0 0 3px rgba(204,0,0,.1); }
  textarea.form-control { resize: vertical; line-height: 1.6; }
  .form-hint { margin-top: 4px; font-size: 12px; color: var(--adm-muted); }

  /* ── CUSTOM SELECT (no browser default) ─────────────────────── */
  select,
  select.form-control {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    padding-right: 36px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 12px;
    cursor: pointer;
  }
  select:not(.form-control) {
    width: 100%;
    padding: 10px 36px 10px 13px;
    border: 1px solid var(--adm-border);
    border-radius: 9px;
    font-size: 14px;
    background-color: var(--adm-surface);
    color: var(--adm-ink);
    transition: border-color var(--speed, .18s), box-shadow var(--speed, .18s);
    outline: none;
  }
  select:focus {
    border-color: var(--accent, #cc0000);
    box-shadow: 0 0 0 3px rgba(204,0,0,.1);
  }
  select:hover { border-color: var(--adm-muted); }
  html[data-adm-theme="dark"] select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238e8e93' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  }

  /* ── CUSTOM TOOLTIPS (replace title attr) ───────────────────── */
  [data-tooltip] {
    position: relative;
  }
  [data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%) scale(.92);
    padding: 6px 10px;
    background: var(--adm-ink);
    color: var(--adm-surface);
    font-size: 12px;
    font-weight: 500;
    line-height: 1.3;
    border-radius: 6px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transition: opacity .15s, transform .15s;
    z-index: 9000;
  }
  [data-tooltip]:hover::after,
  [data-tooltip]:focus-visible::after {
    opacity: 1;
    transform: translateX(-50%) scale(1);
  }

  /* ── CUSTOM CONFIRM DIALOG ──────────────────────────────────── */
  .nt-dialog-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.5);
    backdrop-filter: blur(3px);
    z-index: 10000;
    display: flex; align-items: center; justify-content: center;
    animation: ntFadeIn .15s ease;
  }
  .nt-dialog {
    background: var(--adm-surface);
    border: 1px solid var(--adm-border);
    border-radius: 14px;
    padding: 24px;
    max-width: 420px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
    animation: ntSlideDown .2s cubic-bezier(.2,.8,.2,1);
  }
  .nt-dialog-title { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
  .nt-dialog-body  { font-size: 14px; color: var(--adm-muted); margin-bottom: 20px; line-height: 1.5; }
  .nt-dialog-actions { display: flex; gap: 10px; justify-content: flex-end; }
  @keyframes ntFadeIn { from { opacity: 0; } to { opacity: 1; } }

  /* ── TABLES ─────────────────────────────────────────────────── */
  .table-wrap { border: 1px solid var(--adm-border); border-radius: var(--radius, 12px); overflow: hidden; background: var(--adm-surface); box-shadow: var(--adm-shadow); transition: border-color 220ms ease, background 220ms ease; }
  table { width: 100%; border-collapse: collapse; }
  thead tr { background: var(--adm-bg); }
  th { padding: 12px 16px; border-bottom: 2px solid var(--adm-border); text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--adm-muted); white-space: nowrap; }
  td { padding: 12px 16px; border-bottom: 1px solid var(--adm-border); font-size: 14px; vertical-align: middle; color: var(--adm-ink); transition: color 220ms ease; }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr { transition: background var(--speed, .18s); }
  tbody tr:hover { background: var(--adm-bg); }

  /* ── BADGES ─────────────────────────────────────────────────── */
  .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 99px; font-size: 11px; font-weight: 700; letter-spacing: .03em; }
  .badge.green  { background: #dcfce7; color: #15803d; }
  .badge.yellow { background: #fef9c3; color: #854d0e; }
  .badge.red    { background: #fee2e2; color: #b91c1c; }
  .badge.blue   { background: #dbeafe; color: #1d4ed8; }
  .badge.grey   { background: #f3f4f6; color: #4b5563; }
  html[data-adm-theme="dark"] .badge.green  { background: rgba(5,150,105,.18);  color: #34d399; }
  html[data-adm-theme="dark"] .badge.yellow { background: rgba(217,119,6,.18);  color: #fbbf24; }
  html[data-adm-theme="dark"] .badge.red    { background: rgba(185,28,28,.18);  color: #fca5a5; }
  html[data-adm-theme="dark"] .badge.blue   { background: rgba(37,99,235,.18);  color: #60a5fa; }
  html[data-adm-theme="dark"] .badge.grey   { background: rgba(75,85,99,.22);   color: #9ca3af; }

  /* ── PAGE HEADER ────────────────────────────────────────────── */
  .page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
  .page-header h1 { font-size: 24px; font-weight: 800; letter-spacing: -.3px; margin-bottom: 3px; color: var(--adm-ink); }
  .page-header .sub { font-size: 14px; color: var(--adm-muted); }
  .muted { color: var(--adm-muted); }

  /* ── PAGINATION ─────────────────────────────────────────────── */
  .pagination { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 18px; justify-content: flex-end; }
  .pagination .pcount { font-size: 13px; color: var(--adm-muted); margin-right: 4px; }

  /* ── TOGGLES ────────────────────────────────────────────────── */
  .toggle-wrap { display: flex; align-items: center; gap: 10px; }
  .toggle { position: relative; width: 38px; height: 22px; flex-shrink: 0; }
  .toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
  .toggle-track { position: absolute; inset: 0; background: #ddd; border-radius: 99px; cursor: pointer; transition: background var(--speed, .18s); }
  .toggle input:checked + .toggle-track { background: var(--accent, #cc0000); }
  .toggle-track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform var(--speed, .18s); box-shadow: 0 1px 3px rgba(0,0,0,.2); }
  .toggle input:checked + .toggle-track::after { transform: translateX(16px); }

  /* ── GLOBAL MODAL SYSTEM (NTModal) ─────────────────────────── */
  #nt-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.55); backdrop-filter: blur(3px);
    align-items: center; justify-content: center; padding: 20px;
    animation: ntFadeIn .18s ease;
  }
  #nt-modal-overlay.open { display: flex; }
  #nt-modal-box {
    background: var(--adm-surface);
    border-radius: 16px; max-width: 560px; width: 100%;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 64px rgba(0,0,0,.22);
    position: relative; animation: ntSlideUp .2s cubic-bezier(.2,.8,.2,1);
    color: var(--adm-ink);
    transition: background 220ms ease;
  }
  #nt-modal-box input, #nt-modal-box textarea { background: var(--adm-bg); color: var(--adm-ink); border-color: var(--adm-border); }
  #nt-modal-close { position: absolute; top: 14px; right: 16px; background: none; border: none; color: var(--adm-muted); font-size: 22px; cursor: pointer; line-height: 1; padding: 4px 8px; border-radius: 8px; transition: background .15s, color .15s; }
  #nt-modal-close:hover { background: var(--adm-bg); color: var(--adm-ink); }
  #nt-modal-body { padding: 28px 28px 24px; }
  @keyframes ntFadeIn  { from { opacity: 0; } to { opacity: 1; } }
  @keyframes ntSlideUp { from { transform: translateY(14px) scale(.97); opacity: 0; } to { transform: none; opacity: 1; } }

  /* ── RESPONSIVE ─────────────────────────────────────────────── */
  @media (max-width: 900px) {
    .nt-sidebar { transform: translateX(-100%); }
    .nt-sidebar.open { transform: translateX(0); box-shadow: 4px 0 36px rgba(0,0,0,.28); }
    .nt-overlay { display: block; }
    .nt-overlay.open { opacity: 1; pointer-events: auto; }
    .nt-main { margin-left: 0; }
    .nt-burger { display: flex; }
    .nt-content { padding: 18px; }
  }
  @media (max-width: 480px) {
    .nt-topbar { padding: 0 14px; }
    .nt-tb-av-name { display: none; }
    .nt-live-btn span { display: none; }
    .nt-content { padding: 12px; }
  }

  /* ══ Admin Login Page (.lp-*) ══════════════════════════════════
     Two-column layout. All colours via var(--adm-*) tokens.
     Left panel uses --adm-sb-bg so it always matches the sidebar.
  ════════════════════════════════════════════════════════════════ */
  .lp-wrap { display: flex; min-height: 100vh; width: 100%; }

  /* Left panel */
  .lp-left {
    flex: 0 0 48%; background: var(--adm-sb-bg, #1a1b1e);
    position: relative; display: flex; flex-direction: column; overflow: hidden;
  }
  .lp-noise {
    position: absolute; inset: 0; pointer-events: none; z-index: 1;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
    background-size: 256px 256px; mix-blend-mode: overlay; opacity: 0.6;
  }
  .lp-rules { position: absolute; inset: 0; z-index: 1; display: flex; justify-content: space-around; pointer-events: none; }
  .lp-rules span { display: block; width: 1px; background: linear-gradient(to bottom, transparent 0%, rgba(255,255,255,.05) 20%, rgba(255,255,255,.05) 80%, transparent 100%); }
  .lp-left-body {
    position: relative; z-index: 2; flex: 1;
    display: flex; flex-direction: column; justify-content: center;
    padding: 56px 52px; gap: 44px;
  }
  .lp-masthead { text-align: center; }
  .lp-rule-top, .lp-rule-bot { height: 1px; background: linear-gradient(to right, transparent, rgba(255,255,255,.25), transparent); margin: 12px 0; }
  .lp-nameplate { font-family: 'UnifrakturMaguntia', Georgia, serif; font-size: clamp(32px, 4vw, 52px); color: #f5f0e8; letter-spacing: .01em; line-height: 1.1; text-shadow: 0 2px 20px rgba(0,0,0,.5); }
  .lp-edition { font-family: var(--ui, system-ui, sans-serif); font-size: 10px; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: rgba(255,255,255,.35); margin-top: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; }
  .lp-edition-sep { opacity: .4; }
  .lp-quote-block { border-left: 2px solid rgba(255,255,255,.15); padding-left: 20px; }
  .lp-quote { font-family: Georgia, serif; font-size: 15px; font-style: italic; color: rgba(255,255,255,.55); line-height: 1.7; margin: 0 0 8px; transition: opacity .5s ease; }
  .lp-cite { font-family: var(--ui, system-ui, sans-serif); font-size: 11px; font-weight: 600; letter-spacing: .06em; color: rgba(255,255,255,.3); font-style: normal; display: block; transition: opacity .5s ease; }
  .lp-grid-preview { display: grid; grid-template-columns: 1fr 90px 1fr; gap: 14px; opacity: 0.22; }
  .lp-preview-col { display: flex; flex-direction: column; gap: 7px; }
  .lp-preview-img { background: rgba(255,255,255,.12); border-radius: 2px; min-height: 80px; }
  .lp-pline { height: 6px; background: rgba(255,255,255,.35); border-radius: 3px; }
  .w40{width:40%}.w45{width:45%}.w50{width:50%}.w55{width:55%}.w60{width:60%}.w65{width:65%}.w70{width:70%}.w75{width:75%}.w80{width:80%}.w90{width:90%}
  .lp-left-foot { position: relative; z-index: 2; padding: 16px 52px; border-top: 1px solid rgba(255,255,255,.07); font-family: var(--ui, system-ui, sans-serif); font-size: 11px; color: rgba(255,255,255,.2); letter-spacing: .04em; }

  /* Right panel */
  .lp-right { flex: 1; display: flex; align-items: center; justify-content: center; background: #1c1917; padding: 40px 24px; }
  .lp-form-wrap { width: 100%; max-width: 400px; animation: lpFadeUp .4s cubic-bezier(.2,.8,.2,1) both; }
  @keyframes lpFadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
  .lp-mobile-brand { display: none; font-family: 'UnifrakturMaguntia', Georgia, serif; font-size: 28px; color: #fafafa; margin-bottom: 28px; }
  .lp-form-header { margin-bottom: 28px; }
  .lp-form-title { font-family: var(--ui, system-ui, sans-serif); font-size: 26px; font-weight: 800; letter-spacing: -.02em; color: #fafafa; margin: 0 0 6px; }
  .lp-form-sub { font-family: var(--ui, system-ui, sans-serif); font-size: 14px; color: #a8a29e; margin: 0; }
  .lp-error { display: flex; align-items: center; gap: 8px; background: rgba(211,47,47,.15); border: 1px solid #D32F2F; color: #ffb3ac; border-radius: 10px; padding: 11px 14px; font-size: 13px; font-weight: 500; font-family: var(--ui, system-ui, sans-serif); margin-bottom: 20px; animation: lpShake .35s cubic-bezier(.36,.07,.19,.97) both; }
  @keyframes lpShake { 10%,90%{transform:translateX(-2px)} 20%,80%{transform:translateX(3px)} 30%,50%,70%{transform:translateX(-4px)} 40%,60%{transform:translateX(4px)} }
  .lp-form { display: flex; flex-direction: column; gap: 18px; }
  .lp-field { display: flex; flex-direction: column; gap: 7px; }
  .lp-label { font-family: var(--ui, system-ui, sans-serif); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #a8a29e; }
  .lp-input-wrap { position: relative; display: flex; align-items: center; }
  .lp-input-icon { position: absolute; left: 13px; color: #78716c; pointer-events: none; flex-shrink: 0; }
  .lp-input { width: 100%; padding: 12px 40px; border: 1.5px solid #292524; border-radius: 10px; background: #0c0a09; color: #fafafa; font-family: var(--ui, system-ui, sans-serif); font-size: 15px; transition: border-color .15s, box-shadow .15s; outline: none; }
  .lp-input::placeholder { color: #78716c; }
  .lp-input:focus { border-color: #D32F2F; box-shadow: 0 0 0 3px rgba(211,47,47,.15); }
  .lp-pw-toggle { position: absolute; right: 12px; background: none; border: none; cursor: pointer; color: #78716c; padding: 4px; display: flex; transition: color .15s; }
  .lp-pw-toggle:hover { color: #fafafa; }
  .lp-submit { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px 20px; background: #D32F2F; color: #fff; border: none; border-radius: 10px; cursor: pointer; font-family: var(--ui, system-ui, sans-serif); font-size: 15px; font-weight: 700; letter-spacing: .01em; margin-top: 4px; transition: background .15s, transform .1s; }
  .lp-submit:hover { background: #b71c1c; transform: translateY(-1px); }
  .lp-submit:active { transform: translateY(0); }
  .lp-foot { margin-top: 24px; text-align: center; }
  .lp-back-link { display: inline-flex; align-items: center; gap: 5px; font-family: var(--ui, system-ui, sans-serif); font-size: 13px; color: #78716c; text-decoration: none; transition: color .15s; }
  .lp-back-link:hover { color: #fafafa; }
  @media (max-width: 780px) {
    .lp-wrap { flex-direction: column; }
    .lp-left { display: none; }
    .lp-right { min-height: 100vh; padding: 48px 24px; }
    .lp-mobile-brand { display: block; }
  }
  </style>
</head>
<body>

<div class="nt-shell <?= $hideAdminNav ? 'login' : '' ?>">

<?php if (!$hideAdminNav): ?>
  <div class="nt-overlay" id="ntOverlay"></div>

  <aside class="nt-sidebar" id="ntSidebar" aria-label="Admin navigation">
    <div class="nt-logo">
      <a href="/admin">
        <?php if ($adminLogo): ?>
          <img src="<?= h($adminLogo) ?>" alt="<?= h($sidebarTitle) ?>" class="nt-logo-img"/>
        <?php else: ?>
          <span class="nt-logo-text"><?= h($sidebarTitle) ?></span>
        <?php endif; ?>
        <div class="nt-logo-sub"><?= h($sidebarSub) ?></div>
      </a>
    </div>

    <nav class="nt-nav" aria-label="Admin menu">
      <?php
        // Determine which groups are active based on current page
        $contentActive  = in_array($activeNav, ['articles','archive','categories','media']);
        $editorialActive = in_array($activeNav, ['review','comments','notifications']);
        $growthActive   = in_array($activeNav, ['subscribers','newsletter','popups','analytics','ads','social-posts','push-settings','performance','engagement','syndication','followers']);
        $crawlerActive  = in_array($activeNav, ['crawler','crawler-logs','crawler-seo','crawler-social','crawler-settings','rewriter','rewriter-settings']);
        $adminActive    = in_array($activeNav, ['settings','users','roles','system','login-quotes']);
        $editorialActive = in_array($activeNav, ['review','comments','notifications','policies']);

        // Badges
        $reviewBadge = 0;
        $notifBadge  = 0;
        $crawlerBadge = 0;
        try { $reviewBadge  = (int)\App\Models\Article::queryColumn("SELECT COUNT(*) FROM articles WHERE status = 'pending_review'"); } catch (\Throwable) {}
        try { $notifBadge   = (int)\App\Models\Notification::unreadCount($user['id'] ?? ''); } catch (\Throwable) {}
        try { $crawlerBadge = (int)\App\Models\CrawlSource::queryColumn("SELECT COUNT(*) FROM crawl_sources WHERE is_active = TRUE"); } catch (\Throwable) {}
      ?>

      <?= sidebarLink('dashboard', 'Dashboard', '/admin', $activeNav) ?>

      <?php /* ── CONTENT group ── */ ?>
      <div class="nt-nav-group <?= $contentActive ? 'open' : '' ?>" id="ntGroupContent" data-group="content">
        <button type="button" class="nt-nav-group-toggle" onclick="ntToggleGroup(this)">
          <span class="nt-nav-icon"><?= adminSvg('articles') ?></span>
          <span class="nt-nav-label">Content</span>
          <span class="nt-nav-group-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></span>
        </button>
        <div class="nt-nav-group-items">
          <?= sidebarLink('articles',   'Articles',       '/admin/articles',         $activeNav) ?>
          <?= sidebarLink('archive',    'Archive',        '/admin/articles/archive', $activeNav) ?>
          <?php if (user_can_any(['media.upload','media.manage'])): ?>
          <?= sidebarLink('media',      'Media Library',  '/admin/media',            $activeNav) ?>
          <?php endif; ?>
          <?php if (user_can('categories.manage')): ?>
          <?= sidebarLink('categories', 'Categories',     '/admin/categories',       $activeNav) ?>
          <?php endif; ?>
        </div>
      </div>

      <?php /* ── EDITORIAL group ── */ ?>
      <div class="nt-nav-group <?= $editorialActive ? 'open' : '' ?>" id="ntGroupEditorial" data-group="editorial">
        <button type="button" class="nt-nav-group-toggle" onclick="ntToggleGroup(this)">
          <span class="nt-nav-icon"><?= adminSvg('review') ?></span>
          <span class="nt-nav-label">Editorial</span>
          <?php if (($reviewBadge + $notifBadge) > 0): ?>
            <span class="nt-nav-badge red" id="ntBadgeEditorial"><?= $reviewBadge + $notifBadge ?></span>
          <?php else: ?>
            <span class="nt-nav-badge red" id="ntBadgeEditorial" style="display:none">0</span>
          <?php endif; ?>
          <span class="nt-nav-group-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></span>
        </button>
        <div class="nt-nav-group-items">
          <?php if (user_is_editor()): ?>
          <?= sidebarLink('review', 'Review Queue', '/admin/review', $activeNav,
              false, $reviewBadge > 0 ? (string)$reviewBadge : '') ?>
          <?php endif; ?>
          <?php if (user_can_any(['comments.view','comments.moderate'])): ?>
          <?= sidebarLink('comments', 'Comments', '/admin/comments', $activeNav) ?>
          <?php endif; ?>
          <?= sidebarLink('notifications', 'Notifications', '/admin/notifications', $activeNav,
              false, $notifBadge > 0 ? (string)$notifBadge : '') ?>
          <?php if (user_is_editor()): ?>
          <?= sidebarLink('policies', 'Policy Pages', '/admin/policies', $activeNav) ?>
          <?php endif; ?>
        </div>
      </div>

      <?php /* ── GROWTH group ── */ ?>
      <?php if (user_can_any(['subscribers.view','subscribers.manage','ads.view','ads.manage'])): ?>
      <div class="nt-nav-group <?= $growthActive ? 'open' : '' ?>" id="ntGroupGrowth" data-group="growth">
        <button type="button" class="nt-nav-group-toggle" onclick="ntToggleGroup(this)">
          <span class="nt-nav-icon"><?= adminSvg('analytics') ?></span>
          <span class="nt-nav-label">Growth</span>
          <span class="nt-nav-group-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></span>
        </button>
        <div class="nt-nav-group-items">
          <?php if (user_can_any(['subscribers.view','subscribers.manage'])): ?>
          <?= sidebarLink('subscribers',   'Subscribers',    '/admin/subscribers',      $activeNav) ?>
          <?= sidebarLink('followers',     'Topic Followers','/admin/followers',        $activeNav) ?>
          <?= sidebarLink('newsletter',    'Newsletter',     '/admin/newsletter',       $activeNav) ?>
          <?= sidebarLink('popups',        'Popups',         '/admin/popups',           $activeNav) ?>
          <?= sidebarLink('analytics',     'Popup Analytics','/admin/popups/analytics', $activeNav) ?>
          <?php endif; ?>
          <?php if (user_can_any(['ads.view','ads.manage'])): ?>
          <?= sidebarLink('ads',           'Advertisements', '/admin/ads',              $activeNav) ?>
          <?php endif; ?>
          <?= sidebarLink('social-posts',  'Social Post Log','/admin/social/posts',     $activeNav) ?>
          <?= sidebarLink('push-settings', 'Push & Social',  '/admin/push/settings',    $activeNav) ?>
          <?= sidebarLink('performance',  'Performance',    '/admin/performance',      $activeNav) ?>
          <?= sidebarLink('engagement',   'Engagement',     '/admin/engagement',       $activeNav) ?>
          <?= sidebarLink('syndication',  'Syndication',    '/admin/syndication',      $activeNav) ?>
        </div>
      </div>
      <?php endif; ?>

      <?php /* ── CRAWLER group ── */ ?>
      <?php if (user_is_editor()): ?>
      <div class="nt-nav-group <?= $crawlerActive ? 'open' : '' ?>" id="ntGroupCrawler" data-group="crawler">
        <button type="button" class="nt-nav-group-toggle" onclick="ntToggleGroup(this)">
          <span class="nt-nav-icon"><?= adminSvg('crawler') ?></span>
          <span class="nt-nav-label">News Crawler</span>
          <?php if ($crawlerBadge > 0): ?>
            <span class="nt-nav-badge green" id="ntBadgeCrawler"><?= $crawlerBadge ?></span>
          <?php else: ?>
            <span class="nt-nav-badge green" id="ntBadgeCrawler" style="display:none">0</span>
          <?php endif; ?>
          <span class="nt-nav-group-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></span>
        </button>
        <div class="nt-nav-group-items">
          <?= sidebarLink('crawler',          'Sources',          '/admin/crawler',          $activeNav) ?>
          <?= sidebarLink('crawler-logs',     'Crawl Log',        '/admin/crawler/logs',     $activeNav) ?>
          <?= sidebarLink('crawler-seo',      'SEO Audit',        '/admin/crawler/seo',      $activeNav) ?>
          <?= sidebarLink('crawler-social',   'Social Monitor',   '/admin/crawler/social',   $activeNav) ?>
          <?= sidebarLink('crawler-settings', 'Crawler Settings', '/admin/crawler/settings', $activeNav) ?>
          <div class="nt-nav-divider"></div>
          <?= sidebarLink('rewriter',          'AI Rewriter',      '/admin/rewriter',          $activeNav) ?>
          <?= sidebarLink('rewriter-settings', 'Rewriter Settings','/admin/rewriter/settings', $activeNav) ?>
        </div>
      </div>
      <?php endif; ?>

      <?php /* ── ADMIN group ── */ ?>
      <div class="nt-nav-group <?= $adminActive ? 'open' : '' ?>" id="ntGroupAdmin" data-group="admin">
        <button type="button" class="nt-nav-group-toggle" onclick="ntToggleGroup(this)">
          <span class="nt-nav-icon"><?= adminSvg('settings') ?></span>
          <span class="nt-nav-label">Admin</span>
          <span class="nt-nav-group-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></span>
        </button>
        <div class="nt-nav-group-items">
          <?php if ($isSuperAdmin): ?>
          <?= sidebarLink('system', 'System Admin', '/admin/system', $activeNav) ?>
          <?= sidebarLink('login-quotes', 'Login Quotes', '/admin/login-quotes', $activeNav) ?>
          <?php endif; ?>
          <?php if (user_can_any(['settings.view','settings.edit'])): ?>
          <?= sidebarLink('settings', 'Settings', '/admin/settings', $activeNav) ?>
          <?php endif; ?>
          <?php if (user_can('users.manage')): ?>
          <?= sidebarLink('users', 'Users', '/admin/users', $activeNav) ?>
          <?php endif; ?>
          <?php if (user_can('roles.manage')): ?>
          <?= sidebarLink('roles', 'Roles & Permissions', '/admin/roles', $activeNav) ?>
          <?php endif; ?>
        </div>
      </div>

    </nav>

    <div class="nt-profile-wrap">
      <a href="/admin/profile" class="nt-profile-card">
        <div class="nt-avatar" style="background:<?= h($roleStyle['bg']) ?>;overflow:hidden;padding:0">
          <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= h($user['avatar_url']) ?>"
                 alt="<?= h($user['username'] ?? '') ?>"
                 style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%">
          <?php else: ?>
            <?= h($initials) ?>
          <?php endif; ?>
        </div>
        <div class="nt-profile-info">
          <div class="nt-profile-name"><?= h($user['username'] ?? 'User') ?></div>
          <span class="nt-role-badge" style="background:<?= h($roleStyle['bg']) ?>22;color:<?= h($roleStyle['bg']) ?>">
            <?= h($roleStyle['label']) ?>
          </span>
        </div>
      </a>
    </div>

    <a href="/admin/logout" class="nt-logout" aria-label="Logout">
      <?= adminSvg('logout') ?>
      <span>Logout</span>
    </a>

  </aside>
  <script>
  // ── Sidebar group toggle with localStorage persistence ──────────
  (function () {
    var LS_KEY = 'nt_sidebar_groups';

    function loadState() {
      try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}'); } catch(e) { return {}; }
    }
    function saveState(state) {
      try { localStorage.setItem(LS_KEY, JSON.stringify(state)); } catch(e) {}
    }

    // On load: restore any groups the user previously opened
    // (Groups already open via PHP class="open" are left as-is)
    var state = loadState();
    document.querySelectorAll('.nt-nav-group[data-group]').forEach(function(g) {
      var key = g.dataset.group;
      if (typeof state[key] !== 'undefined') {
        if (state[key]) { g.classList.add('open'); }
        else            { g.classList.remove('open'); }
      }
    });

    // Toggle handler called by onclick
    window.ntToggleGroup = function(btn) {
      var group = btn.closest('.nt-nav-group');
      if (!group) return;
      var isOpen = group.classList.toggle('open');
      var key = group.dataset.group;
      if (key) {
        var state = loadState();
        state[key] = isOpen;
        saveState(state);
      }
    };
  })();
  </script>
<?php endif; ?>

  <div class="nt-main">

    <?php if (!$hideAdminNav): ?>
    <header class="nt-topbar">
      <div class="nt-topbar-left">
        <button class="nt-burger" id="ntBurger" aria-label="Toggle menu" aria-expanded="false">
          <?= adminSvg('burger') ?>
        </button>
        <div class="nt-breadcrumb">
          <span><?= h($siteTitle) ?></span>
          <?php if (!empty($pageTitle) && $pageTitle !== 'Dashboard'): ?>
            <span style="margin:0 5px;color:#ccc">/</span>
            <strong><?= h($pageTitle) ?></strong>
          <?php endif; ?>
        </div>
      </div>
      <div class="nt-topbar-right">
        <?php
          $notifCount = 0;
          try { $notifCount = \App\Models\Notification::unreadCount($user['id'] ?? ''); } catch (\Throwable) {}
        ?>
        <a href="/admin/notifications" class="nt-notif-bell" title="Notifications"
           style="position:relative;display:flex;align-items:center;color:var(--adm-muted);text-decoration:none;padding:6px;">
          <?= adminSvg('notifications') ?>
          <?php if ($notifCount > 0): ?>
            <span id="ntBadgeTopNotif" style="position:absolute;top:0;right:-2px;background:#cc0000;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 4px;"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
          <?php else: ?>
            <span id="ntBadgeTopNotif" style="position:absolute;top:0;right:-2px;background:#cc0000;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 4px;display:none">0</span>
          <?php endif; ?>
        </a>

        <!--
          UPGRADE: Admin theme toggle (light ↔ dark).
          FIX T-01/T-02: This replaces OS @media !important overrides.
          Click sets nt-admin-theme cookie (30-day). JS toggles
          data-adm-theme on <html> immediately for instant feedback.
          Server reads cookie on next request to set data-adm-theme
          server-side (zero flash on hard reload).
        -->
        <button type="button" class="nt-theme-toggle" id="ntThemeToggle"
                title="Toggle admin theme"
                aria-label="<?= $adminIsDark ? 'Switch to light mode' : 'Switch to dark mode' ?>">
          <span class="icon-light"><?= adminSvg('theme-dark') ?></span>
          <span class="icon-dark"><?= adminSvg('theme-light') ?></span>
          <span class="toggle-label"><?= $adminIsDark ? 'Light' : 'Dark' ?></span>
        </button>

        <a href="/" target="_blank" rel="noopener" class="nt-live-btn">
          <?= adminSvg('external') ?>
          <span>Live Site</span>
        </a>
      </div>
    </header>
    <?php endif; ?>

    <main class="nt-content" id="ntContent">
      <?= $slot ?? '' ?>
    </main>

  </div>
</div>

<?php if (!$hideAdminNav): ?>
<script>
(function(){
  'use strict';
  var sb  = document.getElementById('ntSidebar');
  var ov  = document.getElementById('ntOverlay');
  var btn = document.getElementById('ntBurger');

  function openSidebar(){
    sb.classList.add('open');
    ov.classList.add('open');
    if(btn) btn.setAttribute('aria-expanded','true');
    document.body.style.overflow='hidden';
  }
  function closeSidebar(){
    sb.classList.remove('open');
    ov.classList.remove('open');
    if(btn) btn.setAttribute('aria-expanded','false');
    document.body.style.overflow='';
  }

  if(btn) btn.addEventListener('click', openSidebar);
  if(ov)  ov.addEventListener('click',  closeSidebar);
  document.addEventListener('keydown', function(e){
    if(e.key==='Escape' && sb && sb.classList.contains('open')) closeSidebar();
  });

  document.querySelectorAll('.flash').forEach(function(el){
    setTimeout(function(){
      el.style.transition='opacity .35s ease,transform .35s ease';
      el.style.opacity='0';
      el.style.transform='translateY(-6px)';
      setTimeout(function(){ el.remove(); }, 370);
    }, 5000);
  });

  // ── FIX T-01/T-02: Admin theme toggle ──────────────────────────
  // Replaces the @media(prefers-color-scheme:dark) !important block.
  // Sets nt-admin-theme cookie (30 days, SameSite=Lax) so PHP can
  // resolve server-side on next load (zero flash on hard reload).
  var themeBtn = document.getElementById('ntThemeToggle');
  if(themeBtn){
    themeBtn.addEventListener('click', function(){
      var html    = document.documentElement;
      var isDark  = html.getAttribute('data-adm-theme') === 'dark';
      var next    = isDark ? 'light' : 'dark';
      var label   = next === 'dark' ? 'Dark' : 'Light';
      var ariaLbl = next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';

      // Instant DOM update — no flash
      html.setAttribute('data-adm-theme', next);
      themeBtn.setAttribute('aria-label', ariaLbl);
      var lbl = themeBtn.querySelector('.toggle-label');
      if(lbl) lbl.textContent = label;

      // Persist as cookie — PHP reads on next request
      var exp = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toUTCString();
      document.cookie = 'nt-admin-theme=' + next + '; path=/admin; expires=' + exp + '; SameSite=Lax';
    });
  }
})();
</script>

<!-- ── Global Media Picker Modal ──────────────────────────────── -->
<style>
/* Shared overlay+modal used by all media pickers sitewide */
.nt-picker-overlay {
  position: fixed; inset: 0; z-index: 9100;
  background: rgba(0,0,0,.55); backdrop-filter: blur(3px);
  display: none; align-items: center; justify-content: center;
}
.nt-picker-overlay.active { display: flex; }
.nt-picker-modal {
  background: var(--adm-surface, #fff);
  border-radius: 16px;
  width: min(960px, 94vw);
  height: min(680px, 90vh);
  display: flex; flex-direction: column;
  box-shadow: 0 24px 80px rgba(0,0,0,.28);
  overflow: hidden;
  animation: ntPickerSlideUp .2s ease both;
}
@keyframes ntPickerSlideUp {
  from { opacity:0; transform: translateY(16px) scale(.98); }
  to   { opacity:1; transform: translateY(0) scale(1); }
}
.nt-picker-toolbar {
  display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
  padding: 14px 18px; border-bottom: 1px solid var(--adm-border, #e2e2e2);
  background: var(--adm-surface, #fff); flex-shrink: 0;
}
.nt-picker-toolbar-title { font-size: 15px; font-weight: 700; flex-shrink: 0; }
.nt-picker-toolbar input,
.nt-picker-toolbar select {
  padding: 8px 12px; border: 1px solid var(--adm-border, #e2e2e2);
  border-radius: 8px; font: inherit; font-size: 13px;
  background: var(--adm-bg, #f5f5f5); color: var(--adm-ink, #121212);
}
.nt-picker-toolbar input { flex: 1; min-width: 160px; }
.nt-picker-grid-wrap {
  flex: 1; overflow-y: auto; padding: 14px;
}
.nt-picker-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
  gap: 12px; align-content: start;
}
.nt-picker-item {
  border: 2px solid var(--adm-border, #e2e2e2);
  border-radius: 10px; overflow: hidden; cursor: pointer;
  background: var(--adm-surface, #fff);
  transition: border-color .15s, box-shadow .15s;
  user-select: none;
}
.nt-picker-item:hover { border-color: #aaa; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
.nt-picker-item.selected { border-color: #c00; box-shadow: 0 0 0 3px rgba(204,0,0,.18); }
.nt-picker-item img {
  width: 100%; height: 110px; object-fit: cover; display: block; background: #f0f0f0;
}
.nt-picker-item-label {
  padding: 5px 7px; font-size: 11px; color: var(--adm-muted, #666);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  border-top: 1px solid var(--adm-border, #e2e2e2);
}
.nt-picker-empty {
  grid-column: 1 / -1; text-align: center; padding: 60px 20px;
  color: var(--adm-muted, #666); font-size: 14px;
}
.nt-picker-footer {
  display: flex; gap: 10px; align-items: center; justify-content: space-between;
  padding: 12px 18px; border-top: 1px solid var(--adm-border, #e2e2e2);
  background: var(--adm-surface, #fff); flex-shrink: 0;
}
.nt-picker-footer-info { font-size: 13px; color: var(--adm-muted, #666); }
</style>

<!-- ── Global Confirmation Modal ──────────────────────────────── -->
<style>
.nt-confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);animation:ntConfFadeIn .15s ease both}
.nt-confirm-overlay.active{display:flex}
@keyframes ntConfFadeIn{from{opacity:0}to{opacity:1}}
@keyframes ntConfSlideUp{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.nt-confirm-box{background:var(--adm-surface);color:var(--adm-ink);border-radius:16px;padding:28px 28px 22px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.22);animation:ntConfSlideUp .2s ease both}
.nt-confirm-icon{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:16px;flex-shrink:0}
.nt-confirm-icon.warn{background:#fef3c7;color:#d97706}
.nt-confirm-icon.danger{background:#fee2e2;color:#dc2626}
.nt-confirm-icon.info{background:#dbeafe;color:#2563eb}
.nt-confirm-icon svg{width:22px;height:22px}
.nt-confirm-title{font-size:16px;font-weight:700;color:var(--adm-ink);margin-bottom:6px}
.nt-confirm-msg{font-size:14px;color:var(--adm-muted);line-height:1.55;margin-bottom:20px}
.nt-confirm-actions{display:flex;gap:10px;justify-content:flex-end}
.nt-confirm-cancel{padding:9px 20px;border-radius:9px;border:1px solid var(--adm-border);background:var(--adm-surface);font-size:13px;font-weight:600;cursor:pointer;color:var(--adm-ink);transition:all .15s ease;font-family:inherit}
.nt-confirm-cancel:hover{background:var(--adm-bg);border-color:var(--adm-muted)}
.nt-confirm-ok{padding:9px 20px;border-radius:9px;border:none;font-size:13px;font-weight:600;cursor:pointer;color:#fff;transition:all .15s ease;font-family:inherit}
.nt-confirm-ok.warn{background:#d97706}.nt-confirm-ok.warn:hover{background:#b45309}
.nt-confirm-ok.danger{background:#dc2626}.nt-confirm-ok.danger:hover{background:#b91c1c}
.nt-confirm-ok.info{background:#2563eb}.nt-confirm-ok.info:hover{background:#1d4ed8}
</style>

<div class="nt-confirm-overlay" id="ntConfirmOverlay">
  <div class="nt-confirm-box">
    <div class="nt-confirm-icon info" id="ntConfirmIcon">
      <!-- icon swapped by JS -->
      <svg id="ntConfirmIconSvg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
    </div>
    <div class="nt-confirm-title" id="ntConfirmTitle">Confirm Action</div>
    <div class="nt-confirm-msg" id="ntConfirmMsg">Are you sure?</div>
    <div class="nt-confirm-actions">
      <button type="button" class="nt-confirm-cancel" id="ntConfirmCancel">Cancel</button>
      <button type="button" class="nt-confirm-ok info" id="ntConfirmOk">Confirm</button>
    </div>
  </div>
</div>

<script>
(function(){
  'use strict';
  var overlay   = document.getElementById('ntConfirmOverlay');
  var titleEl   = document.getElementById('ntConfirmTitle');
  var msgEl     = document.getElementById('ntConfirmMsg');
  var iconEl    = document.getElementById('ntConfirmIcon');
  var okBtn     = document.getElementById('ntConfirmOk');
  var cancelBtn = document.getElementById('ntConfirmCancel');
  var pendingForm = null;
  var pendingHref = null;
  var iconSvg = document.getElementById('ntConfirmIconSvg');

  // SVG paths for each level
  var iconPaths = {
    info:   'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z',
    warn:   'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
    danger: 'M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z'
  };

  function showConfirm(opts) {
    var level = opts.level || 'info';
    titleEl.textContent = opts.title   || 'Confirm Action';
    msgEl.textContent   = opts.message || 'Are you sure?';
    iconEl.className    = 'nt-confirm-icon ' + level;
    if (iconSvg) iconSvg.querySelector('path').setAttribute('d', iconPaths[level] || iconPaths.info);
    okBtn.className     = 'nt-confirm-ok ' + level;
    okBtn.textContent   = opts.ok || 'Confirm';
    overlay.classList.add('active');
    okBtn.focus();
  }

  function hideConfirm() {
    overlay.classList.remove('active');
    pendingForm = null;
    pendingHref = null;
  }

  cancelBtn.addEventListener('click', hideConfirm);
  overlay.addEventListener('click', function(e){ if(e.target === overlay) hideConfirm(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && overlay.classList.contains('active')) hideConfirm(); });

  okBtn.addEventListener('click', function(){
    if(pendingForm){ pendingForm._ntConfirmed = true; pendingForm.submit(); }
    else if(pendingHref){ window.location.href = pendingHref; }
    hideConfirm();
  });

  document.addEventListener('submit', function(e){
    var form = e.target;
    if(form._ntConfirmed){ form._ntConfirmed = false; return; }
    var msg = form.getAttribute('data-confirm');
    if(!msg) return;
    e.preventDefault();
    pendingForm = form;
    showConfirm({ message: msg, title: form.getAttribute('data-confirm-title')||'Confirm Action', level: form.getAttribute('data-confirm-level')||'info', ok: form.getAttribute('data-confirm-ok')||'Confirm' });
  });

  document.addEventListener('click', function(e){
    var link = e.target.closest('a[data-confirm]');
    if(!link) return;
    e.preventDefault();
    pendingHref = link.href;
    showConfirm({ message: link.getAttribute('data-confirm'), title: link.getAttribute('data-confirm-title')||'Confirm Action', level: form.getAttribute('data-confirm-level')||'info', ok: link.getAttribute('data-confirm-ok')||'Confirm' });
  });

  document.addEventListener('click', function(e){
    var btn = e.target.closest('button[data-confirm]');
    if(!btn) return;
    var form = btn.closest('form');
    if(!form || form.getAttribute('data-confirm')) return;
    if(form._ntConfirmed){ form._ntConfirmed = false; return; }
    e.preventDefault();
    pendingForm = form;
    showConfirm({ message: btn.getAttribute('data-confirm'), title: btn.getAttribute('data-confirm-title')||'Confirm Action', level: form.getAttribute('data-confirm-level')||'info', ok: btn.getAttribute('data-confirm-ok')||'Confirm' });
  });
})();
</script>
<?php endif; ?>

<!-- Global NTModal Overlay -->
<div id="nt-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="nt-modal-title">
  <div id="nt-modal-box">
    <button id="nt-modal-close" aria-label="Close dialog">&times;</button>
    <div id="nt-modal-body"></div>
  </div>
</div>

<script>
(function() {
  'use strict';
  var overlay  = document.getElementById('nt-modal-overlay');
  var box      = document.getElementById('nt-modal-box');
  var body     = document.getElementById('nt-modal-body');
  var closeBtn = document.getElementById('nt-modal-close');
  var _onClose   = null;
  var _prevFocus = null;

  function open(contentOrOpts) {
    var html  = typeof contentOrOpts === 'string' ? contentOrOpts : (contentOrOpts.html || '');
    _onClose  = typeof contentOrOpts === 'object' ? (contentOrOpts.onClose || null) : null;
    var maxW  = typeof contentOrOpts === 'object' ? (contentOrOpts.maxWidth || '560px') : '560px';
    body.innerHTML = html;
    box.style.maxWidth = maxW;
    overlay.classList.add('open');
    _prevFocus = document.activeElement;
    var focusable = box.querySelectorAll('button,input,select,textarea,a[href],[tabindex]:not([tabindex="-1"])');
    (focusable[0] || closeBtn).focus();
    document.body.style.overflow = 'hidden';
  }

  function close() {
    overlay.classList.remove('open');
    body.innerHTML = '';
    document.body.style.overflow = '';
    if(_onClose){ try{ _onClose(); }catch(e){} _onClose = null; }
    if(_prevFocus){ try{ _prevFocus.focus(); }catch(e){} _prevFocus = null; }
  }

  overlay.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){ e.preventDefault(); close(); return; }
    if(e.key !== 'Tab') return;
    var focusable = Array.from(box.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href],[tabindex]:not([tabindex="-1"])'));
    if(!focusable.length) return;
    var first = focusable[0], last = focusable[focusable.length-1];
    if(e.shiftKey){ if(document.activeElement === first){ e.preventDefault(); last.focus(); } }
    else          { if(document.activeElement === last) { e.preventDefault(); first.focus(); } }
  });

  overlay.addEventListener('click', function(e){ if(e.target === overlay) close(); });
  closeBtn.addEventListener('click', close);
  window.NTModal = { open: open, close: close };
})();
</script>

</body>
</html>