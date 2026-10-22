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
    UiMultiSelect,
    UiTable,
    UiTd,
    UiTextarea,
    UiTh,
} from '@/components/ui';
import CollapsibleSection from '../CollapsibleSection.vue';
import AttachmentCell from '../AttachmentCell.vue';
import PendingBadge from '../PendingBadge.vue';
import AttachmentEditor from '../AttachmentEditor.vue';
import { useClientTable } from '@/composables/useClientTable';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { reportFields } from '@/composables/useReportContext';
import { formatDate } from '@/lib/datetime';
import type { SelectOption } from '@/types/models';
import type { ImplementationPlanRow } from '../types';

const props = defineProps<{
    plans: ImplementationPlanRow[];
    /** Actual tampil begitu proposal disetujui — lihat ProjectController::show. */
    showActual: boolean;
    canEdit: boolean;
    canEditRow: boolean;
    canDeleteRow: boolean;
    /** Field asal proposal dikunci bagi anggota tim non-Leader. */
    proposalReadOnly: boolean;
    teamMembers: SelectOption[];
    storeUrl: string;
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

/** Sort & halaman di klien — lihat catatan pada TeamSection. */
const {
    rows: pagePlans,
    sort,
    dir,
    perPage,
    meta,
    sortBy,
    setPage,
} = useClientTable({
    rows: () => props.plans,
    columns: {
        activity: (plan) => plan.activity,
        // Rentang tanggal diurutkan dari tanggal mulainya.
        planning: (plan) => plan.planning_start,
        actual: (plan) => plan.actual_start,
        pic: (plan) => plan.pic_names,
        remarks: (plan) => plan.remarks,
        // Sama persis dengan yang tampil: di fase proposal semua baris masih
        // "Not Started", jadi kolomnya memang tidak membedakan apa pun.
        status: (plan) => (props.showActual ? plan.status_label : t.value.projects.implementation.notStarted),
    },
});

/** dd/mm/yy, sependek versi Blade agar tabel tidak melar. */
const shortDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    const d = new Date(value);

    return Number.isNaN(d.getTime())
        ? '-'
        : `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getFullYear()).slice(-2)}`;
};

const pendingDate = (value: unknown, fallback: string | null): string =>
    shortDate((value as string | null) ?? fallback);

/* ---- Edit satu activity ------------------------------------------------ */

interface EditValues {
    activity: string;
    planning_start: string;
    planning_end: string;
    pic_user_ids: number[];
    actual_start: string;
    actual_end: string;
    remarks: string;
    attachments: File[];
    _version: string | null;
}

const editing = ref<ImplementationPlanRow | null>(null);

const editForm = useForm<EditValues>({
    activity: '',
    planning_start: '',
    planning_end: '',
    pic_user_ids: [],
    actual_start: '',
    actual_end: '',
    remarks: '',
    attachments: [],
    _version: null,
});

const openEdit = (plan: ImplementationPlanRow): void => {
    editing.value = plan;
    editForm.defaults({
        activity: plan.activity ?? '',
        planning_start: plan.planning_start ?? '',
        planning_end: plan.planning_end ?? '',
        pic_user_ids: [...plan.pic_user_ids],
        actual_start: plan.actual_start ?? '',
        actual_end: plan.actual_end ?? '',
        remarks: plan.remarks ?? '',
        attachments: [],
        _version: plan.version,
    });
    editForm.reset();
    editForm.clearErrors();
};

/** Lama hari aktual, dihitung inklusif seperti di versi Blade. */
const actualDays = computed<number | null>(() => {
    const { actual_start: start, actual_end: end } = editForm;

    if (!start || !end) {
        return null;
    }

    const a = new Date(start);
    const b = new Date(end);

    return b < a ? null : Math.floor((b.getTime() - a.getTime()) / 86_400_000) + 1;
});

const saveEdit = (): void => {
    const plan = editing.value;

    if (!plan) {
        return;
    }

    // _method=PUT karena ada berkas: pengiriman jadi multipart.
    editForm
        .transform((data) => ({ ...data, ...reportFields(), _method: 'put' }))
        .post(plan.urls.update, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
        });
};

const remove = async (plan: ImplementationPlanRow): Promise<void> => {
    const ok = await confirm({
        title: t.value.projects.implementation.deleteTitle,
        message: tr(t.value.projects.implementation.deleteMessage, { name: plan.activity ?? '' }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(plan.urls.destroy, { data: reportFields(), preserveScroll: true });
    }
};

/* ---- Tambah activity (beberapa baris) ---------------------------------- */

interface NewRow {
    key: number;
    activity: string;
    planning_start: string;
    planning_end: string;
    actual_start: string;
    actual_end: string;
    pic_user_ids: number[];
    remarks: string;
    attachments: File[];
}

let nextKey = 0;

const blankRow = (): NewRow => ({
    key: nextKey++,
    activity: '',
    planning_start: '',
    planning_end: '',
    actual_start: '',
    actual_end: '',
    pic_user_ids: [],
    remarks: '',
    attachments: [],
});

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
        {
            ...reportFields(),
            rows: rows.value.map(({ key: _key, ...row }) => row),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => (adding.value = false),
            onFinish: () => (submitting.value = false),
        },
    );
};

const columnCount = computed(() => 6 + (props.showActual ? 1 : 0) + (props.canEditRow ? 1 : 0));
</script>

<template>
    <CollapsibleSection
        :title="t.projects.implementation.title"
        storage-key="impl"
        anchor="section-implementation"
    >
        <template #raw>
            <UiTable
                :empty="plans.length === 0"
                :empty-title="t.projects.implementation.emptyTitle"
                :empty-message="t.projects.implementation.emptyMessage"
            >
                <template #head>
                    <UiTh sort-key="activity" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.implementation.activity }}
                    </UiTh>
                    <UiTh sort-key="planning" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.implementation.planning }}
                    </UiTh>
                    <UiTh v-if="showActual" sort-key="actual" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.implementation.actual }}
                    </UiTh>
                    <UiTh sort-key="pic" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.implementation.pic }}
                    </UiTh>
                    <UiTh sort-key="remarks" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.implementation.remarks }}
                    </UiTh>
                    <UiTh sort-key="status" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.fields.status }}
                    </UiTh>
                    <UiTh>{{ t.projects.implementation.attachment }}</UiTh>
                    <UiTh v-if="canEditRow" align="right">{{ t.fields.action }}</UiTh>
                </template>

                <tr v-for="plan in pagePlans" :key="plan.id" class="align-top transition-colors hover:bg-surface-muted">
                    <UiTd strong>{{ plan.activity ?? '-' }}</UiTd>

                    <UiTd nowrap class="text-xs">
                        {{ shortDate(plan.planning_start) }} – {{ shortDate(plan.planning_end) }}
                    </UiTd>

                    <UiTd v-if="showActual" class="text-xs">
                        <div>{{ shortDate(plan.actual_start) }} – {{ shortDate(plan.actual_end) }}</div>

                        <!-- Nilai yang sudah diisi tapi menunggu persetujuan. -->
                        <div
                            v-if="plan.pending && ('actual_start' in plan.pending.fields || 'actual_end' in plan.pending.fields)"
                            class="mt-1 text-warning-ink"
                        >
                            {{ pendingDate(plan.pending.fields.actual_start, plan.actual_start) }}
                            –
                            {{ pendingDate(plan.pending.fields.actual_end, plan.actual_end) }}
                            <PendingBadge :pending="plan.pending" />
                        </div>

                        <div v-if="plan.actual_days !== null" class="text-ink-subtle">
                            {{ tr(t.projects.implementation.days, { count: plan.actual_days }) }}
                        </div>
                    </UiTd>

                    <UiTd class="text-xs">{{ plan.pic_names || '-' }}</UiTd>

                    <UiTd class="text-xs">
                        <div class="max-w-[220px] whitespace-normal break-words">{{ plan.remarks || '-' }}</div>

                        <div
                            v-if="plan.pending && 'remarks' in plan.pending.fields"
                            class="mt-1 max-w-[220px] whitespace-normal break-words text-warning-ink"
                        >
                            {{ (plan.pending.fields.remarks as string) || '-' }}
                            <PendingBadge :pending="plan.pending" />
                        </div>
                    </UiTd>

                    <!-- Di fase proposal status selalu "Not Started": tabel itu
                         snapshot proposal, bukan progres eksekusi. -->
                    <UiTd>
                        <span class="inline-flex rounded-full bg-surface-muted px-2 py-1 text-xs text-ink-muted">
                            {{ showActual ? plan.status_label : t.projects.implementation.notStarted }}
                        </span>
                    </UiTd>

                    <UiTd class="text-xs">
                        <AttachmentCell :attachments="plan.attachments" :caption="plan.activity" />
                    </UiTd>

                    <UiTd v-if="canEditRow" align="right">
                        <div class="flex items-center justify-end gap-3 text-xs">
                            <button type="button" class="font-medium text-brand hover:underline" @click="openEdit(plan)">
                                {{ t.common.edit }}
                            </button>
                            <button
                                v-if="canDeleteRow"
                                type="button"
                                class="font-medium text-danger hover:underline"
                                @click="remove(plan)"
                            >
                                {{ t.common.delete }}
                            </button>
                        </div>
                    </UiTd>
                </tr>
            </UiTable>

            <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />

            <div v-if="canEdit" class="flex justify-end border-t border-line p-4">
                <UiButton size="sm" @click="openAdd">{{ t.projects.implementation.addActivity }}</UiButton>
            </div>
        </template>
    </CollapsibleSection>

    <!-- Dialog: ubah activity. Satu dialog berisi field proposal + field Actual;
         bagi anggota tim non-Leader field proposal dikunci (server pun
         mengabaikannya), sehingga hanya Actual yang tersimpan. -->
    <UiModal
        :model-value="editing !== null"
        :title="t.projects.implementation.editTitle"
        :description="
            proposalReadOnly
                ? t.projects.implementation.readOnlyHint
                : (editing?.activity ?? undefined)
        "
        size="lg"
        @update:model-value="editing = null"
    >
        <div class="space-y-3">
            <UiField :label="t.projects.implementation.activity" :error="editForm.errors.activity">
                <UiInput v-model="editForm.activity" :readonly="proposalReadOnly" />
            </UiField>

            <div class="grid grid-cols-2 gap-2">
                <UiField :label="t.projects.implementation.plannedStart" :error="editForm.errors.planning_start">
                    <UiInput
                        v-model="editForm.planning_start"
                        type="date"
                        :max="editForm.planning_end || undefined"
                        :readonly="proposalReadOnly"
                    />
                </UiField>

                <UiField :label="t.projects.implementation.plannedEnd" :error="editForm.errors.planning_end">
                    <UiInput
                        v-model="editForm.planning_end"
                        type="date"
                        :min="editForm.planning_start || undefined"
                        :readonly="proposalReadOnly"
                    />
                </UiField>
            </div>

            <UiField :label="t.projects.implementation.pic" :error="editForm.errors.pic_user_ids">
                <UiMultiSelect
                    v-model="editForm.pic_user_ids"
                    :options="teamMembers"
                    :disabled="proposalReadOnly"
                    :placeholder="t.projects.implementation.picPlaceholder"
                />
            </UiField>

            <div v-if="showActual" class="border-t border-line pt-3">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-subtle">
                    {{ t.projects.implementation.actual }}
                </p>

                <div class="grid grid-cols-2 gap-2">
                    <UiField :label="t.projects.implementation.actualTimelineStart" :error="editForm.errors.actual_start">
                        <UiInput v-model="editForm.actual_start" type="date" :max="editForm.actual_end || undefined" />
                    </UiField>

                    <UiField :label="t.projects.implementation.actualTimelineEnd" :error="editForm.errors.actual_end">
                        <UiInput v-model="editForm.actual_end" type="date" :min="editForm.actual_start || undefined" />
                    </UiField>
                </div>

                <p class="mt-2 text-xs text-ink-subtle">{{ t.projects.implementation.actualTimelineDays }}</p>
                <div class="rounded-control border border-line bg-surface-muted px-3 py-2 text-sm text-ink-muted">
                    {{
                        actualDays !== null
                            ? tr(t.projects.implementation.days, { count: actualDays })
                            : '-'
                    }}
                </div>
            </div>

            <UiField :label="t.projects.implementation.remarks" :error="editForm.errors.remarks">
                <UiTextarea
                    v-model="editForm.remarks"
                    :rows="2"
                    :maxlength="2000"
                    :placeholder="t.projects.implementation.remarksPlaceholder"
                />
            </UiField>

            <div>
                <p class="mb-1 block text-xs font-medium text-ink-label">
                    {{ t.projects.implementation.attachment }}
                </p>
                <AttachmentEditor v-if="editing" :attachments="editing.attachments" class="mb-2" />
                <UiFileUpload v-model="editForm.attachments" />
            </div>
        </div>

        <template #footer>
            <UiButton variant="secondary" @click="editing = null">{{ t.common.cancel }}</UiButton>
            <UiButton :loading="editForm.processing" @click="saveEdit">{{ t.common.save }}</UiButton>
        </template>
    </UiModal>

    <!-- Dialog: tambah activity, beberapa baris sekaligus -->
    <UiModal v-model="adding" :title="t.projects.implementation.addTitle" size="lg">
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

                <div class="grid grid-cols-2 gap-2">
                    <div class="col-span-2">
                        <UiField :label="t.projects.implementation.activity">
                            <UiInput
                                v-model="row.activity"
                                :placeholder="t.projects.implementation.activityPlaceholder"
                            />
                        </UiField>
                    </div>

                    <UiField :label="t.projects.implementation.plannedStart">
                        <UiInput v-model="row.planning_start" type="date" :max="row.planning_end || undefined" />
                    </UiField>

                    <UiField :label="t.projects.implementation.plannedEnd">
                        <UiInput v-model="row.planning_end" type="date" :min="row.planning_start || undefined" />
                    </UiField>

                    <template v-if="showActual">
                        <UiField :label="t.projects.implementation.actualStart">
                            <UiInput v-model="row.actual_start" type="date" :max="row.actual_end || undefined" />
                        </UiField>

                        <UiField :label="t.projects.implementation.actualEnd">
                            <UiInput v-model="row.actual_end" type="date" :min="row.actual_start || undefined" />
                        </UiField>
                    </template>

                    <div class="col-span-2">
                        <UiField :label="t.projects.implementation.pic">
                            <UiMultiSelect
                                v-model="row.pic_user_ids"
                                :options="teamMembers"
                                :placeholder="t.projects.implementation.picPlaceholder"
                            />
                        </UiField>
                    </div>

                    <div class="col-span-2">
                        <UiField :label="t.projects.implementation.remarks">
                            <UiTextarea
                                v-model="row.remarks"
                                :rows="2"
                                :maxlength="2000"
                                :placeholder="t.projects.implementation.remarksPlaceholder"
                            />
                        </UiField>
                    </div>

                    <div class="col-span-2">
                        <p class="mb-1 block text-xs font-medium text-ink-label">
                            {{ t.projects.implementation.attachment }}
                        </p>
                        <UiFileUpload v-model="row.attachments" />
                    </div>
                </div>
            </div>
        </div>

        <UiButton size="sm" variant="secondary" class="mt-3" @click="rows.push(blankRow())">
            {{ t.projects.team.addRow }}
        </UiButton>

        <template #footer>
            <UiButton variant="secondary" @click="adding = false">{{ t.common.cancel }}</UiButton>
            <UiButton :loading="submitting" @click="submitAdd">
                {{ t.projects.implementation.addTitle }}
            </UiButton>
        </template>
    </UiModal>
</template>
