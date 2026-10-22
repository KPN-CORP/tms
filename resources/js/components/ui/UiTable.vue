<script setup lang="ts">
import { useSlots } from 'vue';
import UiSpinner from './UiSpinner.vue';
import UiEmptyState from './UiEmptyState.vue';
import { useLocale } from '@/composables/useLocale';

// Teks kosong bawaan dibiarkan `null`, bukan string: nilainya bergantung bahasa
// dan harus ikut berubah saat bahasanya diganti, jadi ditambal saat render.
const props = withDefaults(
    defineProps<{
        /** Kosongkan isi tabel dan tampilkan pesan kosong. */
        empty?: boolean;
        emptyTitle?: string | null;
        emptyMessage?: string | null;
        /** Tampilkan lapisan redup saat partial reload berjalan. */
        loading?: boolean;
    }>(),
    {
        empty: false,
        emptyTitle: null,
        emptyMessage: null,
    },
);

const slots = useSlots();
const { t } = useLocale();
</script>

<template>
    <div class="relative">
        <!-- aria-busy memberi tahu pembaca layar bahwa isi sedang diperbarui,
             sementara isi lama tetap terlihat agar tidak ada loncatan tata letak. -->
        <div
            class="overflow-x-auto scroll-slim transition-opacity duration-150"
            :class="loading && 'pointer-events-none opacity-60'"
            :aria-busy="loading || undefined"
        >
            <table v-if="!empty" class="w-full border-collapse text-sm">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left">
                        <slot name="head" />
                    </tr>
                </thead>

                <tbody class="divide-y divide-line/60">
                    <slot />
                </tbody>

                <tfoot v-if="slots.foot" class="border-t border-line bg-surface-muted font-semibold">
                    <slot name="foot" />
                </tfoot>
            </table>

            <UiEmptyState
                v-else
                :title="props.emptyTitle ?? t.common.nothingHere"
                :message="props.emptyMessage ?? t.common.noDataMessage"
            >
                <template v-if="slots.emptyAction" #action>
                    <slot name="emptyAction" />
                </template>
            </UiEmptyState>
        </div>

        <div v-if="loading" class="pointer-events-none absolute right-4 top-3 text-brand">
            <UiSpinner class="h-4 w-4" :label="t.common.updatingList" />
        </div>
    </div>
</template>
