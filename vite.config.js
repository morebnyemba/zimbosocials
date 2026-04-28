import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

const isProduction = process.env.NODE_ENV === 'production';

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
        ...(isProduction
            ? [
                VitePWA({
                    registerType: 'autoUpdate',
                    injectRegister: 'auto',
                    includeAssets: ['favicon.ico', 'apple-touch-icon.png'],
                    manifest: {
                        name: 'Zimbo Socials',
                        short_name: 'ZimboSocial',
                        description: 'Elite Digital Growth Hub Zimbabwe',
                        theme_color: '#10b981',
                        icons: [
                            {
                                src: 'pwa-192x192.png',
                                sizes: '192x192',
                                type: 'image/png'
                            },
                            {
                                src: 'pwa-512x512.png',
                                sizes: '512x512',
                                type: 'image/png'
                            },
                            {
                                src: 'pwa-512x512.png',
                                sizes: '512x512',
                                type: 'image/png',
                                purpose: 'any maskable'
                            }
                        ]
                    }
                })
            ]
            : []),
    ],
});
