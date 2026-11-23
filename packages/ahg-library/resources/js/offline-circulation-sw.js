/* Offline circulation desk: keeps the page and its assets openable without a link.
   Patron data, tokens and sync traffic are never stored here. */
const CACHE = 'heratio-offline-desk-v1';
const SCOPE = '/library-manage/circulation/offline';
const NEVER = [SCOPE + '/package', SCOPE + '/session', SCOPE + '/sync', SCOPE + '/conflicts'];

const isShell = (url) => url.pathname === SCOPE || url.pathname === SCOPE + '/' || url.pathname === SCOPE + '/app.js';
const isAsset = (url) => /\.(css|js|woff2?|ttf|svg|png|jpg|gif|ico)$/i.test(url.pathname) && !url.pathname.startsWith(SCOPE + '/sw');
const cacheable = (url) => url.origin === self.location.origin
    && !NEVER.some((path) => url.pathname.startsWith(path))
    && (isShell(url) || isAsset(url));

async function store(request, response) {
    if (response.ok && response.type === 'basic' && !response.redirected) {
        const cache = await caches.open(CACHE);
        await cache.put(request, response.clone());
    }
    return response;
}

async function networkFirst(request) {
    try {
        return await store(request, await fetch(request));
    } catch (error) {
        const hit = await caches.match(request, { ignoreSearch: new URL(request.url).pathname === SCOPE });
        if (hit) return hit;
        throw error;
    }
}

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key.startsWith('heratio-offline-desk-') && key !== CACHE) await caches.delete(key);
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (cacheable(url)) event.respondWith(networkFirst(event.request));
});

/* Assets the first visit loaded before this worker took control. */
self.addEventListener('message', (event) => {
    if (event.origin !== self.location.origin || event.data?.type !== 'precache' || !Array.isArray(event.data.urls)) return;
    event.waitUntil(Promise.all(event.data.urls.slice(0, 200).map(async (href) => {
        try {
            const url = new URL(href, self.location.origin);
            if (!cacheable(url)) return;
            const request = new Request(url.href, { credentials: 'same-origin' });
            await store(request, await fetch(request));
        } catch (error) { /* a missed asset only degrades the offline look */ }
    })));
});
