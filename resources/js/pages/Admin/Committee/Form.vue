<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    UiButton,
    UiCard,
    UiCombobox,
    UiField,
    UiInput,
    UiModal,
    UiPageHeader,
    UiSelect,
} from '@/components/ui';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { cleanQuery } from '@/lib/query';
import type { SelectOption } from '@/types/models';
import type { SharedProps } from '@/types/inertia';

interface Assigned {
    email: string;
    label: string;
}

const props = defineProps<{
    types: SelectOption[];
    businessUnits: SelectOption[];
    unitNames: SelectOption[];
    selection: {
        approval_type: string | null;
        business_unit_id: number | null;
        department: string | null;
        budget_min: number | null;
        budget_max: number | null;
    };
    context: {
        type_label: string | null;
        bu_name: string | null;
        unit_label: string;
        budget_label: string | null;
    };
    sponsorLayer: boolean;
    assignedByLayer: Record<string, Assigned>;
    initialVisible: number;
    /** Kombinasi terpilih sudah punya baris committee — judulnya "Edit". */
    isEdit: boolean;
    usesRange: boolean;
    rangeReady: boolean;
    maxLayers: number;
    urls: {
        form: string;
        store: string;
        import: string;
        template: string;
        index: string;
        employeeSearch: string;
    };
}>();

const page = usePage<SharedProps>();
const { t } = useLocale();
const { approvalType } = useLabels();

const typeOptions = computed(() =>
    props.types.map((option) => ({
        ...option,
        label: approvalType(String(option.value), option.label),
    })),
);

/* ---- Pemilih: tipe + BU + Unit (+ rentang budget) ---------------------- */

/**
 * Mengganti salah satunya memuat ulang halaman: daftar Unit, rentang budget,
 * dan assignment yang ada semuanya ditentukan server dari kombinasi ini.
 */
const selection = reactive({ ...props.selection });

watch(
    () => props.selection,
    (value) => Object.assign(selection, value),
);

const reload = (): void => {
    router.get(props.urls.form, cleanQuery({ ...selection }), { preserveScroll: true });
};

// Ganti Business Unit mengosongkan Unit: daftar Unit berasal dari BU terpilih.
const onBuChange = (value: string | number | null | undefined): void => {
    selection.business_unit_id = (value as number | null) ?? null;
    selection.department = null;
    reload();
};

/** Ribuan dipisah titik saat ditampilkan; server menerima angka polos. */
const formatThousands = (value: number | null): string =>
    value === null ? '' : value.toLocaleString('id-ID');

const budgetMin = ref(formatThousands(props.selection.budget_min));
const budgetMax = ref(formatThousands(props.selection.budget_max));

watch(
    () => props.selection,
    (value) => {
        budgetMin.value = formatThousands(value.budget_min);
        budgetMax.value = formatThousands(value.budget_max);
    },
);

const digitsOnly = (value: string): number | null => {
    const digits = value.replace(/\D/g, '');

    return digits === '' ? null : Number(digits);
};

const commitBudget = (): void => {
    selection.budget_min = digitsOnly(budgetMin.value);
    selection.budget_max = digitsOnly(budgetMax.value);
    reload();
};

/* ---- Form layer -------------------------------------------------------- */

const visible = ref(props.initialVisible);

/** layers[n] = email approver, atau null. Dikirim apa adanya ke store(). */
const form = useForm<{ [key: string]: unknown }>({
    approval_type: props.selection.approval_type,
    business_unit_id: props.selection.business_unit_id,
    department: props.selection.department,
    budget_min: props.selection.budget_min,
    budget_max: props.selection.budget_max,
    layers: {} as Record<number, string | null>,
});

/** Label pilihan yang sudah tersimpan — combobox mencari ke server. */
const labels = reactive<Record<number, string>>({});

const seedLayers = (): void => {
    const layers: Record<number, string | null> = {};

    for (const [layer, assigned] of Object.entries(props.assignedByLayer)) {
        layers[Number(layer)] = assigned.email;
        labels[Number(layer)] = assigned.label;
    }

    form.layers = layers;
};

seedLayers();

watch(() => props.assignedByLayer, seedLayers);

const layerNumbers = computed(() =>
    Array.from({ length: props.maxLayers }, (_, index) => index + 1).filter(
        (layer) => layer <= visible.value,
    ),
);

const layerValue = (layer: number): string | null =>
    (form.layers as Record<number, string | null>)[layer] ?? null;

const setLayer = (layer: number, value: string | number | null | undefined): void => {
    (form.layers as Record<number, string | null>)[layer] = (value as string | null) ?? null;
};

const save = (): void => {
    form.post(props.urls.store, { preserveScroll: true });
};

/* ---- Import Excel ------------------------------------------------------ */

const importOpen = ref(false);
const importBusy = ref(false);
const importError = ref('');
const importFile = ref<HTMLInputElement | null>(null);
const importFilled = ref<string[]>([]);
const importNotes = ref<string[]>([]);

/**
 * Import hanya MENGISI form — tidak menyimpan apa pun. Tombol Save tetap yang
 * menentukan, sehingga berkas yang salah bisa diperbaiki lebih dulu.
 */
const runImport = async (): Promise<void> => {
    importError.value = '';

    const file = importFile.value?.files?.[0];

    if (!file) {
        importError.value = t.value.admin.committee.chooseFileFirst;
        return;
    }

    const body = new FormData();
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    body.append('_token', token);
    body.append('approval_type', String(props.selection.approval_type ?? ''));
    body.append('file', file);

    importBusy.value = true;

    try {
        const response = await fetch(props.urls.import, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });

        const data = await response.json();

        if (!response.ok) {
            // Pesan validasi Laravel datang sebagai {errors: {field: [pesan]}}.
            const firstError = data.errors ? (Object.values(data.errors)[0] as string[])[0] : null;

            importError.value =
                data.message || firstError || t.value.admin.committee.fileUnreadable;
            return;
        }

        const filled: string[] = [];

        for (const row of (data.rows ?? []) as Array<{ layer: number; email: string; label: string }>) {
            setLayer(row.layer, row.email);
            labels[row.layer] = row.label;

            // Layer di atas 3 tersembunyi — buka sendiri supaya hasil import
            // tidak tampak hilang.
            if (row.layer > visible.value) {
                visible.value = row.layer;
            }

            filled.push(`${tr(t.value.admin.committee.layerLabel, { layer: row.layer })} — ${row.label}`);
        }

        importFilled.value = filled;
        importNotes.value = data.notes ?? [];
        importOpen.value = false;

        if (importFile.value) {
            importFile.value.value = '';
        }
    } catch {
        importError.value = t.value.admin.committee.fileNotSent;
    } finally {
        importBusy.value = false;
    }
};

const configurable = computed(
    () => Boolean(props.selection.approval_type && props.selection.business_unit_id && props.rangeReady),
);

const errorList = computed(() => Object.values(page.props.errors ?? {}));

/**
 * Satu halaman melayani tambah dan ubah, tetapi judulnya tidak boleh
 * menyebut keduanya sekaligus: yang menentukan adalah ada-tidaknya baris
 * committee untuk kombinasi yang sedang dipilih.
 */
const pageTitle = computed(() =>
    props.isEdit ? t.value.admin.committee.formEdit : t.value.admin.committee.formAdd,
);
</script>

<template>
    <Head :title="pageTitle" />

    <UiPageHeader :title="pageTitle" :back-href="urls.index" />

    <div class="space-y-5">
        <ul
            v-if="errorList.length"
            class="list-inside list-disc rounded-card bg-danger-soft px-4 py-3 text-sm text-danger-ink ring-1 ring-danger/20"
        >
            <li v-for="(message, index) in errorList" :key="index">{{ message }}</li>
        </ul>

        <UiCard>
            <div class="flex flex-wrap gap-4">
                <div class="min-w-[200px] flex-1">
                    <UiField :label="t.admin.sla.approvalType">
                        <UiSelect
                            v-model="selection.approval_type"
                            :options="typeOptions"
                            :placeholder="t.admin.committee.selectType"
                            @update:model-value="reload"
                        />
                    </UiField>
                </div>

                <div class="min-w-[200px] flex-1">
                    <UiField :label="t.fields.businessUnit">
                        <UiSelect
                            :model-value="selection.business_unit_id"
                            :options="businessUnits"
                            :placeholder="t.admin.committee.selectBu"
                            @update:model-value="onBuChange"
                        />
                    </UiField>
                </div>

                <div class="min-w-[200px] flex-1">
                    <UiField :label="t.fields.unit">
                        <UiSelect
                            v-model="selection.department"
                            :options="unitNames"
                            :disabled="!selection.business_unit_id"
                            :placeholder="t.admin.committee.allUnits"
                            @update:model-value="reload"
                        />
                    </UiField>
                </div>

                <!-- Budget: project yang total budgetnya masuk [min, max] diarahkan
                     ke committee ini. -->
                <div v-if="usesRange" class="min-w-[240px] flex-1">
                    <UiField :label="t.admin.committee.budget">
                        <div class="flex items-center gap-2">
                            <UiInput
                                v-model="budgetMin"
                                :placeholder="t.admin.committee.budgetMin"
                                @blur="commitBudget"
                            />
                            <span class="text-ink-subtle">–</span>
                            <UiInput
                                v-model="budgetMax"
                                :placeholder="t.admin.committee.budgetMax"
                                @blur="commitBudget"
                            />
                        </div>
                    </UiField>
                </div>
            </div>
        </UiCard>

        <UiCard v-if="configurable">
            <p class="text-sm text-ink-subtle">
                {{ t.admin.committee.contextApproval }}
                <span class="font-semibold text-ink-muted">
                    {{ approvalType(selection.approval_type, context.type_label) }}
                </span>
                —
                {{ t.admin.committee.contextBu }}
                <span class="font-semibold text-ink-muted">{{ context.bu_name }}</span> —
                {{ t.admin.committee.contextUnit }}
                <span class="font-semibold text-ink-muted">{{ context.unit_label }}</span>
                <template v-if="usesRange">
                    — {{ t.admin.committee.contextBudget }}
                    <span class="font-semibold text-ink-muted">{{ context.budget_label }}</span>
                </template>
            </p>

            <h3 class="mt-4 text-base font-semibold text-ink">{{ t.admin.committee.reviewerPerLayer }}</h3>

            <p v-if="sponsorLayer" class="mt-1 text-xs text-ink-subtle">
                {{ t.admin.committee.sponsorLayerHint }}
            </p>

            <!-- Hasil import ditampilkan sebelum Save, supaya salah isi ketahuan
                 sebelum tersimpan. -->
            <div v-if="importFilled.length || importNotes.length" class="mt-4 space-y-2">
                <div
                    v-if="importFilled.length"
                    class="rounded-control bg-success-soft px-4 py-3 text-sm text-success-ink ring-1 ring-success/20"
                >
                    <p class="mb-1 font-semibold">{{ t.admin.committee.importedLead }}</p>
                    <ul class="list-inside list-disc space-y-0.5">
                        <li v-for="(text, index) in importFilled" :key="index">{{ text }}</li>
                    </ul>
                </div>

                <div
                    v-if="importNotes.length"
                    class="rounded-control bg-warning-soft px-4 py-3 text-sm text-warning-ink ring-1 ring-warning/20"
                >
                    <p class="mb-1 font-semibold">{{ t.admin.committee.skippedRows }}</p>
                    <ul class="list-inside list-disc space-y-0.5">
                        <li v-for="(text, index) in importNotes" :key="index">{{ text }}</li>
                    </ul>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <div v-for="layer in layerNumbers" :key="layer" class="flex items-center gap-4">
                    <span class="w-20 shrink-0 text-sm font-semibold text-ink-muted">
                        {{ tr(t.admin.committee.layerLabel, { layer }) }}
                    </span>

                    <!-- Layer 1 pada tipe ber-sponsor tidak bisa diubah. -->
                    <UiInput
                        v-if="sponsorLayer && layer === 1"
                        :model-value="t.admin.committee.projectSponsor"
                        readonly
                        class="flex-1"
                    />

                    <div v-else class="flex-1">
                        <UiCombobox
                            :model-value="layerValue(layer)"
                            :search-url="urls.employeeSearch"
                            value-key="email"
                            :initial-label="labels[layer]"
                            :placeholder="t.admin.committee.none"
                            @update:model-value="setLayer(layer, $event)"
                        />
                    </div>
                </div>
            </div>

            <UiButton
                v-if="visible < maxLayers"
                variant="secondary"
                class="mt-4"
                @click="visible = Math.min(maxLayers, visible + 1)"
            >
                {{ t.admin.committee.addLayer }}
            </UiButton>

            <div class="mt-5 flex flex-wrap justify-end gap-3 border-t border-line pt-4">
                <UiButton variant="secondary" @click="importOpen = true">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                        </svg>
                    </template>
                    {{ t.admin.committee.importExcel }}
                </UiButton>

                <UiButton variant="secondary" :href="urls.index">{{ t.common.cancel }}</UiButton>
                <UiButton :loading="form.processing" @click="save">{{ t.common.save }}</UiButton>
            </div>
        </UiCard>

        <UiCard v-else>
            <p class="text-sm text-ink-subtle">
                {{ t.admin.committee.selectFirstLead }}
                <span class="font-semibold text-ink-muted">{{ t.admin.committee.selectFirstType }}</span>
                {{ t.admin.committee.selectFirstAnd }}
                <span class="font-semibold text-ink-muted">{{ t.admin.committee.selectFirstBu }}</span>
                <template v-if="usesRange">{{ t.admin.committee.selectFirstRange }}</template>
                {{ t.admin.committee.selectFirstTail }}
            </p>
        </UiCard>
    </div>

    <UiModal v-model="importOpen" :title="t.admin.committee.importExcel" size="md">
        <p class="text-sm text-ink-muted">{{ t.admin.committee.importIntro }}</p>

        <p class="mt-2 text-xs text-ink-subtle">{{ t.admin.committee.importNote }}</p>

        <a :href="urls.template" class="mt-3 inline-flex text-sm text-brand hover:underline">
            {{ t.admin.committee.downloadTemplate }}
        </a>

        <input
            ref="importFile"
            type="file"
            accept=".xlsx,.csv"
            class="mt-4 block w-full rounded-control border border-line-strong px-3 py-2 text-sm text-ink-muted file:mr-3 file:rounded-control file:border-0 file:bg-surface-muted file:px-4 file:py-1.5 file:font-semibold file:text-ink-muted"
            @change="importError = ''"
        />

        <p
            v-if="importError"
            class="mt-3 rounded-control bg-danger-soft px-3 py-2 text-sm text-danger-ink ring-1 ring-danger/20"
        >
            {{ importError }}
        </p>

        <template #footer>
            <UiButton variant="secondary" @click="importOpen = false">{{ t.common.cancel }}</UiButton>
            <UiButton :loading="importBusy" @click="runImport">{{ t.admin.committee.import }}</UiButton>
        </template>
    </UiModal>
</template>
