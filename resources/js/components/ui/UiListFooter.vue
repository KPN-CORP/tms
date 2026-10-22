<script setup lang="ts">
import UiPagination from './UiPagination.vue';
import { number } from '@/lib/format';
import { tr, useLocale } from '@/composables/useLocale';
import type { Paginated } from '@/types/models';

defineProps<{
    /** Paginator apa pun -- hanya meta-nya yang dipakai. */
    meta: Pick<Paginated<unknown>, 'current_page' | 'last_page' | 'from' | 'to' | 'total'>;
}>();

const perPage = defineModel<number | string | undefined>('perPage');

const emit = defineEmits<{ page: [page: number] }>();

const { t } = useLocale();

/** Sama dengan whitelist di Concerns\HasListQuery. */
const PER_PAGE_OPTIONS = [10, 25, 50, 100];
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-line px-5 py-3">
        <div class="flex items-center gap-4 text-sm text-ink-subtle">
            <p v-if="meta.total > 0">
                {{ t.list.showing }} <span class="font-medium text-ink-muted">{{ number(meta.from) }}</span>
                {{ t.list.showingTo }} <span class="font-medium text-ink-muted">{{ number(meta.to) }}</span>
                {{ t.list.showingOf }} <span class="font-medium text-ink-muted">{{ number(meta.total) }}</span>
            </p>
            <p v-else>{{ t.list.noResults }}</p>

            <label class="flex items-center gap-2">
                <span class="sr-only">{{ t.list.rowsPerPage }}</span>
                <select
                    v-model="perPage"
                    class="h-8 rounded-control border border-line bg-surface px-2 text-xs text-ink-muted"
                >
                    <option v-for="option in PER_PAGE_OPTIONS" :key="option" :value="option">
                        {{ tr(t.list.perPage, { count: option }) }}
                    </option>
                </select>
            </label>
        </div>

        <UiPagination
            :page="meta.current_page"
            :last-page="meta.last_page"
            @change="emit('page', $event)"
        />
    </div>
</template>
