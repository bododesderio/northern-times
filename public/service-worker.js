/**
 * Site Service Worker — push notifications and offline caching.
 * Handles background push notifications and offline caching.
 *
 * Registered by push-prompt.js at scope '/'.
 * Version bump forces SW update on all clients.
 */

const SW_VERSION = '1.0.0';
const CACHE_NAME = 'site-cache-v1';

// ── Message handler — receives site title from page ───────────
self._siteTitle = '';
self.addEventListener('message', (event) => {
  // Only accept messages from same origin
  if (event.origin && event.origin !== self.location.origin) return;
  if (event.data && event.data.type === 'SET_SITE_TITLE') {
    self._siteTitle = event.data.title || '';
  }
});


// ── Install ────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

// ── Activate ───────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// ── Push event — fires when a push message arrives ─────────────
self.addEventListener('push', (event) => {
  let data = {};

  if (event.data) {
    try {
      data = event.data.json();
    } catch {
      data = { title: event.data.text() };
    }
  }

  const title   = data.title  || self._siteTitle || 'News';
  const options = {
    body:            data.body   || 'A new article has been published.',
    icon:            data.icon   || '/assets/icons/icon-192.png',
    badge:           data.badge  || '/assets/icons/badge-72.png',
    image:           data.image  || undefined,
    tag:             data.tag    || 'site-news',
    renotify:        true,
    requireInteraction: false,
    data: {
      url: data.url || '/',
    },
    actions: [
      { action: 'read',    title: 'Read Article' },
      { action: 'dismiss', title: 'Dismiss' },
    ],
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// ── Notification click ─────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'dismiss') return;

  const url = event.notification.data?.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      // Focus existing tab if open
      for (const client of windowClients) {
        if (client.url === url && 'focus' in client) {
          return client.focus();
        }
      }
      // Otherwise open new tab
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});

// ── Notification close (dismissed via X) ──────────────────────
self.addEventListener('notificationclose', () => {
  // Can be used for analytics
});

// ── Push subscription change (browser auto-renews) ────────────
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil(
    self.registration.pushManager.subscribe({
      userVisibleOnly:      true,
      applicationServerKey: event.oldSubscription?.options?.applicationServerKey,
    }).then((newSub) => {
      return fetch('/api/push/subscribe', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(newSub.toJSON()),
      });
    }).catch((err) => {
      console.warn('[NT SW] Push re-subscription failed:', err);
    })
  );
});