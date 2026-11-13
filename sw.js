/*
 * ITFlow service worker - makes the app installable on a phone and shows a
 * friendly page instead of the browser's error when the network is gone.
 *
 * Deliberately caches NOTHING but the offline page and its icon: ITFlow pages
 * hold client data and credentials, and they must never be left on a device's
 * disk or served stale. Every other request goes straight to the network.
 *
 * Served from the site root so that its scope covers the whole application.
 */

const CACHE = 'itflow-offline-v1';
const OFFLINE_URL = '/offline.php';
const OFFLINE_ASSETS = [OFFLINE_URL, '/img/pwa/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            // reload: the offline page must come from the server, not the HTTP cache
            .then((cache) => cache.addAll(OFFLINE_ASSETS.map((url) => new Request(url, { cache: 'reload' }))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // The offline page's icon, so the page looks right without a network
    if (request.method === 'GET' && new URL(request.url).pathname === '/img/pwa/icon-192.png') {
        event.respondWith(caches.match('/img/pwa/icon-192.png').then((cached) => cached || fetch(request)));
        return;
    }

    // Only page loads are handled; everything else is left to the browser
    if (request.mode !== 'navigate' || request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(request).catch(() => caches.match(OFFLINE_URL))
    );
});
