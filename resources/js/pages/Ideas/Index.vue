<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    UiBadge,
    UiButton,
    UiCard,
    UiDateTime,
    UiListFooter,
    UiPageHeader,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import ListToolbar from '@/components/list/ListToolbar.vue';
import TabBar from '@/components/list/TabBar.vue';
import MetricNotice from '@/components/list/MetricNotice.vue';
import { useListQuery } from '@/composables/useListQuery';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { ideaTone } from '@/lib/status';
import { truncate } from '@/lib/format';
import type { IdeaStatus, ListQuery, Paginated } from '@/types/models';

interface IdeaRow {
    id: number;
    idea_id: string;
    idea_name: string | null;
    status: IdeaStatus;
    status_label: string;
    business_unit_name: string | null;
    department_name: string | null;
    modified_at: string | null;
    can_edit: boolean;
    urls: { show: string; edit: string; destroy: string };
}

const props = defineProps<{
    ideas: Paginated<IdeaRow>;
    filters: ListQuery & { tab: string };
    businessUnits: string[];
    tabs: Array<{ key: string; label: string; count: number }>;
    metric: string | null;
    metricLabel: string | null;
}>();

/**
 * Hanya prop `ideas` yang dimuat ulang saat filter berubah — hitungan tab dan
 * daftar Business Unit tidak ikut dihitung ulang di server.
 */
const { filters, loading, sortBy, setPage, reset, hasActiveFilters } = useListQuery({
    url: route('ideas.index'),
    initial: props.filters,
    only: ['ideas', 'tabs', 'filters'],
});

const { confirm } = useConfirm();
const { t } = useLocale();
const { ideaStatus } = useLabels();

const selectTab = (key: string): void => {
    filters.tab = key;
    filters.page = undefined;
    router.get(
        route('ideas.index'),
        { ...props.filters, tab: key, page: undefined },
        { only: ['ideas', 'tabs', 'filters'], preserveState: true, preserveScroll: true, replace: true },
    );
};

const destroy = async (idea: IdeaRow): Promise<void> => {
    const ok = await confirm({
        title: t.value.ideas.deleteTitle,
        message: tr(t.value.ideas.deleteMessage, {
            name: idea.idea_name || t.value.ideas.deleteUnnamed,
        }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(idea.urls.destroy, { preserveScroll: true });
    }
};

const buOptions = computed(() => props.businessUnits.map((name) => ({ value: name, label: name })));
</script>

<template>
    <Head :title="t.ideas.indexHead" />

    <UiPageHeader :title="t.ideas.indexTitle" :description="t.ideas.indexDescription">
        <template #actions>
            <UiButton :href="route('ideas.create')">
                <template #icon>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 4.25a.75.75 0 0 1 .75.75v4.25H15a.75.75 0 0 1 0 1.5h-4.25V15a.75.75 0 0 1-1.5 0v-4.25H5a.75.75 0 0 1 0-1.5h4.25V5a.75.75 0 0 1 .75-.75Z" />
                    </svg>
                </template>
                {{ t.ideas.createButton }}
            </UiButton>
        </template>
    </UiPageHeader>

    <MetricNotice v-if="metric && metricLabel" :label="metricLabel" :clear-href="route('ideas.index')" />

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
            :empty-title="t.ideas.emptyTitle"
            :empty-message="t.ideas.emptyMessage"
        >
            <template #head>
                <UiTh width="w-14" align="center">{{ t.fields.no }}</UiTh>
                <UiTh sort-key="idea_id" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.ideaId }}</UiTh>
                <UiTh sort-key="idea_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.ideaName }}</UiTh>
                <UiTh sort-key="business_unit_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.targetBu }}</UiTh>
                <UiTh sort-key="department_name" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.targetUnit }}</UiTh>
                <UiTh sort-key="status" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.status }}</UiTh>
                <UiTh sort-key="modified_at" :sort="filters.sort as string" :dir="filters.dir" @sort="sortBy">{{ t.fields.lastUpdate }}</UiTh>
                <UiTh align="right">{{ t.fields.action }}</UiTh>
            </template>

            <tr v-for="(idea, index) in ideas.data" :key="idea.id" class="transition-colors hover:bg-surface-muted">
                <UiTd align="center" muted>{{ (ideas.from ?? 0) + index }}</UiTd>

                <UiTd nowrap>
                    <span class="font-mono text-xs font-semibold text-brand">{{ idea.idea_id }}</span>
                </UiTd>

                <UiTd strong>
                    <span v-if="idea.idea_name" :title="idea.idea_name">{{ truncate(idea.idea_name, 48) }}</span>
                    <!-- Draft kosong tetap harus terbaca sebagai baris. -->
                    <span v-else class="italic text-ink-subtle">{{ t.common.untitledDraft }}</span>
                </UiTd>

                <UiTd>{{ idea.business_unit_name ?? '-' }}</UiTd>
                <UiTd>{{ idea.department_name ?? '-' }}</UiTd>

                <UiTd>
                    <UiBadge :tone="ideaTone(idea.status)" dot>
                        {{ ideaStatus(idea.status, idea.status_label) }}
                    </UiBadge>
                </UiTd>

                <UiTd nowrap muted>
                    <UiDateTime :value="idea.modified_at" mode="date" />
                </UiTd>

                <UiTd align="right">
                    <div class="flex items-center justify-end gap-2">
                        <template v-if="idea.can_edit">
                            <UiButton size="sm" variant="secondary" :href="idea.urls.edit">
                                {{ t.common.edit }}
                            </UiButton>
                            <UiButton size="sm" variant="ghost" class="text-danger" @click="destroy(idea)">
                                {{ t.common.delete }}
                            </UiButton>
                        </template>

                        <UiButton v-else size="sm" variant="secondary" :href="idea.urls.show">
                            {{ t.common.detail }}
                        </UiButton>
                    </div>
                </UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="filters.per_page" :meta="ideas" @page="setPage" />
    </UiCard>
</template>
