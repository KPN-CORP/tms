<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    UiBadge,
    UiButton,
    UiCard,
    UiListFooter,
    UiPageHeader,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import ListToolbar from '@/components/list/ListToolbar.vue';
import TabBar from '@/components/list/TabBar.vue';
import RowActions from '@/components/list/RowActions.vue';
import { useListQuery } from '@/composables/useListQuery';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { shellTone } from '@/lib/status';
import { truncate } from '@/lib/format';
import type { ListQuery, Paginated, ShellStatus } from '@/types/models';

interface PersonCell {
    name: string | null;
    employee_id: string | null;
}

interface ShellRow {
    idea_id: string;
    idea_name: string | null;
    shell_id: number | null;
    project_id: string | null;
    project_name: string | null;
    project_category: string | null;
    leader: PersonCell | null;
    sponsor: PersonCell | null;
    shell_status: ShellStatus;
    shell_status_label: string;
    can_create_shell: boolean;
    urls: { create: string; progress: string | null; destroy_draft: string | null };
    external: { create: boolean; progress: boolean };
}

const props = defineProps<{
    rows: Paginated<ShellRow>;
    filters: ListQuery & { tab: string };
    businessUnits: string[];
    tabs: Array<{ key: string; label: string; count: number }>;
}>();

const { filters, loading, sortBy, setPage, reset, hasActiveFilters } = useListQuery({
    url: route('projects.shell'),
    initial: props.filters,
    only: ['rows', 'tabs', 'filters'],
});

const { confirm } = useConfirm();
const { t } = useLocale();
const { shellStatus } = useLabels();

const selectTab = (key: string): void => {
    router.get(
        route('projects.shell'),
        { ...props.filters, tab: key, page: undefined },
        { only: ['rows', 'tabs', 'filters'], preserveState: true, preserveScroll: true, replace: true },
    );
};

/**
 * Admin yang bukan committee layer terakhir tetap melihat tombolnya, tapi
 * mendapat penjelasan — bukan halaman 403 setelah klik.
 */
const explainDenied = (action: 'create' | 'continue'): void => {
    void confirm({
        title: t.value.projects.notAllowed,
        message:
            action === 'create'
                ? t.value.projects.onlyLastLayerCreate
                : t.value.projects.onlyLastLayerEdit,
        confirmLabel: 'OK',
        cancelLabel: t.value.common.close,
    });
};

const deleteDraft = async (row: ShellRow): Promise<void> => {
    if (!row.urls.destroy_draft) {
        return;
    }

    const ok = await confirm({
        title: t.value.projects.deleteDraftTitle,
        message: tr(t.value.projects.deleteDraftMessage, {
            name: row.project_name || row.idea_id,
        }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(row.urls.destroy_draft, { preserveScroll: true });
    }
};

const buOptions = computed(() => props.businessUnits.map((name) => ({ value: name, label: name })));
</script>

<template>
    <Head :title="t.projects.shellHead" />

    <UiPageHeader :title="t.projects.shellTitle" :description="t.projects.shellDescription" />

    <TabBar :tabs="tabs" :active="filters.tab as string" class="mb-4" @select="selectTab" />

    <UiCard flush>
        <ListToolbar
            :filters="filters"
            :business-units="buOptions"
            :active="hasActiveFilters()"
            :search-placeholder="t.projects.shellSearch"
            @reset="reset"
        />

        <UiTable
            :loading="loading"
            :empty="rows.data.length === 0"
            :empty-title="t.projects.shellEmptyTitle"
            :empty-message="t.projects.shellEmptyMessage"
        >
            <template #head>
                <UiTh width="w-14" align="center">{{ t.fields.no }}</UiTh>
                <UiTh sort-key="idea_id" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.ideaId }}</UiTh>
                <UiTh sort-key="project_id" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.projectId }}</UiTh>
                <UiTh sort-key="project_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.projectName }}</UiTh>
                <UiTh sort-key="project_category" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.category }}</UiTh>
                <UiTh>{{ t.fields.leader }}</UiTh>
                <UiTh>{{ t.fields.sponsor }}</UiTh>
                <UiTh sort-key="shell_status" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.status }}</UiTh>
                <UiTh align="right">{{ t.fields.action }}</UiTh>
            </template>

            <tr v-for="(row, index) in rows.data" :key="`${row.idea_id}-${row.shell_id ?? 'none'}`" class="transition-colors hover:bg-surface-muted">
                <UiTd align="center" muted>{{ (rows.from ?? 0) + index }}</UiTd>

                <UiTd nowrap>
                    <span class="font-mono text-xs font-semibold text-brand">{{ row.idea_id }}</span>
                </UiTd>

                <UiTd nowrap>
                    <span v-if="row.project_id" class="font-mono text-xs font-semibold text-brand">
                        {{ row.project_id }}
                    </span>
                    <span v-else class="text-ink-subtle">—</span>
                </UiTd>

                <UiTd strong>
                    <span :title="row.project_name ?? undefined">
                        {{ truncate(row.project_name, 44) || t.common.untitled }}
                    </span>
                </UiTd>

                <UiTd>{{ row.project_category ?? '—' }}</UiTd>

                <UiTd>
                    <p class="text-ink">{{ row.leader?.name ?? '—' }}</p>
                    <p v-if="row.leader?.employee_id" class="text-xs text-ink-subtle">{{ row.leader.employee_id }}</p>
                </UiTd>

                <UiTd>
                    <p class="text-ink">{{ row.sponsor?.name ?? '—' }}</p>
                    <p v-if="row.sponsor?.employee_id" class="text-xs text-ink-subtle">{{ row.sponsor.employee_id }}</p>
                </UiTd>

                <UiTd>
                    <UiBadge :tone="shellTone(row.shell_status)" dot>
                        {{ shellStatus(row.shell_status, row.shell_status_label) }}
                    </UiBadge>
                </UiTd>

                <UiTd align="right">
                    <div class="flex items-center justify-end gap-2">
                        <template v-if="row.shell_status === 'not_assigned'">
                            <UiButton v-if="row.can_create_shell" size="sm" :href="row.urls.create" :external="row.external.create">
                                {{ t.projects.createProject }}
                            </UiButton>
                            <UiButton v-else size="sm" @click="explainDenied('create')">
                                {{ t.projects.createProject }}
                            </UiButton>
                        </template>

                        <template v-else-if="row.shell_status === 'draft'">
                            <UiButton
                                v-if="row.can_create_shell"
                                size="sm"
                                variant="secondary"
                                :href="row.urls.create"
                                :external="row.external.create"
                            >
                                {{ t.common.edit }}
                            </UiButton>
                            <UiButton v-else size="sm" variant="secondary" @click="explainDenied('continue')">
                                {{ t.common.edit }}
                            </UiButton>

                            <UiButton size="sm" variant="ghost" class="text-danger" @click="deleteDraft(row)">
                                {{ t.common.delete }}
                            </UiButton>
                        </template>

                        <RowActions v-else-if="row.urls.progress" :view-href="row.urls.progress" :external="row.external.progress" />
                    </div>
                </UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="filters.per_page" :meta="rows" @page="setPage" />
    </UiCard>
</template>
