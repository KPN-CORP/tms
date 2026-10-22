import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            // app.ts = cangkang Inertia/Vue. app.js (Alpine) masih dibutuhkan
            // selama sebagian halaman belum dipindahkan dari Blade; hapus baris
            // itu begitu halaman Blade terakhir hilang.
            input: ['resources/css/app.css', 'resources/js/app.ts', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    // Biarkan Vite yang me-resolve aset di dalam SFC, bukan browser.
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],

    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
        // Satu instance Vue saja — mencegah "two different Vue runtimes".
        dedupe: ['vue'],
    },

    build: {
        // Kode terpisah per halaman (lihat resolvePage di app.ts) + vendor stabil,
        // supaya cache browser tidak gugur setiap kali satu halaman berubah.
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['vue', '@inertiajs/vue3'],
                },
            },
        },
        chunkSizeWarningLimit: 700,
    },
});
