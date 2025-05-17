self.addEventListener('install', event => {
  event.waitUntil(
    caches.open('halogari-cache-v1').then(cache => {
      return cache.addAll([
        '/',
        '/css/app.css',
        '/js/app.js',
        '/images/icons/icon-192x192.png',
        '/images/icons/icon-512x512.png',
        '/style.css',
        // ajoute ici d'autres routes si nécessaire
      ]);
    })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});
