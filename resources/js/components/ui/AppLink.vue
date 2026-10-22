<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/**
 * Tautan yang tahu tujuannya.
 *
 * Halaman yang sudah dipindahkan ke Inertia dibuka sebagai kunjungan SPA;
 * halaman yang masih Blade harus dibuka sebagai navigasi biasa, karena <Link>
 * mengharapkan respons Inertia dan akan gagal pada HTML Blade.
 *
 * Prop `external` diisi server (App\Support\Navigation\InertiaRoutes) supaya
 * komponen tidak perlu menebak. Setelah semua halaman pindah, komponen ini bisa
 * diganti <Link> biasa.
 */
withDefaults(
    defineProps<{
        href: string;
        external?: boolean;
        method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    }>(),
    { external: false, method: 'get' },
);
</script>

<template>
    <a v-if="external" :href="href">
        <slot />
    </a>

    <Link v-else :href="href" :method="method" :as="method === 'get' ? 'a' : 'button'">
        <slot />
    </Link>
</template>
