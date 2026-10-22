<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    UiBadge,
    UiButton,
    UiCard,
    UiListFooter,
    UiPageHeader,
    UiSlaBadge,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import ListToolbar from '@/components/list/ListToolbar.vue';
import { useListQuery } from '@/composables/useListQuery';
import { useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { projectTone, type Tone } from '@/lib/status';
import { truncate } from '@/lib/format';
import type { ListQuery, Paginated, ProjectStatus, SlaState } from '@/types/models';

type ReviewKind = 'completion' | 'change_request' | 'proposal';

interface ReviewRow {
    id: number;
    project_id: string;
    project_name: string | null;
    leader_name: string | null;
    business_unit_name: string | null;
    status: ProjectStatus;
    status_label: string;
    current_layer: number | null;
    review_kind: ReviewKind;
    as_sponsor: boolean;
    sla: { state: SlaState | 'none'; due: string | null; left: number | null };
    urls: { review: string };
    external: boolean;
}

const props = defineProps<{
    projects: Paginated<ReviewRow>;
    filters: ListQuery;
    businessUnits: string[];
}>();

const { t } = useLocale();
const { projectStatus } = useLabels();

const { filters, loading, sortBy, setPage, reset, hasActiveFilters } = useListQuery({
    url: route('projects.review'),
    initial: props.filters,
    only: ['projects', 'filters'],
});

/** Nada warna per jenis review; labelnya ikut kamus. */
const KIND_TONES: Record<ReviewKind, Tone> = {
    completion: 'success',
    change_request: 'info',
    proposal: 'accent',
};

const buOptions = computed(() => props.businessUnits.map((name) => ({ value: name, label: name })));
</script>

<template>
    <Head :title="t.projects.reviewHead" />

    <UiPageHeader :title="t.projects.reviewTitle" :description="t.projects.reviewDescription" />

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
            :empty-title="t.projects.reviewEmptyTitle"
            :empty-message="t.projects.reviewEmptyMessage"
        >
            <template #head>
                <UiTh width="w-14" align="center">{{ t.fields.no }}</UiTh>
                <UiTh sort-key="project_id" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.projectId }}</UiTh>
                <UiTh sort-key="project_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.projectName }}</UiTh>
                <UiTh>{{ t.fields.leader }}</UiTh>
                <UiTh>{{ t.fields.targetBu }}</UiTh>
                <UiTh>{{ t.projects.reviewColumn }}</UiTh>
                <UiTh sort-key="current_layer" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.layer }}</UiTh>
                <UiTh>{{ t.fields.sla }}</UiTh>
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
                        {{ truncate(project.project_name, 40) || t.common.untitled }}
                    </span>
                </UiTd>

                <UiTd>{{ project.leader_name ?? '-' }}</UiTd>
                <UiTd>{{ project.business_unit_name ?? '-' }}</UiTd>

                <UiTd>
                    <UiBadge :tone="KIND_TONES[project.review_kind]">
                        {{ t.projects.kinds[project.review_kind] }}
                    </UiBadge>
                    <p v-if="project.as_sponsor" class="mt-0.5 text-[11px] text-ink-subtle">
                        {{ t.projects.asSponsor }}
                    </p>
                </UiTd>

                <UiTd>
                    <UiBadge tone="neutral">L{{ project.current_layer }}</UiBadge>
                </UiTd>

                <UiTd nowrap>
                    <UiSlaBadge :sla="project.sla" />
                </UiTd>

                <UiTd>
                    <UiBadge :tone="projectTone(project.status)" dot>
                        {{ projectStatus(project.status, project.status_label) }}
                    </UiBadge>
                </UiTd>

                <UiTd align="right">
                    <UiButton size="sm" :href="project.urls.review" :external="project.external">
                        {{ t.projects.review }}
                    </UiButton>
                </UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="filters.per_page" :meta="projects" @page="setPage" />
    </UiCard>
</template>
