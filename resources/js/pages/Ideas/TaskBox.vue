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
    UiSlaBadge,
    UiTable,
    UiTd,
    UiTh,
    UiTooltip,
} from '@/components/ui';
import ListToolbar from '@/components/list/ListToolbar.vue';
import TabBar from '@/components/list/TabBar.vue';
import { useListQuery } from '@/composables/useListQuery';
import { useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { ideaTone } from '@/lib/status';
import { truncate } from '@/lib/format';
import type { IdeaStatus, ListQuery, Paginated, SlaState } from '@/types/models';

interface LayerParticipant {
    layer: number;
    name: string;
}

interface TaskRow {
    id: number;
    idea_id: string;
    idea_name: string | null;
    status: IdeaStatus;
    status_label: string;
    submitter_name: string | null;
    business_unit_name: string | null;
    department_name: string | null;
    current_layer: number | null;
    layer_chain: LayerParticipant[];
    sla: { state: SlaState | 'none'; due: string | null; left: number | null };
    can_review: boolean;
    can_create_shell: boolean;
    urls: { review: string; create_project: string };
    create_is_external: boolean;
}

const props = defineProps<{
    ideas: Paginated<TaskRow>;
    filters: ListQuery & { tab: string };
    businessUnits: string[];
    tabs: Array<{ key: string; label: string; count: number }>;
}>();

const { t } = useLocale();
const { ideaStatus } = useLabels();

const { filters, loading, sortBy, setPage, reset, hasActiveFilters } = useListQuery({
    url: route('ideas.taskbox'),
    initial: props.filters,
    only: ['ideas', 'tabs', 'filters'],
});

const selectTab = (key: string): void => {
    router.get(
        route('ideas.taskbox'),
        { ...props.filters, tab: key, page: undefined },
        { only: ['ideas', 'tabs', 'filters'], preserveState: true, preserveScroll: true, replace: true },
    );
};

const buOptions = computed(() => props.businessUnits.map((name) => ({ value: name, label: name })));
</script>

<template>
    <Head :title="t.ideas.taskBoxHead" />

    <UiPageHeader :title="t.ideas.taskBoxTitle" :description="t.ideas.taskBoxDescription" />

    <TabBar :tabs="tabs" :active="filters.tab as string" class="mb-4" @select="selectTab" />

    <UiCard flush>
        <ListToolbar
            :filters="filters"
            :business-units="buOptions"
            :active="hasActiveFilters()"
            :search-placeholder="t.ideas.searchPlaceholder"
            @reset="reset"
        />

        <UiTable
            :loading="loading"
            :empty="ideas.data.length === 0"
            :empty-title="t.ideas.taskBoxEmptyTitle"
            :empty-message="t.ideas.taskBoxEmptyMessage"
        >
            <template #head>
                <UiTh width="w-14" align="center">{{ t.fields.no }}</UiTh>
                <UiTh sort-key="idea_id" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.ideaId }}</UiTh>
                <UiTh sort-key="idea_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.ideaName }}</UiTh>
                <UiTh>{{ t.fields.submitter }}</UiTh>
                <UiTh sort-key="business_unit_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.targetBu }}</UiTh>
                <UiTh sort-key="department_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.targetUnit }}</UiTh>
                <UiTh sort-key="current_layer" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.layer }}</UiTh>
                <UiTh sort-key="status" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.status }}</UiTh>
                <UiTh>{{ t.fields.sla }}</UiTh>
                <UiTh align="right">{{ t.fields.action }}</UiTh>
            </template>

            <tr v-for="(idea, index) in ideas.data" :key="idea.id" class="transition-colors hover:bg-surface-muted">
                <UiTd align="center" muted>{{ (ideas.from ?? 0) + index }}</UiTd>

                <UiTd nowrap>
                    <span class="font-mono text-xs font-semibold text-brand">{{ idea.idea_id }}</span>
                </UiTd>

                <UiTd strong>
                    <span :title="idea.idea_name ?? undefined">
                        {{ truncate(idea.idea_name, 44) || t.common.untitled }}
                    </span>
                </UiTd>

                <UiTd>{{ idea.submitter_name ?? '-' }}</UiTd>
                <UiTd>{{ idea.business_unit_name ?? '-' }}</UiTd>
                <UiTd>{{ idea.department_name ?? '-' }}</UiTd>

                <UiTd>
                    <UiTooltip :label="t.ideas.committeeLayers">
                        <UiBadge tone="neutral">L{{ idea.current_layer }}</UiBadge>

                        <template #content>
                            <p class="mb-1 font-semibold text-ink-subtle">{{ t.ideas.committeeLayers }}</p>

                            <div
                                v-for="participant in idea.layer_chain"
                                :key="`${participant.layer}-${participant.name}`"
                                class="flex items-center gap-2 whitespace-nowrap py-0.5"
                            >
                                <span
                                    class="inline-flex min-w-7 justify-center rounded px-1 py-px font-semibold"
                                    :class="
                                        participant.layer === idea.current_layer
                                            ? 'bg-brand-soft text-brand-ink'
                                            : 'bg-surface-muted text-ink-subtle'
                                    "
                                >
                                    L{{ participant.layer }}
                                </span>

                                <span :class="participant.layer === idea.current_layer ? 'font-semibold text-brand-ink' : 'text-ink-muted'">
                                    {{ participant.name }}
                                </span>
                            </div>

                            <p v-if="!idea.layer_chain.length" class="text-ink-subtle">{{ t.ideas.noCommittee }}</p>
                        </template>
                    </UiTooltip>
                </UiTd>

                <UiTd>
                    <UiBadge :tone="ideaTone(idea.status)" dot>
                        {{ ideaStatus(idea.status, idea.status_label) }}
                    </UiBadge>
                </UiTd>

                <UiTd nowrap>
                    <UiSlaBadge :sla="idea.sla" />
                </UiTd>

                <UiTd align="right">
                    <UiButton v-if="idea.can_review" size="sm" :href="idea.urls.review">
                        {{ t.ideas.review }}
                    </UiButton>

                    <UiButton
                        v-else-if="idea.can_create_shell"
                        size="sm"
                        :href="idea.urls.create_project"
                        :external="idea.create_is_external"
                    >
                        {{ t.ideas.createProject }}
                    </UiButton>

                    <UiButton v-else size="sm" variant="secondary" :href="idea.urls.review">
                        {{ t.common.detail }}
                    </UiButton>
                </UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="filters.per_page" :meta="ideas" @page="setPage" />
    </UiCard>
</template>
