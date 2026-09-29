/* Build del frontend web (Laravel + Inertia). El vite.config.js de la raíz és el de
   l'app mòbil Capacitor (src/ → dist/) i no es toca: aquest compila resources/js →
   public/build, que és el que el blade demana amb @vite(). */
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    build: {
        chunkSizeWarningLimit: 2000,
        /* El CSS de vue3-easy-data-table porta variables sense -- que el minificador
           nou (lightningcss) rebutja; sense minify el build passa i el pes és assumible. */
        cssMinify: false,
    },
});
