import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

// PWA is implemented with a hand-rolled, root-scoped service worker
// (public/sw.js + public/manifest.webmanifest) so it controls the whole app
// on cPanel, where Vite output lives under /build. See resources/js/registerSW.ts.
export default defineConfig({
    server: {
        host: '127.0.0.1',
        port: 5174,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
            port: 5174,
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
    ],
});
