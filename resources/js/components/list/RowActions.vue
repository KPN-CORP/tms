<script setup lang="ts">
import AppLink from '@/components/ui/AppLink.vue';
import { useLocale } from '@/composables/useLocale';

/**
 * Pensil = buka untuk diubah (hanya bila user berwenang pada fase ini),
 * mata = buka untuk dilihat. Keduanya bisa muncul bersamaan atau salah satu.
 *
 * `external` menandai tujuan yang halamannya masih Blade — lihat AppLink.
 */
withDefaults(
    defineProps<{
        editHref?: string | null;
        viewHref: string;
        external?: boolean;
    }>(),
    { external: false },
);

const { t } = useLocale();

const BUTTON = 'inline-flex h-8 w-8 items-center justify-center rounded-control border transition-colors';
</script>

<template>
    <div class="flex items-center justify-end gap-2">
        <AppLink
            v-if="editHref"
            :href="editHref"
            :external="external"
            :class="`${BUTTON} border-brand text-brand hover:bg-brand-soft`"
            :title="t.list.edit"
        >
            <span class="sr-only">{{ t.list.edit }}</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586Z" />
            </svg>
        </AppLink>

        <AppLink
            :href="viewHref"
            :external="external"
            :class="`${BUTTON} border-line text-ink-muted hover:bg-surface-muted`"
            :title="t.list.viewDetail"
        >
            <span class="sr-only">{{ t.list.viewDetail }}</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
            </svg>
        </AppLink>
    </div>
</template>
