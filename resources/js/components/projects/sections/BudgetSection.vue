<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    UiButton,
    UiField,
    UiFileUpload,
    UiInput,
    UiListFooter,
    UiModal,
    UiSelect,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import CollapsibleSection from '../CollapsibleSection.vue';
import AttachmentCell from '../AttachmentCell.vue';
import AttachmentEditor from '../AttachmentEditor.vue';
import { useClientTable } from '@/composables/useClientTable';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { reportFields } from '@/composables/useReportContext';
import { currency } from '@/lib/format';
import type { BudgetRow, UomGroup } from '../types';

const props = defineProps<{
    budgets: BudgetRow[];
    showActual: boolean;
    canEdit: boolean;
    canEditRow: boolean;
    canDeleteRow: boolean;
    proposalReadOnly: boolean;
    uomGroups: UomGroup[];
    /** Sebelum submit "No budget yet"; sesudahnya keadaannya sudah final. */
    emptyMessage: string;
    storeUrl: string;
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

// Total dihitung dari SELURUH baris, bukan halaman yang sedang tampil: angka
// inilah yang dicocokkan dengan rentang budget committee.
const totalBudget = computed(() => props.budgets.reduce((sum, row) => sum + (row.planned_total || 0), 0));
const totalCost = computed(() => props.budgets.reduce((sum, row) => sum + (row.actual_cost || 0), 0));

/** Sort & halaman di klien — lihat catatan pada TeamSection. */
const {
    rows: pageBudgets,
    sort,
    dir,
    perPage,
    meta,
    offset,
    sortBy,
    setPage,
} = useClientTable({
    rows: () => props.budgets,
    columns: {
        item: (row) => row.item,
        qty: (row) => row.qty,
        uom: (row) => row.uom,
        unit_price: (row) => row.unit_price,
        planned_total: (row) => row.planned_total,
        actual_qty: (row) => row.actual_qty,
        actual_price: (row) => row.actual_price,
        actual_cost: (row) => row.actual_cost,
    },
});

/* ---- Edit ------------------------------------------------------------- */

interface EditValues {
    item: string;
    qty: string;
    uom: string | null;
    unit_price: string;
    actual_qty: string;
    actual_price: string;
    attachments: File[];
    _version: string | null;
}

const editing = ref<BudgetRow | null>(null);

const editForm = useForm<EditValues>({
    item: '',
    qty: '',
    uom: null,
    unit_price: '',
    actual_qty: '',
    actual_price: '',
    attachments: [],
    _version: null,
});

const openEdit = (row: BudgetRow): void => {
    editing.value = row;
    editForm.defaults({
        item: row.item ?? '',
        qty: row.qty === null ? '' : String(row.qty),
        uom: row.uom,
        unit_price: row.unit_price === null ? '' : String(row.unit_price),
        actual_qty: row.actual_qty === null ? '' : String(row.actual_qty),
        actual_price: row.actual_price === null ? '' : String(row.actual_price),
        attachments: [],
        _version: row.version,
    });
    editForm.reset();
    editForm.clearErrors();
};

const editTotal = computed(() => (Number(editForm.qty) || 0) * (Number(editForm.unit_price) || 0));
const editActualCost = computed(
    () => (Number(editForm.actual_qty) || 0) * (Number(editForm.actual_price) || 0),
);

const saveEdit = (): void => {
    const row = editing.value;

    if (!row) {
        return;
    }

    editForm
        .transform((data) => ({ ...data, ...reportFields(), _method: 'put' }))
        .post(row.urls.update, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
        });
};

const remove = async (row: BudgetRow): Promise<void> => {
    const ok = await confirm({
        title: t.value.projects.budget.deleteTitle,
        message: tr(t.value.projects.budget.deleteMessage, { name: row.item ?? '' }),
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
    item: string;
    qty: string;
    uom: string | null;
    unit_price: string;
}

let nextKey = 0;

const blankRow = (): NewRow => ({ key: nextKey++, item: '', qty: '', uom: null, unit_price: '' });

const adding = ref(false);
const rows = ref<NewRow[]>([]);
const submitting = ref(false);

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
    <CollapsibleSection :title="t.projects.budget.title" storage-key="budget" anchor="section-budget">
        <template #raw>
            <UiTable
                :empty="budgets.length === 0"
                :empty-title="t.projects.budget.emptyTitle"
                :empty-message="emptyMessage"
            >
                <template #head>
                    <UiTh width="w-12">{{ t.fields.no }}</UiTh>
                    <UiTh sort-key="item" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.budget.itemName }}
                    </UiTh>
                    <UiTh sort-key="qty" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.budget.qty }}
                    </UiTh>
                    <UiTh sort-key="uom" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.budget.uom }}
                    </UiTh>
                    <UiTh sort-key="unit_price" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.budget.price }}
                    </UiTh>
                    <UiTh sort-key="planned_total" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.budget.totalPrice }}
                    </UiTh>
                    <template v-if="showActual">
                        <UiTh sort-key="actual_qty" :sort="sort" :dir="dir" @sort="sortBy">
                            {{ t.projects.budget.actualQty }}
                        </UiTh>
                        <UiTh sort-key="actual_price" :sort="sort" :dir="dir" @sort="sortBy">
                            {{ t.projects.budget.actualPrice }}
                        </UiTh>
                        <UiTh sort-key="actual_cost" :sort="sort" :dir="dir" @sort="sortBy">
                            {{ t.projects.budget.actualCost }}
                        </UiTh>
                    </template>
                    <UiTh>{{ t.projects.budget.attachment }}</UiTh>
                    <UiTh v-if="canEditRow" align="right">{{ t.fields.action }}</UiTh>
                </template>

                <tr v-for="(row, index) in pageBudgets" :key="row.id" class="align-top transition-colors hover:bg-surface-muted">
                    <!-- Nomor mengikuti urutan yang sedang tampil, bukan id. -->
                    <UiTd muted>{{ offset + index + 1 }}</UiTd>
                    <UiTd strong>{{ row.item ?? '-' }}</UiTd>
                    <UiTd>{{ row.qty ?? '-' }}</UiTd>
                    <UiTd>{{ row.uom ?? '-' }}</UiTd>
                    <UiTd nowrap>{{ currency(row.unit_price) }}</UiTd>
                    <UiTd nowrap>{{ currency(row.planned_total) }}</UiTd>

                    <template v-if="showActual">
                        <UiTd class="text-xs">{{ row.actual_qty ?? '-' }}</UiTd>
                        <UiTd nowrap class="text-xs">{{ currency(row.actual_price) }}</UiTd>
                        <UiTd nowrap strong class="text-xs">{{ currency(row.actual_cost) }}</UiTd>
                    </template>

                    <UiTd class="text-xs">
                        <AttachmentCell :attachments="row.attachments" :caption="row.item" />
                    </UiTd>

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

                <template v-if="budgets.length" #foot>
                    <tr>
                        <UiTd align="right" strong>{{ t.projects.budget.totalBudget }}</UiTd>
                        <UiTd colspan="4" />
                        <UiTd nowrap strong>{{ currency(totalBudget) }}</UiTd>

                        <template v-if="showActual">
                            <UiTd align="right" strong colspan="2">{{ t.projects.budget.totalCost }}</UiTd>
                            <UiTd nowrap strong>{{ currency(totalCost) }}</UiTd>
                        </template>

                        <UiTd />
                        <UiTd v-if="canEditRow" />
                    </tr>
                </template>
            </UiTable>

            <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />

            <div v-if="canEdit" class="flex justify-end border-t border-line p-4">
                <UiButton size="sm" @click="openAdd">{{ t.projects.budget.addBudget }}</UiButton>
            </div>
        </template>
    </CollapsibleSection>

    <!-- Dialog: ubah budget -->
    <UiModal
        :model-value="editing !== null"
        :title="t.projects.budget.editTitle"
        size="lg"
        @update:model-value="editing = null"
    >
        <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
                <UiField :label="t.projects.budget.itemName" :error="editForm.errors.item">
                    <UiInput v-model="editForm.item" :readonly="proposalReadOnly" />
                </UiField>
            </div>

            <UiField :label="t.projects.budget.qty" :error="editForm.errors.qty">
                <UiInput v-model="editForm.qty" type="number" step="any" min="0" :readonly="proposalReadOnly" />
            </UiField>

            <UiField :label="t.projects.budget.uom" :error="editForm.errors.uom">
                <UiSelect
                    v-model="editForm.uom"
                    :groups="uomGroups"
                    :disabled="proposalReadOnly"
                    :placeholder="t.projects.budget.selectUom"
                />
            </UiField>

            <UiField :label="t.projects.budget.priceIdr" :error="editForm.errors.unit_price">
                <UiInput v-model="editForm.unit_price" type="number" step="any" min="0" :readonly="proposalReadOnly" />
            </UiField>

            <div>
                <p class="mb-1 block text-xs font-medium text-ink-label">{{ t.projects.budget.totalPrice }}</p>
                <div class="rounded-control border border-line bg-surface-muted px-3 py-2 text-sm text-ink-muted">
                    {{ currency(editTotal) }}
                </div>
            </div>

            <div v-if="showActual" class="col-span-2 border-t border-line pt-3">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-subtle">
                    {{ t.projects.budget.actual }}
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <UiField :label="t.projects.budget.actualQty" :error="editForm.errors.actual_qty">
                        <UiInput v-model="editForm.actual_qty" type="number" step="any" />
                    </UiField>

                    <UiField :label="t.projects.budget.actualPriceIdr" :error="editForm.errors.actual_price">
                        <UiInput v-model="editForm.actual_price" type="number" step="any" />
                    </UiField>
                </div>

                <p class="mt-2 text-xs text-ink-subtle">{{ t.projects.budget.actualCost }}</p>
                <div class="rounded-control border border-line bg-surface-muted px-3 py-2 text-sm text-ink-muted">
                    {{ currency(editActualCost) }}
                </div>
            </div>

            <div class="col-span-2">
                <p class="mb-1 block text-xs font-medium text-ink-label">{{ t.projects.budget.attachment }}</p>
                <AttachmentEditor v-if="editing" :attachments="editing.attachments" class="mb-2" />
                <UiFileUpload v-model="editForm.attachments" />
            </div>
        </div>

        <template #footer>
            <UiButton variant="secondary" @click="editing = null">{{ t.common.cancel }}</UiButton>
            <UiButton :loading="editForm.processing" @click="saveEdit">{{ t.common.save }}</UiButton>
        </template>
    </UiModal>

    <!-- Dialog: tambah budget -->
    <UiModal v-model="adding" :title="t.projects.budget.addTitle" size="lg">
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
                        <UiField :label="t.projects.budget.itemName">
                            <UiInput
                                v-model="row.item"
                                :maxlength="500"
                                :placeholder="t.projects.budget.itemPlaceholder"
                            />
                        </UiField>
                    </div>

                    <UiField :label="t.projects.budget.qty">
                        <UiInput v-model="row.qty" type="number" step="any" min="0" />
                    </UiField>

                    <UiField :label="t.projects.budget.uom">
                        <UiSelect
                            v-model="row.uom"
                            :groups="uomGroups"
                            :placeholder="t.projects.budget.selectUom"
                        />
                    </UiField>

                    <UiField :label="t.projects.budget.priceIdr">
                        <UiInput v-model="row.unit_price" type="number" step="any" min="0" />
                    </UiField>

                    <div>
                        <p class="mb-1 block text-xs font-medium text-ink-label">{{ t.projects.budget.totalPrice }}</p>
                        <div class="rounded-control border border-line bg-surface-muted px-3 py-2 text-sm text-ink-muted">
                            {{ currency((Number(row.qty) || 0) * (Number(row.unit_price) || 0)) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <UiButton size="sm" variant="secondary" class="mt-3" @click="rows.push(blankRow())">
            {{ t.projects.budget.addRow }}
        </UiButton>

        <template #footer>
            <UiButton variant="secondary" @click="adding = false">{{ t.common.cancel }}</UiButton>
            <UiButton :loading="submitting" @click="submitAdd">{{ t.projects.budget.addTitle }}</UiButton>
        </template>
    </UiModal>
</template>
