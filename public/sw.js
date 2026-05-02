/**
 * Northern Times — Service Worker
 * Handles push notifications and basic offline caching.
 */

const CACHE_NAME = 'nt-cache-v1';
const OFFLINE_URL = '/offline.html';

let siteTitle = 'Northern Times';

// Listen for site title from push-prompt.js
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SET_SITE_TITLE') {
    siteTitle = event.data.title;
  }
});

// Install — cache offline fallback
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll([OFFLINE_URL]).catch(() => {
        // offline.html may not exist yet — that's fine
      });
    })
  );
  self.skipWaiting();
});

// Activate — clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch — network-first, fall back to cache
self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return;

  event.respondWith(
    fetch(event.request).catch(() =>
      caches.match(OFFLINE_URL).then((r) => r || new Response('Offline', { status: 503 }))
    )
  );
});

// Push notification
self.addEventListener('push', (event) => {
  let data = { title: siteTitle, body: 'New update available', url: '/' };
  try {
    if (event.data) data = Object.assign(data, event.data.json());
  } catch (e) {
    data.body = event.data ? event.data.text() : data.body;
  }

  event.waitUntil(
    self.registration.showNotification(data.title || siteTitle, {
      body: data.body,
      icon: '/assets/icon.svg',
      badge: '/assets/icon.svg',
      data: { url: data.url || '/' },
    })
  );
});

// Notification click — open the article
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        if (client.url === url && 'focus' in client) return client.focus();
      }
      return clients.openWindow(url);
    })
  );
});
