<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    UiBadge,
    UiButton,
    UiCard,
    UiListFooter,
    UiPageHeader,
    UiTable,
    UiTd,
    UiTh,
    UiTooltip,
} from '@/components/ui';
import TabBar from '@/components/list/TabBar.vue';
import CoverageWarnings, { type Coverage } from '@/components/admin/CoverageWarnings.vue';
import { useClientTable } from '@/composables/useClientTable';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';

interface TypeOption {
    value: string;
    label: string;
    usesBudgetRange: boolean;
}

interface Layer {
    layer: number;
    name: string | null;
    /** Layer bawaan Project Sponsor — dibedakan lewat warna badge saja. */
    reserved?: boolean;
}

interface CommitteeSet {
    approval_type: string;
    type_label: string;
    business_unit_id: number;
    bu_name: string | null;
    department_id: number | null;
    dept_name: string;
    budget_min: number | null;
    budget_max: number | null;
    budget_label: string | null;
    layers: Layer[];
    edit_url: string;
}

const props = defineProps<{
    types: TypeOption[];
    activeType: string;
    sets: CommitteeSet[];
    coverage: Coverage;
    urls: { create: string; destroy: string };
}>();

const { confirm } = useConfirm();
const { t } = useLocale();
const { approvalType } = useLabels();

/** Tab dipilih di klien: semua set sudah ada di payload. */
const tab = ref(props.activeType);

// computed, bukan const: judulnya harus ikut berubah saat bahasa diganti.
const tabs = computed(() =>
    props.types.map((type) => ({
        key: String(type.value),
        label: approvalType(String(type.value), type.label),
        count: props.sets.filter((set) => set.approval_type === type.value).length,
    })),
);

const setsFor = (type: string): CommitteeSet[] =>
    props.sets.filter((set) => set.approval_type === type);

const currentType = computed(() => props.types.find((type) => type.value === tab.value) ?? null);

const usesBudgetRange = computed(() => currentType.value?.usesBudgetRange ?? false);

/**
 * Hanya tab yang terbuka yang dirender. Sebelumnya semua tipe ikut dirender
 * lalu disembunyikan dengan v-show; satu tabel membuat sort & halaman punya
 * satu keadaan, bukan satu per tipe.
 */
const {
    rows: pageSets,
    sort,
    dir,
    perPage,
    meta,
    sortBy,
    setPage,
} = useClientTable({
    rows: () => setsFor(tab.value),
    columns: {
        bu_name: (set) => set.bu_name,
        dept_name: (set) => set.dept_name,
        // Rentang diurutkan dari batas bawahnya; rentang terbuka ("≥ N") tetap
        // punya batas bawah, jadi urutannya tidak pernah kosong.
        budget: (set) => set.budget_min,
        layers: (set) => set.layers.length,
    },
    defaultSort: 'bu_name',
});

// Pindah tab selalu mulai dari halaman 1 — jumlah set tiap tipe berbeda.
watch(tab, () => setPage(1));

const remove = async (set: CommitteeSet): Promise<void> => {
    const ok = await confirm({
        title: t.value.admin.committee.deleteTitle,
        message: tr(t.value.admin.committee.deleteMessage, {
            label: approvalType(set.approval_type, set.type_label),
            bu: set.bu_name ?? '-',
            unit: set.dept_name,
        }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (!ok) {
        return;
    }

    // Satu set diidentifikasi oleh kombinasinya, bukan sebuah id tunggal.
    router.delete(props.urls.destroy, {
        data: {
            approval_type: set.approval_type,
            business_unit_id: set.business_unit_id,
            department_id: set.department_id,
            ...(set.budget_min !== null ? { budget_min: set.budget_min } : {}),
            ...(set.budget_max !== null ? { budget_max: set.budget_max } : {}),
        },
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t.admin.committee.head" />

    <UiPageHeader :title="t.admin.committee.head" :description="t.admin.committee.description">
        <template #actions>
            <UiButton :href="urls.create">{{ t.admin.committee.addCommittee }}</UiButton>
        </template>
    </UiPageHeader>

    <div class="space-y-5">
        <CoverageWarnings :coverage="coverage" />

        <UiCard flush>
            <header class="flex items-center justify-between gap-3 border-b border-line bg-surface-muted/70 px-5 py-3.5 text-sm font-semibold text-ink">
                <span>{{ t.admin.committee.configured }}</span>
                <span class="rounded-full bg-brand/10 px-2.5 py-0.5 text-xs font-semibold text-brand">
                    {{ tr(t.admin.committee.setCount, { count: sets.length }) }}
                </span>
            </header>

            <TabBar :tabs="tabs" :active="tab" @select="tab = $event" />

            <div v-if="currentType">
                <UiTable
                    :empty="meta.total === 0"
                    :empty-title="t.admin.committee.emptyTitle"
                    :empty-message="
                        tr(t.admin.committee.emptyMessage, {
                            label: approvalType(String(currentType.value), currentType.label),
                        })
                    "
                >
                    <template #head>
                        <UiTh sort-key="bu_name" :sort="sort" :dir="dir" @sort="sortBy">
                            {{ t.fields.businessUnit }}
                        </UiTh>
                        <UiTh sort-key="dept_name" :sort="sort" :dir="dir" @sort="sortBy">
                            {{ t.fields.unit }}
                        </UiTh>
                        <UiTh v-if="usesBudgetRange" sort-key="budget" :sort="sort" :dir="dir" @sort="sortBy">
                            {{ t.admin.committee.budget }}
                        </UiTh>
                        <UiTh sort-key="layers" :sort="sort" :dir="dir" @sort="sortBy">
                            {{ t.admin.committee.layer }}
                        </UiTh>
                        <UiTh align="right">{{ t.fields.action }}</UiTh>
                    </template>

                    <tr
                        v-for="set in pageSets"
                        :key="`${set.approval_type}-${set.business_unit_id}-${set.department_id}-${set.budget_min}-${set.budget_max}`"
                        class="align-top transition-colors hover:bg-surface-muted"
                    >
                        <UiTd strong>{{ set.bu_name ?? '-' }}</UiTd>
                        <UiTd>{{ set.dept_name }}</UiTd>

                        <UiTd v-if="usesBudgetRange" nowrap>
                            <UiBadge tone="warning">{{ set.budget_label ?? '—' }}</UiBadge>
                        </UiTd>

                        <UiTd>
                            <!-- Ringkas: L1..L3 lalu "+N"; daftar lengkapnya di tooltip. -->
                            <UiTooltip :label="t.admin.committee.committeePerLayerTooltip">
                                <span class="inline-flex items-center gap-1">
                                    <UiBadge
                                        v-for="layer in set.layers.slice(0, 3)"
                                        :key="layer.layer"
                                        :tone="layer.reserved ? 'warning' : 'neutral'"
                                        :title="layer.name ?? undefined"
                                    >
                                        L{{ layer.layer }}
                                    </UiBadge>

                                    <span v-if="set.layers.length > 3" class="text-xs text-ink-subtle">
                                        +{{ set.layers.length - 3 }}
                                    </span>

                                    <span v-if="!set.layers.length" class="text-xs text-ink-subtle">—</span>
                                </span>

                                <template #content>
                                    <p class="mb-1 font-semibold text-ink-subtle">
                                        {{ t.admin.committee.committeePerLayer }}
                                    </p>

                                    <div
                                        v-for="layer in set.layers"
                                        :key="layer.layer"
                                        class="flex items-center gap-2 whitespace-nowrap py-0.5"
                                    >
                                        <span
                                            class="inline-flex min-w-7 justify-center rounded px-1 py-px font-semibold"
                                            :class="layer.reserved ? 'bg-warning-soft text-warning-ink' : 'bg-surface-muted text-ink-subtle'"
                                        >
                                            L{{ layer.layer }}
                                        </span>
                                        <span class="text-ink-muted">{{ layer.name || '—' }}</span>
                                    </div>

                                    <p v-if="!set.layers.length" class="text-ink-subtle">{{ t.common.none }}</p>
                                </template>
                            </UiTooltip>
                        </UiTd>

                        <UiTd align="right">
                            <div class="flex items-center justify-end gap-2">
                                <UiButton size="sm" variant="secondary" :href="set.edit_url">
                                    {{ t.common.edit }}
                                </UiButton>
                                <UiButton size="sm" variant="danger" @click="remove(set)">
                                    {{ t.common.delete }}
                                </UiButton>
                            </div>
                        </UiTd>
                    </tr>
                </UiTable>

                <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />
            </div>
        </UiCard>
    </div>
</template>
