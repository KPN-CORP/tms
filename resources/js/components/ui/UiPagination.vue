<script setup lang="ts">
import { computed } from 'vue';
import { cx } from '@/lib/format';
import { tr, useLocale } from '@/composables/useLocale';

const props = withDefaults(
    defineProps<{
        page: number;
        lastPage: number;
        /** Jumlah nomor halaman di kiri/kanan halaman aktif. */
        siblings?: number;
    }>(),
    { siblings: 1 },
);

const emit = defineEmits<{ change: [page: number] }>();

const { t } = useLocale();

const GAP = '…';

/**
 * Jendela halaman: selalu tampilkan halaman pertama & terakhir, halaman aktif
 * beserta tetangganya, dan sisipkan '…' untuk lompatan. Dibangun dari angka
 * (bukan array `links` Laravel) supaya labelnya bersih dan bisa diterjemahkan.
 */
const pages = computed<Array<number | typeof GAP>>(() => {
    const { page, lastPage, siblings } = props;

    if (lastPage <= 1) {
        return [];
    }

    const candidates = new Set<number>([1, lastPage]);

    for (let i = page - siblings; i <= page + siblings; i++) {
        if (i >= 1 && i <= lastPage) {
            candidates.add(i);
        }
    }

    const sorted = [...candidates].sort((a, b) => a - b);
    const out: Array<number | typeof GAP> = [];

    sorted.forEach((value, index) => {
        const previous = sorted[index - 1];

        if (previous !== undefined && value - previous > 1) {
            out.push(GAP);
        }

        out.push(value);
    });

    return out;
});

const go = (page: number): void => {
    if (page >= 1 && page <= props.lastPage && page !== props.page) {
        emit('change', page);
    }
};

const ITEM = 'inline-flex h-9 min-w-9 items-center justify-center rounded-control px-2.5 text-sm transition-colors';
</script>

<template>
    <nav v-if="pages.length" class="flex flex-wrap items-center gap-1" :aria-label="t.pagination.label">
        <button
            type="button"
            :class="cx(ITEM, 'text-ink-muted hover:bg-surface-muted disabled:opacity-40 disabled:hover:bg-transparent')"
            :disabled="page <= 1"
            :aria-label="t.pagination.previous"
            @click="go(page - 1)"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 .02 1.06L8.832 10l3.978 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.02.02Z" clip-rule="evenodd" />
            </svg>
        </button>

        <template v-for="(item, index) in pages" :key="`${item}-${index}`">
            <span v-if="item === GAP" :class="cx(ITEM, 'text-ink-subtle')" aria-hidden="true">{{ GAP }}</span>

            <button
                v-else
                type="button"
                :class="
                    cx(
                        ITEM,
                        item === page
                            ? 'bg-brand font-semibold text-white'
                            : 'text-ink-muted hover:bg-surface-muted',
                    )
                "
                :aria-current="item === page ? 'page' : undefined"
                :aria-label="tr(t.pagination.page, { page: item })"
                @click="go(item)"
            >
                {{ item }}
            </button>
        </template>

        <button
            type="button"
            :class="cx(ITEM, 'text-ink-muted hover:bg-surface-muted disabled:opacity-40 disabled:hover:bg-transparent')"
            :disabled="page >= lastPage"
            :aria-label="t.pagination.next"
            @click="go(page + 1)"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1-.02-1.06L11.168 10 7.19 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.02-.02Z" clip-rule="evenodd" />
            </svg>
        </button>
    </nav>
</template>
