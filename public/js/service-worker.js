// Cache app-shell for better offline resilience.
const CACHE_NAME = 'halogari-cache-v7';
const urlsToCache = [
  '/',
  '/css/style.css',
  '/assets/styles/app.css',
  '/manifest.json',
  '/images/logo/logo-787x298.png',
  '/images/icons/favicon.ico',
  '/images/icons/icon-192x192.png',
  '/images/icons/icon-512x512.png',
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(urlsToCache);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames
          .filter(function(cacheName) {
            return cacheName !== CACHE_NAME;
          })
          .map(function(cacheName) {
            return caches.delete(cacheName);
          })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function(event) {
  if (event.request.method !== 'GET') {
    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(function() {
        return caches.match(event.request);
      })
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then(function(response) {
      return response || fetch(event.request);
    })
  );
});

// 🔔 Gestion des notifications Web Push
self.addEventListener('push', function(event) {
  const data = event.data ? event.data.json() : {};
  const badgeCount = Number(data.badgeCount) || 0;

  const options = {
    body: data.body || 'Notification HaloGari',
    icon: '/images/icons/logo430x430.png',
    badge: '/images/icons/icon-192x192.png',
    tag: data.url || 'halogari-notification',
    renotify: true,
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(
    Promise.all([
      self.registration.showNotification(data.title || 'HaloGari', options),
      badgeCount > 0 && self.registration.setAppBadge
        ? self.registration.setAppBadge(badgeCount)
        : Promise.resolve()
    ])
  );
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const targetUrl = event.notification.data?.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      for (const client of clientList) {
        if ('focus' in client && client.url.includes(self.location.origin)) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }

      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
