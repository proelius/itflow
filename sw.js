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

const CACHE = 'itflow-offline-v2';
const OFFLINE_URL = '/offline.php';
// The company logo icon (app_icon.php), as offline.php shows it
const OFFLINE_ICON = '/app_icon.php?size=192';
const OFFLINE_ASSETS = [OFFLINE_URL, OFFLINE_ICON];

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

    // The offline page's icon: from the network while there is one, keeping the copy up to
    // date when the logo changes - the kept copy when there is none
    const url = new URL(request.url);
    if (request.method === 'GET' && url.origin === self.location.origin && url.pathname + url.search === OFFLINE_ICON) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(OFFLINE_ICON, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(OFFLINE_ICON))
        );
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
