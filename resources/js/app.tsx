import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import AppErrorBoundary from './Components/AppErrorBoundary';
import WhatsAppFloatingButton from './Components/WhatsAppFloatingButton';
import PwaInstallPrompt from './Components/PwaInstallPrompt';
import { registerServiceWorker } from './registerSW';

const appName = 'Zimbo Socials';

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
