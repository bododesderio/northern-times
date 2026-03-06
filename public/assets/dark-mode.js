/**
 * Northern Times — Theme Engine v2
 *
 * THREE-TIER PRIORITY CHAIN (fixed):
 *   1. User explicit choice  → localStorage 'nt-theme' = 'light' | 'dark'
 *   2. Server default        → data-theme attr on <html> set by PHP (get_site_setting)
 *   3. OS preference         → prefers-color-scheme (ONLY when server mode is 'system')
 *   4. Hard fallback         → 'light'
 *
 * BUG FIXES applied (see Diagnostics Report T-03, T-04):
 *   T-03: getTheme() no longer blindly applies OS dark over DB light default.
 *         OS preference is only honoured when the server has set mode='system'.
 *   T-04: Reads data-theme attribute emitted by PHP on <html> — no longer relies
 *         on [data-theme="light"] never being set.
 *
 * UPGRADE — Three-Mode Toggle:
 *   Button cycles: light → dark → system → light …
 *   Stored as: 'light' | 'dark' | 'system' (null = defer to server default)
 *
 * UPGRADE — Logo Swap:
 *   If a dark-logo src is present (data-dark-src on .site-logo img),
 *   the src is swapped when dark mode activates.
 *
 * Scope: frontend only. Admin panel has its own separate theme handling.
 */
(function () {
  'use strict';

  var STORAGE_KEY  = 'nt-theme';
  var html         = document.documentElement;

  /* ── Read server-declared mode from PHP ────────────────────────
   * app/Views/frontend/layout.php must emit:
   *   <html lang="en" data-theme="<?= h($serverThemeMode) ?>">
   * where $serverThemeMode = get_site_setting('theme_mode', 'light')
   * Values: 'light' | 'dark' | 'system'
   * ──────────────────────────────────────────────────────────── */
  function getServerMode() {
    return html.getAttribute('data-theme') || 'light';
  }

  /* ── Resolve which theme to actually apply ──────────────────────
   * Priority:
   *   1. User has an explicit saved preference  → use it always
   *   2. Server mode is 'system'               → follow OS
   *   3. Server mode is 'dark'                 → dark
   *   4. Server mode is 'light' (default)      → light
   * ──────────────────────────────────────────────────────────── */
  function resolveTheme() {
    var stored = null;
    try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}

    // 1. User explicit choice wins over everything
    if (stored === 'dark' || stored === 'light') {
      return stored;
    }

    var serverMode = getServerMode();

    // 2. Server says "system" → follow OS
    if (serverMode === 'system') {
      return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
        ? 'dark'
        : 'light';
    }

    // 3 & 4. Server says light or dark → obey
    if (serverMode === 'dark') return 'dark';
    return 'light';
  }

  /* ── Apply theme to DOM ─────────────────────────────────────── */
  function applyTheme(theme, animate) {
    if (animate) {
      html.classList.add('dark-transition');
      setTimeout(function () { html.classList.remove('dark-transition'); }, 260);
    }

    if (theme === 'dark') {
      html.classList.add('dark');
    } else {
      html.classList.remove('dark');
    }

    // Swap logo src if the site logo has a data-dark-src attribute
    swapLogos(theme);

    // Update toggle button aria-label and icon visibility
    updateToggleUI();
  }

  /* ── Logo swap (Upgrade) ────────────────────────────────────── */
  function swapLogos(theme) {
    var logos = document.querySelectorAll('img.site-logo, img[data-dark-src]');
    for (var i = 0; i < logos.length; i++) {
      var img = logos[i];
      var lightSrc = img.getAttribute('data-light-src') || img.getAttribute('src');
      var darkSrc  = img.getAttribute('data-dark-src');
      if (!darkSrc) continue;

      // Store light src on first run
      if (!img.getAttribute('data-light-src')) {
        img.setAttribute('data-light-src', img.getAttribute('src'));
      }
      img.src = (theme === 'dark') ? darkSrc : lightSrc;
    }
  }

  /* ── Update toggle button UI ────────────────────────────────── */
  function updateToggleUI() {
    var btn = document.getElementById('darkToggle');
    if (!btn) return;

    var isDark   = html.classList.contains('dark');
    var stored   = null;
    try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    var isSystem = (stored === null || stored === 'system');

    // Icon visibility — the button contains three SVG elements:
    //   .icon-sun    (shown in dark mode  — click to go light)
    //   .icon-moon   (shown in light mode — click to go dark)
    //   .icon-system (shown when following system preference)
    var sun    = btn.querySelector('.icon-sun');
    var moon   = btn.querySelector('.icon-moon');
    var system = btn.querySelector('.icon-system');

    if (sun)    sun.style.display    = (isDark && !isSystem)  ? '' : 'none';
    if (moon)   moon.style.display   = (!isDark && !isSystem) ? '' : 'none';
    if (system) system.style.display = isSystem               ? '' : 'none';

    // aria-label
    var label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    if (isSystem) label = 'Theme follows system (click to override)';
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  }

  /* ── Toggle cycle: light → dark → system → light … ─────────── */
  function toggle() {
    var stored = null;
    try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}

    var serverMode = getServerMode();
    var next;

    if (stored === 'light') {
      next = 'dark';
    } else if (stored === 'dark') {
      // Cycle back to system only if server supports it; otherwise go light
      next = (serverMode === 'system') ? 'system' : 'light';
    } else {
      // Currently system / unset → go to light explicitly
      next = 'light';
    }

    try {
      if (next === 'system') {
        localStorage.removeItem(STORAGE_KEY);
      } else {
        localStorage.setItem(STORAGE_KEY, next);
      }
    } catch (e) {}

    applyTheme(resolveTheme(), true);
  }

  /* ── Boot: apply theme immediately (prevents FOUC) ──────────── */
  var initial = resolveTheme();
  // Apply without animation on first load — no classList.add('dark-transition')
  if (initial === 'dark') {
    html.classList.add('dark');
  }

  /* ── DOM ready: wire button + OS listener ───────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('darkToggle');
    if (btn) {
      btn.addEventListener('click', toggle);
    }

    // Run logo swap and UI update now that DOM is ready
    swapLogos(initial);
    updateToggleUI();
  });

  /* ── OS preference change listener ─────────────────────────── */
  if (window.matchMedia) {
    try {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        var stored = null;
        try { stored = localStorage.getItem(STORAGE_KEY); } catch (err) {}

        // Only auto-switch if user has NOT made an explicit manual choice
        // and the server mode is 'system'
        if (!stored && getServerMode() === 'system') {
          applyTheme(e.matches ? 'dark' : 'light', true);
        }
      });
    } catch (e) {} // older Safari/Firefox
  }

})();