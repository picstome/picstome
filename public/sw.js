self.addEventListener('install', event => {
  event.waitUntil(
    caches.open('picstome-v1').then(cache => {
      return cache.addAll([
        '/site.webmanifest',
        '/app-logo.png',
        '/app-logo-dark.png',
        '/favicon-96x96.png',
        '/favicon.svg',
        '/favicon.ico',
        '/web-app-manifest-192x192.png',
        '/web-app-manifest-512x512.png',
        // Add more assets as needed
      ]).catch(err => {
        console.error('Cache addAll failed:', err);
      });
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
