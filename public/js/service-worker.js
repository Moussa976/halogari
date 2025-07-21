// 📦 Mise en cache pour fonctionnement hors-ligne
const CACHE_NAME = 'halogari-cache-v1';
const urlsToCache = [
  '/',
  '/css/style.css',
  '/manifest.json',
  '/images/logo.png',
  '/images/icons/icon-192x192.png',
  '/images/icons/icon-512x512.png',
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(urlsToCache);
    })
  );
});

self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request).then(function(response) {
      return response || fetch(event.request);
    })
  );
});

// 🔔 Gestion des notifications Web Push
self.addEventListener('push', function(event) {
  const data = event.data ? event.data.json() : {};

  const options = {
    body: data.body || 'Notification HaloGari',
    icon: '/images/logo.png',
    badge: '/images/icons/icon-192x192.png'
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'HaloGari', options)
  );
});
