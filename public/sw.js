/* Heratio service worker - mobile / offline researcher flow.
 *
 * Caches the mobile shell + Bootstrap/FontAwesome bundles so the /research/mobile
 * route can open while offline. POST traffic (sync endpoint, comments, etc.)
 * is never cached; on failure it's left to the page's localStorage queue to
 * retry when navigator.onLine flips true.
 *
 * Cache name is bumped via the SW_VERSION constant - update it whenever the
 * mobile shell changes so old clients pick up the new bundle.
 */
const SW_VERSION = 'heratio-mobile-v1';
const SHELL_URLS = [
    '/research/mobile',
    '/manifest.webmanifest',
    '/favicon.ico',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SW_VERSION).then((cache) => cache.addAll(SHELL_URLS).catch(() => null))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.map((k) => (k !== SW_VERSION ? caches.delete(k) : null)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Only handle GET; let mutating requests pass through unchanged so the
    // page-side queue handles offline durability.
    if (req.method !== 'GET') return;

    // Don't cache cross-origin or chrome-extension requests.
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Static asset network-first-with-cache-fallback for the mobile route + shell.
    event.respondWith(
        fetch(req)
            .then((resp) => {
                // Only cache 200-OK responses (avoid caching auth redirects).
                if (resp && resp.status === 200) {
                    const copy = resp.clone();
                    caches.open(SW_VERSION).then((cache) => cache.put(req, copy)).catch(() => null);
                }
                return resp;
            })
            .catch(() => caches.match(req).then((hit) => hit || new Response('Offline', { status: 503 })))
    );
});

// Allow the page to send messages (e.g. force-flush).
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'flush-cache') {
        caches.delete(SW_VERSION);
    }
});
