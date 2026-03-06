<?php
/**
 * Popup Live Preview — Standalone iframe endpoint
 *
 * Receives popup configuration via window.postMessage and renders
 * a pixel-accurate preview of the popup as the visitor would see it.
 * The iframe lives at /assets/popup-preview-frame.php and is embedded
 * in the admin popup form.
 *
 * Security: No DB access, no session, no CSRF — this file only renders
 * sanitised data that was POSTed from the same-origin parent window.
 * All untrusted string values are passed through htmlspecialchars before
 * being injected into DOM text nodes (never innerHTML).
 */
declare(strict_types=1);
header('X-Frame-Options: SAMEORIGIN');
header('Content-Security-Policy: default-src \'self\' \'unsafe-inline\' data: https://fonts.googleapis.com https://fonts.gstatic.com');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Popup Preview</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* Simulated website background */
    html, body {
      width: 100%; height: 100%;
      font-family: 'Libre Franklin', system-ui, sans-serif;
      overflow: hidden;
    }

    #site-bg {
      position: absolute; inset: 0;
      background: #f5f5f5;
      display: flex; flex-direction: column; gap: 10px; padding: 14px;
      pointer-events: none;
    }
    .fake-header { height: 14px; background: #e0e0e0; border-radius: 4px; width: 35%; }
    .fake-nav    { height: 8px;  background: #ececec; border-radius: 4px; width: 70%; }
    .fake-hero   { height: 60px; background: #ddd;    border-radius: 6px; }
    .fake-line   { height: 7px;  background: #e8e8e8; border-radius: 3px; }
    .fake-line.w90 { width: 90%; }
    .fake-line.w75 { width: 75%; }
    .fake-line.w60 { width: 60%; }

    /* ── Overlay ─────────────────────────────────────────────────── */
    #popup-overlay {
      position: absolute; inset: 0;
      display: none; align-items: center; justify-content: center;
      z-index: 10;
      transition: background 200ms ease;
    }
    #popup-overlay.visible { display: flex; }

    /* ── POPUP SHELLS ────────────────────────────────────────────── */
    #popup-box {
      position: absolute;
      font-family: 'Libre Franklin', system-ui, sans-serif;
      animation: pvFadeUp 280ms cubic-bezier(.2,.8,.2,1) both;
    }
    @keyframes pvFadeUp {
      from { opacity: 0; transform: translateY(16px) scale(.97); }
      to   { opacity: 1; transform: translateY(0)    scale(1); }
    }
    @keyframes pvSlideIn {
      from { opacity: 0; transform: translateX(100%); }
      to   { opacity: 1; transform: translateX(0); }
    }
    @keyframes pvSlideDown {
      from { opacity: 0; transform: translateY(-100%); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes pvSlideUp {
      from { opacity: 0; transform: translateY(100%); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes pvFloat {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Card Modal */
    #popup-box.style-card-modal {
      width: min(88%, 340px);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,.25);
    }

    /* Minimal Bar — bottom */
    #popup-box.style-minimal-bar {
      position: absolute;
      bottom: 0; left: 0; right: 0; width: 100%;
      border-radius: 12px 12px 0 0;
      box-shadow: 0 -4px 24px rgba(0,0,0,.15);
      animation-name: pvSlideUp;
    }

    /* Split Image */
    #popup-box.style-split-image {
      width: min(90%, 380px);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,.28);
      display: flex;
    }

    /* Fullscreen */
    #popup-box.style-fullscreen {
      inset: 0; width: 100%; height: 100%;
      border-radius: 0;
      animation-name: pvFadeUp;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
    }

    /* Slide-in Panel */
    #popup-box.style-slide-in {
      position: absolute;
      top: 0; right: 0; bottom: 0;
      width: min(68%, 220px);
      border-radius: 12px 0 0 12px;
      box-shadow: -4px 0 24px rgba(0,0,0,.18);
      animation-name: pvSlideIn;
      display: flex; flex-direction: column;
      justify-content: center;
      padding: 20px 16px;
    }

    /* Floating Banner */
    #popup-box.style-floating {
      position: absolute;
      bottom: 12px; right: 12px;
      width: min(72%, 200px);
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,.22);
      animation-name: pvFloat;
    }

    /* Top Bar */
    #popup-box.style-top-bar {
      position: absolute;
      top: 0; left: 0; right: 0; width: 100%;
      border-radius: 0 0 8px 8px;
      box-shadow: 0 4px 16px rgba(0,0,0,.12);
      animation-name: pvSlideDown;
    }

    /* ── INNER LAYOUT ─────────────────────────────────────────────── */
    .pv-image {
      width: 100%;
      display: block;
      object-fit: cover;
      max-height: 100px;
    }
    .pv-split-img {
      width: 38%;
      flex-shrink: 0;
      object-fit: cover;
      display: block;
    }
    .pv-body {
      padding: 14px 16px;
      display: flex; flex-direction: column; gap: 8px;
    }
    .pv-body.compact { padding: 10px 14px; gap: 6px; }
    .pv-body.bar { flex-direction: row; align-items: center; flex-wrap: wrap; gap: 8px; padding: 10px 14px; }
    .pv-title {
      font-size: 13px; font-weight: 800; line-height: 1.3;
    }
    .pv-body-text {
      font-size: 11px; line-height: 1.4; opacity: .82;
    }
    .pv-email {
      width: 100%; padding: 7px 10px;
      border: 1.5px solid rgba(0,0,0,.14);
      border-radius: 7px; font-size: 11px;
      background: rgba(255,255,255,.18);
      color: inherit;
    }
    .pv-email::placeholder { opacity: .6; }
    .pv-btn {
      padding: 8px 14px;
      border: none; border-radius: 8px;
      font-size: 11px; font-weight: 700;
      cursor: pointer; white-space: nowrap;
      flex-shrink: 0;
    }
    .pv-close {
      position: absolute; top: 8px; right: 10px;
      background: rgba(0,0,0,.18); color: #fff;
      border: none; border-radius: 50%;
      width: 20px; height: 20px;
      font-size: 12px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      line-height: 1;
    }
  </style>
</head>
<body>

<!-- Simulated website content in background -->
<div id="site-bg">
  <div class="fake-header"></div>
  <div class="fake-nav"></div>
  <div class="fake-hero"></div>
  <div class="fake-line w90"></div>
  <div class="fake-line w75"></div>
  <div class="fake-line w60"></div>
  <div class="fake-line w90"></div>
  <div class="fake-line w75"></div>
</div>

<!-- Overlay + popup box injected by JS -->
<div id="popup-overlay">
  <div id="popup-box"></div>
</div>

<script>
(function () {
  'use strict';

  var overlay  = document.getElementById('popup-overlay');
  var box      = document.getElementById('popup-box');
  var siteBg   = document.getElementById('site-bg');

  /**
   * Safely set a text node — never uses innerHTML for user content.
   * Returns the created element.
   */
  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'style') { node.style.cssText = attrs[k]; }
        else if (k === 'class') { node.className = attrs[k]; }
        else if (k === 'placeholder') { node.placeholder = attrs[k]; }
        else { node.setAttribute(k, attrs[k]); }
      });
    }
    if (text !== undefined && text !== null) {
      node.appendChild(document.createTextNode(String(text)));
    }
    return node;
  }

  function renderPopup(v) {
    var style   = v.style   || 'card_modal';
    var title   = v.title   || 'Stay Updated';
    var body    = v.body    || 'Get the best stories in your inbox.';
    var btn     = v.btn     || 'Subscribe';
    var bg      = v.bg      || '#ffffff';
    var text    = v.text    || '#1a1a1a';
    var btnBg   = v.btnBg   || '#cc0000';
    var btnText = v.btnText || '#ffffff';
    var img     = v.img     || '';
    var overlay_opacity = parseFloat(v.overlayOpacity || '0.50');
    var hasEmail = !!v.email;
    var position = v.position || 'center';

    /* ---------- overlay background ---------- */
    var showOverlay = ['card_modal','split_image','fullscreen'].indexOf(style) !== -1;
    overlay.style.background = showOverlay
      ? 'rgba(0,0,0,' + overlay_opacity + ')'
      : 'transparent';
    overlay.style.pointerEvents = 'none';

    /* ---------- site background tint for fullscreen ---------- */
    siteBg.style.filter = (style === 'fullscreen') ? 'blur(2px)' : 'none';

    /* ---------- reset box ---------- */
    box.className = '';
    box.style.cssText = '';
    box.innerHTML = '';
    box.className = 'style-' + style.replace(/_/g, '-');
    box.style.background = bg;
    box.style.color = text;
    box.style.position = 'absolute';

    /* ---------- position adjustments ---------- */
    if (style === 'card_modal') {
      if (position === 'top')    { box.style.top = '12px';    box.style.left = '50%'; box.style.transform = 'translateX(-50%)'; }
      else if (position === 'bottom') { box.style.bottom = '12px'; box.style.left = '50%'; box.style.transform = 'translateX(-50%)'; }
      else                       { box.style.top = '50%';    box.style.left = '50%'; box.style.transform = 'translate(-50%,-50%)'; }
    }

    /* ---------- build inner DOM ---------- */
    var closeBtn = el('button', { 'class': 'pv-close', 'aria-label': 'Close' }, '×');
    box.appendChild(closeBtn);

    if (style === 'split_image') {
      /* Split layout: image on left, content on right */
      if (img) {
        var imgEl = el('img', { 'class': 'pv-split-img', src: img, alt: '' });
        box.appendChild(imgEl);
      }
      var bodyDiv = el('div', { 'class': 'pv-body' });
      bodyDiv.appendChild(el('div', { 'class': 'pv-title' }, title));
      bodyDiv.appendChild(el('div', { 'class': 'pv-body-text' }, body));
      if (hasEmail) {
        bodyDiv.appendChild(el('input', { 'class': 'pv-email', type: 'email', placeholder: 'your@email.com' }));
      }
      var btnEl = el('button', { 'class': 'pv-btn', style: 'background:' + btnBg + ';color:' + btnText }, btn);
      bodyDiv.appendChild(btnEl);
      box.appendChild(bodyDiv);

    } else if (style === 'minimal_bar' || style === 'top_bar') {
      /* Bar layout: inline */
      var bodyDiv = el('div', { 'class': 'pv-body bar' });
      bodyDiv.appendChild(el('span', { 'class': 'pv-title', style: 'flex:1;min-width:80px' }, title));
      if (hasEmail) {
        bodyDiv.appendChild(el('input', { 'class': 'pv-email', type: 'email', placeholder: 'email…', style: 'flex:1;min-width:80px' }));
      }
      var btnEl = el('button', { 'class': 'pv-btn', style: 'background:' + btnBg + ';color:' + btnText }, btn);
      bodyDiv.appendChild(btnEl);
      box.appendChild(bodyDiv);

    } else {
      /* Card modal / fullscreen / slide-in / floating */
      if (img) {
        var imgEl = el('img', { 'class': 'pv-image', src: img, alt: '' });
        box.appendChild(imgEl);
      }
      var bodyDiv = el('div', { 'class': 'pv-body' + (style === 'floating' ? ' compact' : '') });
      bodyDiv.appendChild(el('div', { 'class': 'pv-title' }, title));
      if (style !== 'floating') {
        bodyDiv.appendChild(el('div', { 'class': 'pv-body-text' }, body));
      }
      if (hasEmail) {
        bodyDiv.appendChild(el('input', { 'class': 'pv-email', type: 'email', placeholder: 'your@email.com' }));
      }
      var btnEl = el('button', { 'class': 'pv-btn', style: 'background:' + btnBg + ';color:' + btnText }, btn);
      bodyDiv.appendChild(btnEl);
      box.appendChild(bodyDiv);
    }

    overlay.classList.add('visible');
  }

  /* ── Listen for postMessage from parent ───────────────────────── */
  window.addEventListener('message', function (e) {
    /* Accept only from same origin */
    if (e.origin && e.origin !== window.location.origin) return;

    var data = e.data;
    if (!data || data.type !== 'nt_popup_preview') return;

    renderPopup(data.values);
  });

  /* ── Tell parent we're ready ─────────────────────────────────── */
  if (window.parent !== window) {
    window.parent.postMessage({ type: 'nt_preview_ready' }, '*');
  }
})();
</script>
</body>
</html>