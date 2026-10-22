<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    UiBadge,
    UiCard,
    UiDateTime,
    UiListFooter,
    UiPageHeader,
    UiSelect,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { cleanQuery } from '@/lib/query';
import type { Paginated, SelectOption } from '@/types/models';
import type { Tone } from '@/lib/status';

interface Change {
    field: string;
    old: string;
    new: string;
}

interface LogRow {
    id: number;
    created_at: string | null;
    causer: string;
    event: string;
    event_label: string;
    subject_short: string | null;
    subject_label: string | null;
    changes: Change[];
}

const props = defineProps<{
    logs: Paginated<LogRow>;
    types: SelectOption[];
    filters: {
        type: string;
        event: string;
        per_page: number;
        sort: string;
        dir: 'asc' | 'desc';
    };
    urls: { index: string };
}>();

/**
 * Halaman ini tidak muncul di sidebar (menunya sengaja disembunyikan), tapi
 * rutenya hidup dan pencatatan audit tetap berjalan.
 */
const { t } = useLocale();
const { subjectType } = useLabels();

/** Nilai filter = nama kelas lengkap; labelnya class_basename dari server. */
const typeOptions = computed(() =>
    props.types.map((option) => ({
        ...option,
        label: subjectType(option.label, option.label),
    })),
);

const EVENTS = computed<SelectOption[]>(() => [
    { value: 'created', label: t.value.admin.activityLog.events.created },
    { value: 'updated', label: t.value.admin.activityLog.events.updated },
    { value: 'deleted', label: t.value.admin.activityLog.events.deleted },
]);

/** Label event dikirim server; kodenya yang diterjemahkan. */
const eventLabel = (event: string, fallback: string): string =>
    (t.value.admin.activityLog.events as Record<string, string | undefined>)[event] ?? fallback;

const TONES: Record<string, Tone> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
};

type Patch = Partial<{
    type: string;
    event: string;
    page: number | undefined;
    per_page: number | string | undefined;
    sort: string;
    dir: 'asc' | 'desc';
}>;

const apply = (patch: Patch): void => {
    router.get(props.urls.index, cleanQuery({ ...props.filters, ...patch }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

/**
 * Sort dikerjakan SERVER: tabel ini bisa berisi ratusan ribu baris dan sudah
 * dipaginasi di query, jadi mengurutkan satu halaman di klien akan mengurutkan
 * potongan yang salah.
 */
const sortBy = (key: string): void => {
    const sameColumn = props.filters.sort === key;

    apply({
        sort: key,
        dir: sameColumn && props.filters.dir === 'asc' ? 'desc' : 'asc',
        page: undefined,
    });
};

const perPage = computed({
    get: () => props.filters.per_page,
    set: (value) => apply({ per_page: value, page: undefined }),
});
</script>

<template>
    <Head :title="t.admin.activityLog.head" />

    <UiPageHeader :title="t.admin.activityLog.head" :description="t.admin.activityLog.description" />

    <UiCard flush>
        <div class="flex flex-wrap items-end gap-3 border-b border-line px-4 py-4">
            <div class="w-full sm:w-64">
                <label class="mb-1 block text-xs font-medium text-ink-label">{{ t.admin.activityLog.subject }}</label>
                <UiSelect
                    :model-value="filters.type || null"
                    :options="typeOptions"
                    :placeholder="t.admin.activityLog.allSubjects"
                    @update:model-value="apply({ type: ($event as string) ?? '', page: undefined })"
                />
            </div>

            <div class="w-full sm:w-48">
                <label class="mb-1 block text-xs font-medium text-ink-label">{{ t.admin.activityLog.event }}</label>
                <UiSelect
                    :model-value="filters.event || null"
                    :options="EVENTS"
                    :placeholder="t.admin.activityLog.allEvents"
                    @update:model-value="apply({ event: ($event as string) ?? '', page: undefined })"
                />
            </div>
        </div>

        <UiTable
            :empty="logs.data.length === 0"
            :empty-title="t.admin.activityLog.emptyTitle"
            :empty-message="t.admin.activityLog.emptyMessage"
        >
            <template #head>
                <UiTh sort-key="created_at" :sort="filters.sort" :dir="filters.dir" @sort="sortBy">
                    {{ t.admin.activityLog.time }}
                </UiTh>
                <UiTh sort-key="causer" :sort="filters.sort" :dir="filters.dir" @sort="sortBy">
                    {{ t.fields.user }}
                </UiTh>
                <UiTh sort-key="event" :sort="filters.sort" :dir="filters.dir" @sort="sortBy">
                    {{ t.admin.activityLog.event }}
                </UiTh>
                <UiTh sort-key="subject" :sort="filters.sort" :dir="filters.dir" @sort="sortBy">
                    {{ t.admin.activityLog.subject }}
                </UiTh>
                <UiTh>{{ t.admin.activityLog.changes }}</UiTh>
            </template>

            <tr v-for="log in logs.data" :key="log.id" class="align-top transition-colors hover:bg-surface-muted">
                <UiTd nowrap muted class="text-xs">
                    <UiDateTime :value="log.created_at" />
                </UiTd>

                <UiTd class="text-xs">{{ log.causer }}</UiTd>

                <UiTd>
                    <UiBadge :tone="TONES[log.event] ?? 'neutral'">
                        {{ eventLabel(log.event, log.event_label) }}
                    </UiBadge>
                </UiTd>

                <UiTd>
                    <p class="text-xs font-semibold text-ink-muted">{{ log.subject_short ?? '—' }}</p>
                    <p class="text-xs text-ink-subtle">{{ log.subject_label }}</p>
                </UiTd>

                <UiTd>
                    <span v-if="!log.changes.length" class="text-xs text-ink-subtle">—</span>

                    <details v-else>
                        <summary class="cursor-pointer select-none text-xs text-brand">
                            {{ tr(t.admin.activityLog.fieldCount, { count: log.changes.length }) }}
                        </summary>

                        <div class="mt-2 space-y-1">
                            <div v-for="change in log.changes" :key="change.field" class="text-xs">
                                <span class="font-mono font-semibold text-ink-muted">{{ change.field }}</span>:

                                <template v-if="log.event === 'updated'">
                                    <span class="text-ink-subtle line-through">{{ change.old || '∅' }}</span>
                                    <span class="text-ink-subtle"> → </span>
                                </template>

                                <span class="text-ink">{{ change.new || '∅' }}</span>
                            </div>
                        </div>
                    </details>
                </UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="perPage" :meta="logs" @page="apply({ page: $event })" />
    </UiCard>
</template>
