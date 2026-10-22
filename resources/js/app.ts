import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

import AppLayout from '@/layouts/AppLayout.vue';
import { prepareLocale } from '@/composables/useLocale';

/**
 * Setiap berkas di pages/ jadi chunk terpisah (glob tanpa `eager`), jadi browser
 * hanya mengunduh kode halaman yang benar-benar dibuka. Chunk `vendor` dipisah
 * di vite.config.ts supaya cache-nya bertahan lintas deploy.
 */
const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} — TMS` : 'TMS'),

    resolve: async (name) => {
        const page = await resolvePageComponent(`./pages/${name}.vue`, pages);

        // Layout persisten: sidebar & topbar tidak di-mount ulang saat pindah
        // halaman, sehingga posisi scroll dan state menu tetap.
        //
        // Dibandingkan dengan `undefined`, BUKAN `??=`: halaman tamu memakai
        // `defineOptions({ layout: null })` untuk menolak cangkang aplikasi, dan
        // null termasuk nullish sehingga `??=` justru menimpanya.
        if (page.default.layout === undefined) {
            page.default.layout = AppLayout;
        }

        return page;
    },

    setup({ el, App, props, plugin }) {
        // Kamus bahasa yang tersimpan dimuat DULU, baru aplikasi di-mount:
        // kalau tidak, render pertama selalu English dan baru berganti satu
        // tick kemudian -- terlihat sebagai kedipan di setiap muat halaman.
        // Untuk English (bawaan) ini langsung selesai, tanpa permintaan apa pun.
        void prepareLocale().then(() => {
            createApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue)
                .mount(el);
        });
    },

    progress: {
        color: 'rgb(185 28 28)',
        // Tidak memunculkan bar untuk respons cepat -- mengurangi kedipan.
        delay: 250,
    },
});
