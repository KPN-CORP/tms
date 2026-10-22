<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useLocale } from '@/composables/useLocale';

/**
 * Penanda bahwa daftar sedang tersaring karena dibuka dari kartu Dashboard —
 * tanpa ini jumlah baris terlihat "salah" dibanding filter yang terlihat.
 */
defineProps<{ label: string; clearHref: string }>();

const { t } = useLocale();
</script>

<template>
    <div
        class="mb-4 inline-flex items-center gap-2 rounded-full bg-warning-soft px-3 py-1.5 text-xs text-warning-ink ring-1 ring-warning/25"
    >
        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M2.628 4.5A1.5 1.5 0 0 1 4 3.5h12a1.5 1.5 0 0 1 1.14 2.475l-4.39 5.12v4.03a1 1 0 0 1-.553.894l-2.5 1.25A1 1 0 0 1 8.25 16.3v-5.205L3.86 5.975A1.5 1.5 0 0 1 2.628 4.5Z" clip-rule="evenodd" />
        </svg>

        <span>{{ t.list.fromDashboard }} · <span class="font-semibold">{{ label }}</span></span>

        <Link :href="clearHref" class="font-semibold underline-offset-2 hover:underline">
            {{ t.list.clear }}
        </Link>
    </div>
</template>
