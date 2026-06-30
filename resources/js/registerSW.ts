/**
 * Registers the root-scoped service worker (public/sw.js).
 *
 * Lives at the site root so its scope is "/" and it can control the whole
 * Inertia app — unlike a bundled worker emitted under /build. Registration is
 * production-only so it never interferes with Vite HMR during development.
 */
export function registerServiceWorker(): void {
    if (import.meta.env.DEV) return;
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                // When a new worker takes over, reload once so the user gets
                // fresh assets without a hard refresh.
                registration.addEventListener('updatefound', () => {
                    const installing = registration.installing;
                    if (!installing) return;
                    installing.addEventListener('statechange', () => {
                        if (
                            installing.state === 'installed' &&
                            navigator.serviceWorker.controller
                        ) {
                            installing.postMessage('SKIP_WAITING');
                        }
                    });
                });
            })
            .catch(() => {
                /* registration failures are non-fatal */
            });

        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshing) return;
            refreshing = true;
            window.location.reload();
        });
    });
}
