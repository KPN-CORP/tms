<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    UiBadge,
    UiCard,
    UiListFooter,
    UiPageHeader,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import ListToolbar from '@/components/list/ListToolbar.vue';
import TabBar from '@/components/list/TabBar.vue';
import MetricNotice from '@/components/list/MetricNotice.vue';
import RowActions from '@/components/list/RowActions.vue';
import { useListQuery } from '@/composables/useListQuery';
import { useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { projectTone } from '@/lib/status';
import { truncate } from '@/lib/format';
import type { ListQuery, Paginated, ProjectStatus } from '@/types/models';

interface PersonCell {
    name: string | null;
    employee_id: string | null;
}

interface ProjectRow {
    id: number;
    project_id: string;
    project_name: string | null;
    project_category: string | null;
    status: ProjectStatus;
    status_label: string;
    leader: PersonCell | null;
    sponsor: PersonCell | null;
    can_edit: boolean;
    urls: { edit: string; view: string };
}

/**
 * Satu halaman untuk Project Proposal, Implementation, dan Completion.
 *
 * Ketiganya memang satu tampilan dengan fase status berbeda — di PHP pun
 * dibangun oleh satu method (ProjectController::projectList), jadi tidak ada
 * gunanya menduplikasi komponennya.
 */
const props = defineProps<{
    projects: Paginated<ProjectRow>;
    filters: ListQuery & { tab: string };
    businessUnits: string[];
    tabs: Array<{ key: string; label: string; count: number }>;
    page: {
        /** Kunci terjemahan fase: proposal | implementation | completion. */
        key: 'proposal' | 'implementation' | 'completion';
        title: string;
        subtitle: string;
        url: string;
        createHint: string | null;
    };
    detailIsExternal: boolean;
    metric: string | null;
    metricLabel: string | null;
}>();

const { t } = useLocale();
const { projectStatus, metric: metricText } = useLabels();

/** Judul & subjudul fase: terjemahan dulu, teks server sebagai cadangan. */
const heading = computed(() => t.value.projects.pages[props.page.key]?.title ?? props.page.title);
const subheading = computed(() => t.value.projects.pages[props.page.key]?.subtitle ?? props.page.subtitle);

const { filters, loading, sortBy, setPage, reset, hasActiveFilters } = useListQuery({
    url: props.page.url,
    initial: props.filters,
    only: ['projects', 'tabs', 'filters'],
});

const selectTab = (key: string): void => {
    router.get(
        props.page.url,
        { ...props.filters, tab: key, page: undefined },
        { only: ['projects', 'tabs', 'filters'], preserveState: true, preserveScroll: true, replace: true },
    );
};

const buOptions = computed(() => props.businessUnits.map((name) => ({ value: name, label: name })));
</script>

<template>
    <Head :title="heading" />

    <UiPageHeader :title="heading" :description="subheading" />

    <MetricNotice
        v-if="metric && metricLabel"
        :label="metricText(metric, metricLabel)"
        :clear-href="page.url"
    />

    <TabBar :tabs="tabs" :active="filters.tab as string" class="mb-4" @select="selectTab" />

    <UiCard flush>
        <ListToolbar
            :filters="filters"
            :business-units="buOptions"
            :active="hasActiveFilters()"
            :search-placeholder="t.projects.searchPlaceholder"
            @reset="reset"
        />

        <UiTable
            :loading="loading"
            :empty="projects.data.length === 0"
            :empty-title="t.projects.emptyTitle"
            :empty-message="page.createHint ?? t.projects.emptyMessage"
        >
            <template #head>
                <UiTh width="w-14" align="center">{{ t.fields.no }}</UiTh>
                <UiTh sort-key="project_id" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.projectId }}</UiTh>
                <UiTh sort-key="project_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.projectName }}</UiTh>
                <UiTh sort-key="project_category" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.category }}</UiTh>
                <UiTh>{{ t.fields.leader }}</UiTh>
                <UiTh>{{ t.fields.sponsor }}</UiTh>
                <UiTh sort-key="status" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.status }}</UiTh>
                <UiTh align="right">{{ t.fields.action }}</UiTh>
            </template>

            <tr v-for="(project, index) in projects.data" :key="project.id" class="transition-colors hover:bg-surface-muted">
                <UiTd align="center" muted>{{ (projects.from ?? 0) + index }}</UiTd>

                <UiTd nowrap>
                    <span class="font-mono text-xs font-semibold text-brand">{{ project.project_id }}</span>
                </UiTd>

                <UiTd strong>
                    <span :title="project.project_name ?? undefined">
                        {{ truncate(project.project_name, 44) || t.common.untitled }}
                    </span>
                </UiTd>

                <UiTd>{{ project.project_category ?? '-' }}</UiTd>

                <UiTd>
                    <p class="text-ink">{{ project.leader?.name ?? '-' }}</p>
                    <p v-if="project.leader?.employee_id" class="text-xs text-ink-subtle">
                        {{ project.leader.employee_id }}
                    </p>
                </UiTd>

                <UiTd>
                    <p class="text-ink">{{ project.sponsor?.name ?? '-' }}</p>
                    <p v-if="project.sponsor?.employee_id" class="text-xs text-ink-subtle">
                        {{ project.sponsor.employee_id }}
                    </p>
                </UiTd>

                <UiTd>
                    <UiBadge :tone="projectTone(project.status)" dot>
                        {{ projectStatus(project.status, project.status_label) }}
                    </UiBadge>
                </UiTd>

                <UiTd align="right">
                    <RowActions
                        :edit-href="project.can_edit ? project.urls.edit : null"
                        :view-href="project.urls.view"
                        :external="detailIsExternal"
                    />
                </UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="filters.per_page" :meta="projects" @page="setPage" />
    </UiCard>
</template>
