/**
 * Push Notification Prompt
 *
 * Include on frontend pages: <script src="/assets/push-prompt.js" defer></script>
 * Requires: VAPID public key exposed via <meta name="vapid-public-key" content="...">
 *
 * Flow:
 *  1. Register service worker
 *  2. Check existing subscription
 *  3. Show a subtle "Enable notifications" bar after 5s (only if not yet subscribed)
 *  4. On click — request permission and subscribe
 *  5. POST subscription to /api/push/subscribe
 */

(function () {
  'use strict';

  // Bail if push not supported
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

  const STORAGE_KEY  = 'nt_push_dismissed';
  const PROMPT_DELAY = 5000; // ms before showing prompt

  // Read VAPID public key from meta tag
  const vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
  if (!vapidMeta || !vapidMeta.content) return;
  const VAPID_PUBLIC_KEY = vapidMeta.content;

  // ── Register service worker ────────────────────────────────
  // Pass site title to SW so push notifications use the white-label name
  const siteTitleMeta = document.querySelector('meta[name="site-title"]');
  if (navigator.serviceWorker.controller && siteTitleMeta) {
    navigator.serviceWorker.controller.postMessage({ type: 'SET_SITE_TITLE', title: siteTitleMeta.content });
  }

  navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
    .then((reg) => {
      // Send site title to newly activated SW
      if (siteTitleMeta) {
        reg.active && reg.active.postMessage({ type: 'SET_SITE_TITLE', title: siteTitleMeta.content });
      }
      // Check if already subscribed
      return reg.pushManager.getSubscription().then((sub) => {
        if (sub) {
          // Already subscribed — sync with server silently
          syncSubscription(sub);
          return;
        }

        // If user previously dismissed, respect that
        if (sessionStorage.getItem(STORAGE_KEY)) return;
        if (Notification.permission === 'denied') return;

        // Show prompt after delay
        setTimeout(() => showPrompt(reg), PROMPT_DELAY);
      });
    })
    .catch((err) => {
      console.warn('[Push] SW registration failed:', err);
    });

  // ── Prompt UI ──────────────────────────────────────────────
  function showPrompt(reg) {
    if (document.getElementById('nt-push-bar')) return;

    const bar = document.createElement('div');
    bar.id = 'nt-push-bar';
    bar.style.cssText = [
      'position:fixed', 'bottom:20px', 'left:50%', 'transform:translateX(-50%)',
      'background:#121212', 'color:#fff', 'border-radius:12px',
      'padding:14px 20px', 'display:flex', 'align-items:center', 'gap:14px',
      'box-shadow:0 8px 32px rgba(0,0,0,.25)', 'z-index:9999',
      'max-width:480px', 'width:calc(100% - 40px)', 'font-family:inherit',
      'animation:ntSlideUp .3s ease', 'font-size:14px',
    ].join(';');

    bar.innerHTML = `
      <style>
        @keyframes ntSlideUp {
          from { opacity:0; transform:translateX(-50%) translateY(20px); }
          to   { opacity:1; transform:translateX(-50%) translateY(0); }
        }
        #nt-push-bar button { cursor:pointer; border:none; border-radius:8px; font:inherit; font-size:13px; padding:8px 16px; }
        #nt-push-enable { background:#c00; color:#fff; font-weight:700; }
        #nt-push-enable:hover { background:#a00; }
        #nt-push-dismiss { background:rgba(255,255,255,.1); color:#ccc; }
        #nt-push-dismiss:hover { background:rgba(255,255,255,.2); }
      </style>
      <span style="font-size:20px">🔔</span>
      <span style="flex:1; line-height:1.4">
        <strong>Stay informed.</strong><br>
        <span style="color:#aaa">Get notified when we publish breaking news.</span>
      </span>
      <button id="nt-push-enable">Enable</button>
      <button id="nt-push-dismiss" title="Dismiss">✕</button>
    `;

    document.body.appendChild(bar);

    document.getElementById('nt-push-enable').addEventListener('click', () => {
      subscribe(reg, bar);
    });

    document.getElementById('nt-push-dismiss').addEventListener('click', () => {
      sessionStorage.setItem(STORAGE_KEY, '1');
      bar.remove();
    });
  }

  // ── Subscribe ──────────────────────────────────────────────
  function subscribe(reg, bar) {
    Notification.requestPermission().then((permission) => {
      if (permission !== 'granted') {
        if (bar) bar.remove();
        return;
      }

      reg.pushManager.subscribe({
        userVisibleOnly:      true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
      }).then((sub) => {
        if (bar) bar.remove();
        syncSubscription(sub);
        showConfirmation();
      }).catch((err) => {
        console.warn('[Push] Subscribe failed:', err);
        if (bar) bar.remove();
      });
    });
  }

  // ── Sync subscription with server ─────────────────────────
  function syncSubscription(sub) {
    const data = sub.toJSON();
    fetch('/api/push/subscribe', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        endpoint: data.endpoint,
        p256dh:   data.keys?.p256dh,
        auth:     data.keys?.auth,
      }),
    }).catch(() => {}); // Silent fail — non-critical
  }

  // ── Success toast ──────────────────────────────────────────
  function showConfirmation() {
    const toast = document.createElement('div');
    toast.style.cssText = [
      'position:fixed', 'bottom:20px', 'left:50%', 'transform:translateX(-50%)',
      'background:#15803d', 'color:#fff', 'border-radius:10px',
      'padding:12px 20px', 'font-size:14px', 'z-index:9999',
      'box-shadow:0 4px 16px rgba(0,0,0,.2)', 'font-family:inherit',
    ].join(';');
    toast.textContent = '✓ Notifications enabled! You\'ll be the first to know.';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  }

  // ── Utility: VAPID key conversion ─────────────────────────
  function urlBase64ToUint8Array(base64String) {
    const padding  = '='.repeat((4 - base64String.length % 4) % 4);
    const base64   = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData  = atob(base64);
    const output   = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
      output[i] = rawData.charCodeAt(i);
    }
    return output;
  }

})();