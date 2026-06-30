/* Zimbo Socials — service worker
 * Hand-rolled for Laravel + Inertia on cPanel so it lives at the site root (scope "/").
 * Bump CACHE_VERSION whenever this file or the precached shell changes.
 */
const CACHE_VERSION = 'v1';
const STATIC_CACHE = `zs-static-${CACHE_VERSION}`;
const RUNTIME_CACHE = `zs-runtime-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';

// Minimal app shell — kept tiny so installs never fail on a missing asset.
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/pwa-192x192.png',
    '/pwa-512x512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(STATIC_CACHE);
            // addAll is atomic; a single 404 would abort the install, so add individually.
            await Promise.all(
                PRECACHE_URLS.map((url) =>
                    cache.add(url).catch(() => undefined),
                ),
            );
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(
                keys
                    .filter((k) => k !== STATIC_CACHE && k !== RUNTIME_CACHE)
                    .map((k) => caches.delete(k)),
            );
            await self.clients.claim();
        })(),
    );
});

// Allow the page to trigger an immediate update.
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

function isStaticAsset(url) {
    return (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/images/') ||
        /\.(?:js|css|png|jpe?g|svg|webp|gif|ico|woff2?|ttf)$/.test(url.pathname)
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only GET, only http(s); never touch POST/PUT mutations or extensions.
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (!url.protocol.startsWith('http')) return;

    // Inertia XHR visits (expect JSON) — let them hit the network and fail
    // naturally so Inertia's own error handling stays in control.
    if (request.headers.get('X-Inertia')) return;

    // Full-page navigations: network-first with an offline fallback page.
    if (request.mode === 'navigate') {
        event.respondWith(
            (async () => {
                try {
                    const network = await fetch(request);
                    return network;
                } catch {
                    const cache = await caches.open(STATIC_CACHE);
                    return (
                        (await cache.match(OFFLINE_URL)) ||
                        new Response('Offline', { status: 503 })
                    );
                }
            })(),
        );
        return;
    }

    // Same-origin hashed/static assets: cache-first (immutable Vite output).
    if (url.origin === self.location.origin && isStaticAsset(url)) {
        event.respondWith(
            (async () => {
                const cache = await caches.open(RUNTIME_CACHE);
                const cached = await cache.match(request);
                if (cached) return cached;
                try {
                    const response = await fetch(request);
                    if (response.ok) cache.put(request, response.clone());
                    return response;
                } catch {
                    return cached || Response.error();
                }
            })(),
        );
        return;
    }

    // Google/Bunny fonts: stale-while-revalidate so they work offline once seen.
    if (url.origin.includes('fonts.bunny.net')) {
        event.respondWith(
            (async () => {
                const cache = await caches.open(RUNTIME_CACHE);
                const cached = await cache.match(request);
                const network = fetch(request)
                    .then((response) => {
                        if (response.ok) cache.put(request, response.clone());
                        return response;
                    })
                    .catch(() => cached);
                return cached || network;
            })(),
        );
    }
    // Everything else (Tawk widget, analytics, cross-origin): default network.
});
