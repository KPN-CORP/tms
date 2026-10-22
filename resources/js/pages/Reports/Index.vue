<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    AppLink,
    UiBadge,
    UiButton,
    UiCard,
    UiDateTime,
    UiListFooter,
    UiPageHeader,
    UiSelect,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import ListToolbar from '@/components/list/ListToolbar.vue';
import TabBar from '@/components/list/TabBar.vue';
import { useListQuery } from '@/composables/useListQuery';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { ideaTone, projectTone } from '@/lib/status';
import { number, truncate } from '@/lib/format';
import type { ListQuery, Paginated, SelectOption } from '@/types/models';

type CellKind = 'id' | 'name' | 'text' | 'status' | 'datetime' | 'money';

interface Column {
    label: string;
    kind: CellKind;
    sort: string | null;
}

interface Cell {
    value: string | number | null;
    /** Hanya untuk kolom status: label yang sudah diterjemahkan server. */
    label?: string;
}

interface ReportRow {
    key: string;
    cells: Cell[];
    urls: { detail: string; override: string | null };
}

/**
 * Report — satu-satunya tampilan lintas organisasi, dan satu-satunya halaman
 * yang menyalakan konteks override (lihat App\Support\ReportOverride).
 *
 * Kolomnya dinamis: definisi tetap di ReportController (dipakai bersama oleh
 * unduhan CSV), halaman ini hanya tahu cara merender tiap `kind`.
 */
const props = defineProps<{
    type: string;
    types: SelectOption[];
    columns: Column[];
    rows: Paginated<ReportRow>;
    filters: ListQuery & { tab: string };
    tabs: Array<{ key: string; label: string; count: number }>;
    businessUnits: string[];
    isIdea: boolean;
    metric: string | null;
    metricLabel: string | null;
    mineOnly: boolean;
    downloadUrl: string;
    detailIsExternal: boolean;
}>();

const { t } = useLocale();
const { field, metric: metricText, ideaStatus, projectStatus, reportType } = useLabels();

const typeOptions = computed(() =>
    props.types.map((option) => ({
        ...option,
        label: reportType(String(option.value), option.label),
    })),
);

const { filters, loading, sortBy, setPage, reset, hasActiveFilters } = useListQuery({
    url: route('reports.index'),
    initial: { ...props.filters, type: props.type, metric: props.metric ?? undefined },
    only: ['rows', 'tabs', 'filters', 'columns'],
});

const selectTab = (key: string): void => {
    router.get(
        route('reports.index'),
        { ...props.filters, type: props.type, metric: props.metric, tab: key, page: undefined },
        { only: ['rows', 'tabs', 'filters'], preserveState: true, preserveScroll: true, replace: true },
    );
};

/**
 * Ganti jenis laporan memuat ulang penuh: kolom dan tab ikut berubah, dan
 * konteks metrik Dashboard tidak lagi berlaku untuk jenis yang berbeda.
 */
const changeType = (value: string | number | null | undefined): void => {
    router.get(route('reports.index'), { type: value, tab: 'all' }, { preserveScroll: true });
};

const buOptions = computed(() => props.businessUnits.map((name) => ({ value: name, label: name })));

const tone = (status: string | number | null) =>
    props.isIdea ? ideaTone(String(status)) : projectTone(String(status));

/** Sel status membawa kode di `value` dan label Inggris di `label`. */
const statusText = (cell: Cell): string =>
    props.isIdea
        ? ideaStatus(String(cell.value), cell.label)
        : projectStatus(String(cell.value), cell.label);

/** Judul laporan: dari kartu Dashboard bila ada, kalau tidak dari daftar jenis. */
const heading = computed(() => {
    if (props.metric) {
        return metricText(props.metric, props.metricLabel);
    }

    return reportType(props.type, props.types.find((entry) => entry.value === props.type)?.label);
});
</script>

<template>
    <Head :title="t.reports.head" />

    <UiPageHeader :title="t.reports.title">
        <template #actions>
            <UiButton :href="downloadUrl" external>
                <template #icon>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 10l5 5 5-5M12 15V3" />
                    </svg>
                </template>
                {{ t.common.download }}
            </UiButton>
        </template>
    </UiPageHeader>

    <!-- Konteks laporan dinyatakan terang-terangan: ranah, jenis, jumlah baris,
         dan apakah daftar ini dibatasi ke data sendiri. -->
    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <UiBadge :tone="isIdea ? 'info' : 'brand'" size="md">
            {{ isIdea ? t.reports.idea : t.reports.project }}
        </UiBadge>
        <span class="text-ink-subtle">/</span>
        <span class="font-semibold text-ink-muted">{{ heading }}</span>
        <span class="text-ink-subtle">· {{ tr(t.common.records, { count: number(rows.total) }) }}</span>

        <UiBadge v-if="mineOnly" tone="neutral">{{ t.reports.yourDataOnly }}</UiBadge>

        <span
            v-if="metric"
            class="inline-flex items-center gap-2 rounded-full bg-warning-soft px-2.5 py-1 text-xs text-warning-ink ring-1 ring-warning/25"
        >
            {{ t.list.fromDashboard }}
            <AppLink :href="route('reports.index', { type })" class="font-semibold hover:underline">
                {{ t.list.clear }}
            </AppLink>
        </span>
    </div>

    <UiCard flush class="mb-4">
        <div class="p-4">
            <div class="w-full sm:w-72">
                <label class="mb-1 block text-xs font-medium text-ink-label" for="report-type">{{ t.reports.selectReport }}</label>
                <UiSelect
                    id="report-type"
                    :model-value="type"
                    :options="typeOptions"
                    @update:model-value="changeType"
                />
            </div>
        </div>
    </UiCard>

    <UiCard flush>
        <ListToolbar
            :filters="filters"
            :business-units="buOptions"
            :active="hasActiveFilters()"
            :search-placeholder="isIdea ? t.reports.searchIdea : t.reports.searchProject"
            @reset="reset"
        />

        <TabBar :tabs="tabs" :active="filters.tab as string" @select="selectTab" />

        <UiTable
            :loading="loading"
            :empty="rows.data.length === 0"
            :empty-title="t.common.noData"
        >
            <template #head>
                <UiTh
                    v-for="column in columns"
                    :key="column.label"
                    :sort-key="column.sort ?? undefined"
                    :sort="filters.sort as string"
                    :dir="filters.dir"
                    @sort="sortBy"
                >
                    {{ field(column.label) }}
                </UiTh>
                <UiTh align="right">{{ t.fields.action }}</UiTh>
            </template>

            <tr v-for="row in rows.data" :key="row.key" class="align-top transition-colors hover:bg-surface-muted">
                <UiTd v-for="(cell, index) in row.cells" :key="index" nowrap>
                    <template v-if="columns[index]?.kind === 'status'">
                        <UiBadge :tone="tone(cell.value)" dot>{{ statusText(cell) }}</UiBadge>
                    </template>

                    <UiDateTime v-else-if="columns[index]?.kind === 'datetime'" :value="cell.value as string" />

                    <span v-else-if="columns[index]?.kind === 'money'" class="tabular-nums">
                        {{ number(cell.value, '0') }}
                    </span>

                    <span v-else-if="columns[index]?.kind === 'id'" class="font-mono text-xs font-semibold text-brand">
                        {{ cell.value }}
                    </span>

                    <!-- Nama dipendekkan agar tabel tidak melar; teks utuh tetap
                         terbaca lewat tooltip dan halaman detail. -->
                    <span v-else-if="columns[index]?.kind === 'name'" :title="String(cell.value ?? '')">
                        {{ truncate(String(cell.value ?? ''), 36) || '—' }}
                    </span>

                    <span v-else>{{ cell.value === null || cell.value === '' ? '—' : cell.value }}</span>
                </UiTd>

                <UiTd align="right" nowrap>
                    <div class="flex items-center justify-end gap-1.5">
                        <!-- Pensil: bertindak atas nama committee layer aktif.
                             Hanya muncul untuk pemegang 'override.role' pada baris
                             yang memang masih menunggu keputusan. -->
                        <AppLink
                            v-if="row.urls.override"
                            :href="row.urls.override"
                            :external="detailIsExternal"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-warning/40 text-warning-ink transition-colors hover:bg-warning-soft"
                            :title="t.reports.actAsCommitteeTitle"
                        >
                            <span class="sr-only">{{ t.reports.actAsCommittee }}</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                            </svg>
                        </AppLink>

                        <AppLink
                            :href="row.urls.detail"
                            :external="detailIsExternal"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-line-strong text-ink-muted transition-colors hover:bg-surface-muted"
                            :title="t.list.viewDetail"
                        >
                            <span class="sr-only">{{ t.list.viewDetail }}</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
                                <circle cx="12" cy="12" r="2.5" />
                            </svg>
                        </AppLink>
                    </div>
                </UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="filters.per_page" :meta="rows" @page="setPage" />
    </UiCard>
</template>
