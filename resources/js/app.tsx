import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import AppErrorBoundary from './Components/AppErrorBoundary';
import WhatsAppFloatingButton from './Components/WhatsAppFloatingButton';
import PwaInstallPrompt from './Components/PwaInstallPrompt';
import { registerServiceWorker } from './registerSW';

const appName = 'Zimbo Socials';

// cPanel/LiteSpeed hosts commonly block PUT/PATCH/DELETE at the web-server
// level (mod_security serves its own 404 before Laravel ever runs — seen as
// "The resource requested could not be found on this server!" on every save
// or delete action). Tunnel those verbs through POST + _method, which the
// server allows and Laravel natively resolves back to the intended method.
const SPOOFED_METHODS = ['put', 'patch', 'delete'];
const originalVisit = router.visit.bind(router);
(router as any).visit = (href: any, options: any = {}) => {
    const method = String(options.method ?? 'get').toLowerCase();
    if (SPOOFED_METHODS.includes(method)) {
        options = {
            ...options,
            method: 'post',
            data: { ...(options.data ?? {}), _method: method },
        };
    }
    return originalVisit(href, options);
};

createInertiaApp({
    title: (title) => (title.includes(appName) ? title : `${title} - ${appName}`),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <AppErrorBoundary>
                <App {...props} />
                <WhatsAppFloatingButton />
                <PwaInstallPrompt />
            </AppErrorBoundary>,
        );
    },
    progress: {
        color: '#0B3E09',
    },
});

registerServiceWorker();

// Google Analytics: the gtag config in app.blade.php has send_page_view:false,
// so we emit a page_view here on first load and on every Inertia navigation
// (SPA visits don't trigger a full page load that GA would otherwise count).
const gaId = document
    .querySelector('meta[name="ga-id"]')
    ?.getAttribute('content');

if (gaId) {
    const trackPageView = () => {
        const gtag = (window as unknown as { gtag?: (...args: any[]) => void }).gtag;
        if (typeof gtag !== 'function') return;
        gtag('event', 'page_view', {
            page_path: window.location.pathname + window.location.search,
            page_location: window.location.href,
            page_title: document.title,
        });
    };

    trackPageView(); // initial load
    router.on('navigate', trackPageView); // subsequent Inertia visits
}
