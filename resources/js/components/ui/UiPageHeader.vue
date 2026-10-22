<script setup lang="ts">
import { useSlots } from 'vue';
import AppLink from './AppLink.vue';
import { useLocale } from '@/composables/useLocale';

/**
 * Kepala halaman: judul, keterangan, dan tombol aksi di kanan.
 *
 * Halaman anak (form tambah/ubah, detail) memakai tombol "Back to list" di
 * sebelah kanan, bukan breadcrumb di atas judul — mengikuti kpn-tmp, yang
 * menaruh tautan kembali di slot aksi kepala halaman.
 */
defineProps<{
    title: string;
    description?: string;
    /** Tujuan tombol kembali; tanpa ini tombolnya tidak dirender. */
    backHref?: string;
    /** Teks tombol kembali; bawaannya "Back to list". */
    backLabel?: string;
    /** Halaman tujuan masih Blade — lihat AppLink. */
    backExternal?: boolean;
}>();

const slots = useSlots();
const { t } = useLocale();
</script>

<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold text-ink">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-ink-subtle">{{ description }}</p>
        </div>

        <div v-if="backHref || slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <!-- Kembali ditaruh paling kiri dalam kelompok aksi: ia menjauh dari
                 tombol utama, sehingga tidak tertukar saat diklik cepat. -->
            <AppLink
                v-if="backHref"
                :href="backHref"
                :external="backExternal"
                class="inline-flex h-control items-center gap-1.5 rounded-control border border-line bg-surface px-4 text-sm font-semibold text-ink-muted transition-colors hover:bg-surface-muted"
            >
                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 .02 1.06L8.832 10l3.978 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd" />
                </svg>
                {{ backLabel ?? t.common.backToList }}
            </AppLink>

            <slot name="actions" />
        </div>
    </div>
</template>
