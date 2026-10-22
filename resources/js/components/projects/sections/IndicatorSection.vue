<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    UiBadge,
    UiButton,
    UiField,
    UiInput,
    UiListFooter,
    UiModal,
    UiSelect,
    UiTable,
    UiTd,
    UiTextarea,
    UiTh,
} from '@/components/ui';
import CollapsibleSection from '../CollapsibleSection.vue';
import { useClientTable } from '@/composables/useClientTable';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { reportFields } from '@/composables/useReportContext';
import type { SelectOption } from '@/types/models';
import type { IndicatorRow, UomGroup } from '../types';

const props = defineProps<{
    indicators: IndicatorRow[];
    showActual: boolean;
    canEdit: boolean;
    canEditRow: boolean;
    canDeleteRow: boolean;
    proposalReadOnly: boolean;
    uomGroups: UomGroup[];
    types: string[];
    storeUrl: string;
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

/** Sort & halaman di klien — lihat catatan pada TeamSection. */
const {
    rows: pageIndicators,
    sort,
    dir,
    perPage,
    meta,
    sortBy,
    setPage,
} = useClientTable({
    rows: () => props.indicators,
    columns: {
        indicator: (row) => row.indicator,
        description: (row) => row.description,
        baseline: (row) => row.baseline,
        achievement_value: (row) => row.achievement_value,
        achievement: (row) => row.achievement,
        uom: (row) => row.uom,
        weightage: (row) => row.weightage,
        type: (row) => row.type,
        improvement: (row) => row.improvement,
    },
});

const typeOptions = computed<SelectOption[]>(() =>
    props.types.map((type) => ({ value: type, label: type })),
);

/** Total bobot harus tepat 100% sebelum proposal bisa disubmit (T-43/214). */
const usedWeight = computed(() =>
    props.indicators.reduce((total, row) => total + (Number(row.weightage) || 0), 0),
);

const weightBalanced = computed(() => Math.abs(usedWeight.value - 100) < 0.01);

/* ---- Edit ------------------------------------------------------------- */

interface EditValues {
    indicator: string;
    description: string;
    baseline: string;
    achievement_value: string;
    achievement: string;
    uom: string | null;
    weightage: string;
    type: string | null;
    _version: string | null;
}

const editing = ref<IndicatorRow | null>(null);

const editForm = useForm<EditValues>({
    indicator: '',
    description: '',
    baseline: '',
    achievement_value: '',
    achievement: '',
    uom: null,
    weightage: '',
    type: null,
    _version: null,
});

const openEdit = (row: IndicatorRow): void => {
    editing.value = row;
    editForm.defaults({
        indicator: row.indicator ?? '',
        description: row.description ?? '',
        baseline: row.baseline === null ? '' : String(row.baseline),
        achievement_value: row.achievement_value === null ? '' : String(row.achievement_value),
        achievement: row.achievement === null ? '' : String(row.achievement),
        uom: row.uom,
        weightage: row.weightage === null ? '' : String(row.weightage),
        type: row.type,
        _version: row.version,
    });
    editForm.reset();
    editForm.clearErrors();
};

const saveEdit = (): void => {
    const row = editing.value;

    if (!row) {
        return;
    }

    editForm
        .transform((data) => ({ ...data, ...reportFields() }))
        .put(row.urls.update, { preserveScroll: true, onSuccess: () => (editing.value = null) });
};

const remove = async (row: IndicatorRow): Promise<void> => {
    const ok = await confirm({
        title: t.value.projects.indicator.deleteTitle,
        message: tr(t.value.projects.indicator.deleteMessage, { name: row.indicator ?? '' }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(row.urls.destroy, { data: reportFields(), preserveScroll: true });
    }
};

/* ---- Tambah ------------------------------------------------------------ */

interface NewRow {
    key: number;
    indicator: string;
    description: string;
    baseline: string;
    achievement_value: string;
    achievement: string;
    uom: string | null;
    weightage: string;
    type: string | null;
}

let nextKey = 0;

const blankRow = (): NewRow => ({
    key: nextKey++,
    indicator: '',
    description: '',
    baseline: '',
    achievement_value: '',
    achievement: '',
    uom: null,
    weightage: '',
    type: null,
});

const adding = ref(false);
const rows = ref<NewRow[]>([]);
const submitting = ref(false);

const addingWeight = computed(() =>
    rows.value.reduce((total, row) => total + (Number(row.weightage) || 0), 0),
);

const projectedTotal = computed(() => Math.round((usedWeight.value + addingWeight.value) * 100) / 100);
const remainingWeight = computed(() => Math.round((100 - usedWeight.value) * 100) / 100);
const overWeight = computed(() => projectedTotal.value > 100);

const openAdd = (): void => {
    rows.value = [blankRow()];
    adding.value = true;
};

const submitAdd = (): void => {
    submitting.value = true;

    router.post(
        props.storeUrl,
        { ...reportFields(), rows: rows.value.map(({ key: _key, ...row }) => row) },
        {
            preserveScroll: true,
            onSuccess: () => (adding.value = false),
            onFinish: () => (submitting.value = false),
        },
    );
};
</script>

<template>
    <CollapsibleSection :title="t.projects.indicator.title" storage-key="ind" anchor="section-indicators">
        <template #titleExtra>
            <span
                class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                :class="
                    weightBalanced
                        ? 'bg-success-soft text-success-ink ring-success/20'
                        : 'bg-warning-soft text-warning-ink ring-warning/20'
                "
            >
                {{ tr(t.projects.indicator.totalWeight, { value: Math.round(usedWeight * 100) / 100 }) }}
            </span>
        </template>

        <template #raw>
            <UiTable
                :empty="indicators.length === 0"
                :empty-title="t.projects.indicator.emptyTitle"
                :empty-message="t.projects.indicator.emptyMessage"
            >
                <template #head>
                    <UiTh sort-key="indicator" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.indicator }}
                    </UiTh>
                    <UiTh sort-key="description" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.description }}
                    </UiTh>
                    <UiTh sort-key="baseline" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.baseline }}
                    </UiTh>
                    <UiTh sort-key="achievement_value" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.achievementValue }}
                    </UiTh>
                    <UiTh v-if="showActual" sort-key="achievement" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.achievement }}
                    </UiTh>
                    <UiTh sort-key="uom" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.uom }}
                    </UiTh>
                    <UiTh sort-key="weightage" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.weightage }}
                    </UiTh>
                    <UiTh sort-key="type" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.type }}
                    </UiTh>
                    <UiTh v-if="showActual" sort-key="improvement" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.indicator.improve }}
                    </UiTh>
                    <UiTh v-if="canEditRow" align="right">{{ t.fields.action }}</UiTh>
                </template>

                <tr v-for="row in pageIndicators" :key="row.id" class="align-top transition-colors hover:bg-surface-muted">
                    <UiTd strong>{{ row.indicator ?? '-' }}</UiTd>

                    <UiTd>
                        <div class="max-w-xs whitespace-normal break-words text-xs text-ink-subtle">
                            {{ row.description || '-' }}
                        </div>
                    </UiTd>

                    <UiTd>{{ row.baseline ?? '-' }}</UiTd>
                    <UiTd>{{ row.achievement_value ?? '-' }}</UiTd>
                    <UiTd v-if="showActual" class="text-xs">{{ row.achievement ?? '-' }}</UiTd>
                    <UiTd>{{ row.uom ?? '-' }}</UiTd>
                    <UiTd>{{ row.weightage ?? '-' }}</UiTd>
                    <UiTd class="text-xs">{{ row.type ?? '-' }}</UiTd>
                    <UiTd v-if="showActual">{{ row.improvement !== null ? `${row.improvement}%` : '-' }}</UiTd>

                    <UiTd v-if="canEditRow" align="right">
                        <div class="flex items-center justify-end gap-3 text-xs">
                            <button type="button" class="font-medium text-brand hover:underline" @click="openEdit(row)">
                                {{ t.common.edit }}
                            </button>
                            <button
                                v-if="canDeleteRow"
                                type="button"
                                class="font-medium text-danger hover:underline"
                                @click="remove(row)"
                            >
                                {{ t.common.delete }}
                            </button>
                        </div>
                    </UiTd>
                </tr>
            </UiTable>

            <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />

            <div v-if="canEdit" class="flex items-center justify-between gap-3 border-t border-line p-4">
                <p class="text-xs text-ink-subtle">{{ t.projects.indicator.footerHint }}</p>
                <UiButton size="sm" class="shrink-0" @click="openAdd">
                    {{ t.projects.indicator.addIndicator }}
                </UiButton>
            </div>
        </template>
    </CollapsibleSection>

    <!-- Dialog: ubah indikator -->
    <UiModal
        :model-value="editing !== null"
        :title="t.projects.indicator.editTitle"
        :description="proposalReadOnly ? t.projects.indicator.readOnlyHint : undefined"
        size="lg"
        @update:model-value="editing = null"
    >
        <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
                <UiField :label="t.projects.indicator.indicatorName" :error="editForm.errors.indicator">
                    <UiInput v-model="editForm.indicator" :readonly="proposalReadOnly" />
                </UiField>
            </div>

            <div class="col-span-2">
                <UiField :label="t.projects.indicator.indicatorDescription" :error="editForm.errors.description">
                    <UiTextarea v-model="editForm.description" :rows="2" :disabled="proposalReadOnly" />
                </UiField>
            </div>

            <UiField :label="t.projects.indicator.baseline" :error="editForm.errors.baseline">
                <UiInput v-model="editForm.baseline" type="number" step="any" :readonly="proposalReadOnly" />
            </UiField>

            <UiField
                :label="t.projects.indicator.achievementValue"
                :hint="t.projects.indicator.targetHint"
                :error="editForm.errors.achievement_value"
            >
                <UiInput v-model="editForm.achievement_value" type="number" step="any" :readonly="proposalReadOnly" />
            </UiField>

            <div v-if="showActual" class="col-span-2">
                <UiField
                    :label="t.projects.indicator.achievement"
                    :hint="t.projects.indicator.actualHint"
                    :error="editForm.errors.achievement"
                >
                    <UiInput v-model="editForm.achievement" type="number" step="any" />
                </UiField>
            </div>

            <UiField :label="t.projects.indicator.uom" :error="editForm.errors.uom">
                <UiSelect
                    v-model="editForm.uom"
                    :groups="uomGroups"
                    :disabled="proposalReadOnly"
                    :placeholder="t.projects.indicator.selectUom"
                />
            </UiField>

            <UiField :label="t.projects.indicator.weightage" :error="editForm.errors.weightage">
                <UiInput v-model="editForm.weightage" type="number" step="any" :readonly="proposalReadOnly" />
            </UiField>

            <div class="col-span-2">
                <UiField :label="t.projects.indicator.type" required :error="editForm.errors.type">
                    <UiSelect
                        v-model="editForm.type"
                        :options="typeOptions"
                        :disabled="proposalReadOnly"
                        :placeholder="t.projects.indicator.selectType"
                    />
                </UiField>
            </div>
        </div>

        <template #footer>
            <UiButton variant="secondary" @click="editing = null">{{ t.common.cancel }}</UiButton>
            <UiButton :loading="editForm.processing" @click="saveEdit">{{ t.common.save }}</UiButton>
        </template>
    </UiModal>

    <!-- Dialog: tambah indikator -->
    <UiModal v-model="adding" :title="t.projects.indicator.addTitle" size="lg">
        <div class="space-y-3">
            <div v-for="(row, index) in rows" :key="row.key" class="relative rounded-card border border-line p-3">
                <button
                    v-if="rows.length > 1"
                    type="button"
                    class="absolute right-2 top-2 rounded p-1 text-danger transition-colors hover:bg-danger-soft"
                    :aria-label="t.projects.team.removeRow"
                    @click="rows.splice(index, 1)"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <UiField :label="t.projects.indicator.indicatorName">
                            <UiInput v-model="row.indicator" :placeholder="t.projects.indicator.namePlaceholder" />
                        </UiField>
                    </div>

                    <div class="col-span-2">
                        <UiField :label="t.projects.indicator.indicatorDescription">
                            <UiTextarea
                                v-model="row.description"
                                :rows="2"
                                :placeholder="t.projects.indicator.descriptionPlaceholder"
                            />
                        </UiField>
                    </div>

                    <UiField :label="t.projects.indicator.baseline">
                        <UiInput v-model="row.baseline" type="number" step="any" />
                    </UiField>

                    <UiField
                        :label="t.projects.indicator.achievementValue"
                        :hint="t.projects.indicator.targetHint"
                    >
                        <UiInput v-model="row.achievement_value" type="number" step="any" />
                    </UiField>

                    <div v-if="showActual" class="col-span-2">
                        <UiField
                            :label="t.projects.indicator.achievement"
                            :hint="t.projects.indicator.actualHint"
                        >
                            <UiInput v-model="row.achievement" type="number" step="any" />
                        </UiField>
                    </div>

                    <UiField :label="t.projects.indicator.uom">
                        <UiSelect
                            v-model="row.uom"
                            :groups="uomGroups"
                            :placeholder="t.projects.indicator.selectUom"
                        />
                    </UiField>

                    <UiField :label="t.projects.indicator.weightage">
                        <UiInput v-model="row.weightage" type="number" step="any" />
                    </UiField>

                    <div class="col-span-2">
                        <UiField :label="t.projects.indicator.type" required>
                            <UiSelect
                                v-model="row.type"
                                :options="typeOptions"
                                :placeholder="t.projects.indicator.selectType"
                            />
                        </UiField>
                    </div>
                </div>
            </div>
        </div>

        <UiButton size="sm" variant="secondary" class="mt-3" @click="rows.push(blankRow())">
            {{ t.projects.indicator.addRow }}
        </UiButton>

        <!-- Ringkasan bobot: sisa kuota, dan peringatan bila melewati 100%. -->
        <div
            class="mt-3 rounded-control px-3 py-2 text-sm ring-1"
            :class="overWeight ? 'bg-danger-soft text-danger-ink ring-danger/25' : 'bg-surface-muted text-ink-muted ring-line'"
        >
            {{ t.projects.indicator.totalWeightage }}
            <span class="font-semibold">{{ projectedTotal }}%</span>
            <span class="text-xs">
                {{
                    tr(t.projects.indicator.weightBreakdown, {
                        used: Math.round(usedWeight * 100) / 100,
                        left: remainingWeight,
                    })
                }}
            </span>
            <span v-if="overWeight" class="mt-0.5 block text-xs font-semibold">
                {{ t.projects.indicator.overWeight }}
            </span>
        </div>

        <template #footer>
            <UiButton variant="secondary" @click="adding = false">{{ t.common.cancel }}</UiButton>
            <UiButton :loading="submitting" :disabled="overWeight" @click="submitAdd">
                {{ t.projects.indicator.addTitle }}
            </UiButton>
        </template>
    </UiModal>
</template>
