// Maura Laundry Service Worker
// Static assets only — never cache authenticated navigations
const CACHE_NAME = 'maura-laundry-v2';
const OFFLINE_URL = '/maura-laundry/offline.html';

const urlsToCache = [
  '/maura-laundry/offline.html',
  '/maura-laundry/assets/css/style.css',
  '/maura-laundry/assets/js/app.js',
  '/maura-laundry/assets/icon-192.png',
  '/maura-laundry/assets/icon-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(names =>
      Promise.all(names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  // Skip non-GET and all navigations — authenticated HTML must always come from network
  if (request.method !== 'GET' || request.mode === 'navigate') return;

  event.respondWith(
    caches.match(request).then(cached => cached || fetch(request))
  );
});
