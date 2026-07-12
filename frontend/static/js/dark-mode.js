/**
 * Northern Times — Theme Engine (light / dark only).
 *
 * Default is LIGHT. The header button toggles light <-> dark. There is NO
 * "system / OS" mode — the reader's explicit choice (or the light default) wins.
 *
 *   1. User explicit choice  -> localStorage 'nt-theme' = 'light' | 'dark'
 *   2. Server default        -> data-theme attr on <html> ('light' | 'dark')
 *   3. Hard fallback         -> 'light'
 *
 * Applied by toggling the `html.dark` class synchronously in <head> (no FOUC).
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'nt-theme';
  var html = document.documentElement;

  function serverDefault() {
    var m = html.getAttribute('data-theme');
    return m === 'dark' ? 'dark' : 'light';  // anything but 'dark' -> light
  }

  function resolveTheme() {
    var stored = null;
    try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    if (stored === 'dark' || stored === 'light') return stored;
    return serverDefault();
  }

  function swapLogos(theme) {
    var logos = document.querySelectorAll('img.header-logo, img.site-logo, img[data-dark-src]');
    for (var i = 0; i < logos.length; i++) {
      var img = logos[i];
      var darkSrc = img.getAttribute('data-dark-src');
      if (!darkSrc) continue;
      if (!img.getAttribute('data-light-src')) img.setAttribute('data-light-src', img.getAttribute('src'));
      img.src = (theme === 'dark') ? darkSrc : img.getAttribute('data-light-src');
    }
  }

  function updateToggleUI() {
    var btn = document.getElementById('darkToggle');
    if (!btn) return;
    var isDark = html.classList.contains('dark');
    var sun = btn.querySelector('.icon-sun');
    var moon = btn.querySelector('.icon-moon');
    if (sun) sun.style.display = isDark ? '' : 'none';   // in dark: show sun (click -> light)
    if (moon) moon.style.display = isDark ? 'none' : ''; // in light: show moon (click -> dark)
    var label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  }

  function applyTheme(theme, animate) {
    if (animate) {
      html.classList.add('dark-transition');
      setTimeout(function () { html.classList.remove('dark-transition'); }, 260);
    }
    if (theme === 'dark') html.classList.add('dark');
    else html.classList.remove('dark');
    swapLogos(theme);
    updateToggleUI();
  }

  function toggle() {
    var next = html.classList.contains('dark') ? 'light' : 'dark';
    try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
    applyTheme(next, true);
  }

  // Boot: apply before paint to prevent FOUC.
  if (resolveTheme() === 'dark') html.classList.add('dark');

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('darkToggle');
    if (btn) btn.addEventListener('click', toggle);
    swapLogos(resolveTheme());
    updateToggleUI();
  });
})();
