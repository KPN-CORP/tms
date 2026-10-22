<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    AppLink,
    UiButton,
    UiCard,
    UiField,
    UiInput,
    UiModal,
    UiMultiSelect,
    UiPageHeader,
    UiRichText,
    UiSelect,
    UiListFooter,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import { cx } from '@/lib/format';
import { useClientTable } from '@/composables/useClientTable';
import { tr, useLocale } from '@/composables/useLocale';
import type { SelectOption } from '@/types/models';

interface Values {
    sla_setting_id: number | null;
    title: string;
    business_units: string[];
    units: string[];
    companies: string[];
    locations: string[];
    job_levels: string[];
    start_date: string;
    end_date: string;
    attach_detail: boolean;
    repeat_days: string[];
    message: string;
}

interface Recipient {
    name: string;
    email: string;
    unit: string | null;
    job_level: string | null;
    bu: string | null;
}

const props = defineProps<{
    values: Values;
    slaOptions: SelectOption[];
    days: SelectOption[];
    sentCount: number | null;
    isEdit: boolean;
    urls: {
        store: string;
        recipients: string;
        close: string;
        businessUnits: string;
        units: string;
        companies: string;
        locations: string;
        jobLevels: string;
        slaSettings: string;
    };
}>();

/**
 * Konfigurasi saja — tidak ada apa pun di kode ini yang benar-benar mengirim
 * email (lihat CLAUDE.md). Jadwal dan penerimanya disimpan dan bisa ditelusuri,
 * tapi Console\Kernel::schedule() kosong.
 */
const { t } = useLocale();

const form = useForm<Values>({ ...props.values });

/* ---- Pilihan organisasi ------------------------------------------------ */

const buOptions = ref<SelectOption[]>([]);
const unitOptions = ref<SelectOption[]>([]);
const companyOptions = ref<SelectOption[]>([]);
const locationOptions = ref<SelectOption[]>([]);
const jobLevelOptions = ref<SelectOption[]>([]);

const toOptions = (rows: unknown): SelectOption[] => {
    if (!Array.isArray(rows)) {
        return [];
    }

    const names = new Set<string>();

    for (const row of rows) {
        const name =
            typeof row === 'string' || typeof row === 'number'
                ? row
                : ((row as Record<string, unknown>)?.text ??
                  (row as Record<string, unknown>)?.name ??
                  (row as Record<string, unknown>)?.value);

        if (name !== undefined && name !== null && name !== '') {
            names.add(String(name));
        }
    }

    return [...names].sort().map((name) => ({ value: name, label: name }));
};

const getJson = (url: string): Promise<unknown> =>
    fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then((r) => (r.ok ? r.json() : []))
        .catch(() => []);

onMounted(async () => {
    [buOptions.value, jobLevelOptions.value] = await Promise.all([
        getJson(props.urls.businessUnits).then(toOptions),
        getJson(props.urls.jobLevels).then(toOptions),
    ]);
});

/**
 * Unit, Company dan Location mengikuti Business Unit terpilih. Endpoint org
 * menerima satu `bu`, sedangkan jadwal boleh menyasar beberapa BU — hasilnya
 * digabung.
 */
const loadCascade = async (): Promise<void> => {
    const units = form.business_units;

    if (!units.length) {
        unitOptions.value = [];
        companyOptions.value = [];
        locationOptions.value = [];
        return;
    }

    const fetchAll = async (url: string): Promise<SelectOption[]> => {
        const batches = await Promise.all(
            units.map((bu) => getJson(`${url}?bu=${encodeURIComponent(bu)}`)),
        );

        return toOptions(batches.flatMap((rows) => (Array.isArray(rows) ? rows : [])));
    };

    [unitOptions.value, companyOptions.value, locationOptions.value] = await Promise.all([
        fetchAll(props.urls.units),
        fetchAll(props.urls.companies),
        fetchAll(props.urls.locations),
    ]);
};

watch(() => [...form.business_units], () => void loadCascade(), { immediate: true });

/* ---- Repeat On --------------------------------------------------------- */

const toggleDay = (day: string): void => {
    form.repeat_days = form.repeat_days.includes(day)
        ? form.repeat_days.filter((entry) => entry !== day)
        : [...form.repeat_days, day];
};

/* ---- Konfirmasi penerima ----------------------------------------------- */

const confirmOpen = ref(false);
const checking = ref(false);
const total = ref(0);
const truncated = ref(false);
const recipients = ref<Recipient[]>([]);

/** Pratinjau mengambil sampai 200 orang — terlalu banyak untuk satu layar. */
const {
    rows: pageRecipients,
    sort,
    dir,
    perPage,
    meta,
    sortBy,
    setPage,
} = useClientTable({
    rows: recipients,
    columns: {
        name: (recipient) => recipient.name,
        email: (recipient) => recipient.email,
        bu: (recipient) => recipient.bu,
        unit: (recipient) => recipient.unit,
        job_level: (recipient) => recipient.job_level,
    },
    defaultSort: 'name',
});

const noFilter = computed(
    () =>
        !form.business_units.length &&
        !form.units.length &&
        !form.companies.length &&
        !form.locations.length &&
        !form.job_levels.length,
);

/**
 * Submit tidak langsung menyimpan: tampilkan dulu siapa saja yang akan menerima
 * berdasarkan filter yang diisi, baru dikonfirmasi.
 */
const openConfirm = async (): Promise<void> => {
    checking.value = true;

    try {
        const body = new FormData();
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        body.append('_token', token);
        body.append('limit', '200');

        for (const field of ['business_units', 'units', 'companies', 'locations', 'job_levels'] as const) {
            for (const value of form[field]) {
                body.append(`${field}[]`, value);
            }
        }

        const response = await fetch(props.urls.recipients, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });

        const data = await response.json();

        total.value = data.total ?? 0;
        truncated.value = Boolean(data.truncated);
        recipients.value = data.recipients ?? [];
        // Pratinjau baru selalu dibuka dari halaman pertama.
        setPage(1);
        confirmOpen.value = true;
    } finally {
        checking.value = false;
    }
};

const submit = (): void => {
    confirmOpen.value = false;
    form.post(props.urls.store, { preserveScroll: true });
};

/* ---- Hasil pengiriman -------------------------------------------------- */

const resultOpen = ref(props.sentCount !== null);
const delivered = computed(() => (props.sentCount ?? 0) > 0);
</script>

<template>
    <Head :title="t.admin.email.head" />

    <UiPageHeader
        :title="isEdit ? t.admin.email.editHead : t.admin.email.head"
        :description="t.admin.email.description"
    >
        <template #actions>
            <UiButton variant="secondary" :href="urls.close">{{ t.common.close }}</UiButton>
        </template>
    </UiPageHeader>

    <UiCard>
        <form class="space-y-5" @submit.prevent="openConfirm">
            <UiField :label="t.admin.email.slaSetting" required :error="form.errors.sla_setting_id">
                <UiSelect
                    v-model="form.sla_setting_id"
                    :options="slaOptions"
                    :placeholder="t.admin.email.selectSla"
                />

                <template v-if="!slaOptions.length">
                    <p class="mt-1 text-xs text-ink-subtle">
                        {{ t.admin.email.noSlaLead }}
                        <AppLink :href="urls.slaSettings" class="font-semibold underline">
                            {{ t.admin.email.noSlaLink }}
                        </AppLink>
                        {{ t.admin.email.noSlaTail }}
                    </p>
                </template>
            </UiField>

            <UiField :label="t.admin.email.title" required :error="form.errors.title">
                <UiInput v-model="form.title" :placeholder="t.admin.email.titlePlaceholder" />
            </UiField>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <UiField
                    :label="t.fields.businessUnit"
                    :hint="t.admin.roles.emptyMeansAll"
                    :error="form.errors.business_units"
                >
                    <UiMultiSelect
                        v-model="form.business_units"
                        :options="buOptions"
                        :placeholder="t.list.allBusinessUnits"
                    />
                </UiField>

                <UiField :label="t.fields.unit" :error="form.errors.units">
                    <UiMultiSelect
                        v-model="form.units"
                        :options="unitOptions"
                        :disabled="!form.business_units.length"
                        :placeholder="t.list.allUnits"
                    />
                </UiField>

                <UiField :label="t.fields.company" :error="form.errors.companies">
                    <UiMultiSelect
                        v-model="form.companies"
                        :options="companyOptions"
                        :disabled="!form.business_units.length"
                        :placeholder="t.admin.email.allCompanies"
                    />
                </UiField>

                <UiField :label="t.fields.location" :error="form.errors.locations">
                    <UiMultiSelect
                        v-model="form.locations"
                        :options="locationOptions"
                        :disabled="!form.business_units.length"
                        :placeholder="t.admin.email.allLocations"
                    />
                </UiField>

                <UiField :label="t.admin.email.jobLevel" :error="form.errors.job_levels">
                    <UiMultiSelect
                        v-model="form.job_levels"
                        :options="jobLevelOptions"
                        :placeholder="t.admin.email.allJobLevels"
                    />
                </UiField>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <UiField :label="t.fields.startDate" :error="form.errors.start_date">
                    <UiInput v-model="form.start_date" type="date" />
                </UiField>

                <UiField :label="t.fields.endDate" :error="form.errors.end_date">
                    <UiInput v-model="form.end_date" type="date" :min="form.start_date || undefined" />
                </UiField>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-muted">
                <input
                    v-model="form.attach_detail"
                    type="checkbox"
                    class="rounded border-line-strong text-brand focus:ring-brand/40"
                />
                {{ t.admin.email.attachDetail }}
            </label>

            <div>
                <p class="mb-1.5 block text-xs font-medium text-ink-label">{{ t.admin.email.repeatOn }}</p>

                <div class="grid grid-cols-7 overflow-hidden rounded-control border border-brand/40">
                    <button
                        v-for="(day, index) in days"
                        :key="day.value"
                        type="button"
                        :class="
                            cx(
                                'px-2 py-2 text-center text-sm transition-colors',
                                index < days.length - 1 && 'border-r border-brand/40',
                                form.repeat_days.includes(String(day.value))
                                    ? 'bg-brand font-semibold text-white'
                                    : 'bg-surface text-brand hover:bg-brand-soft',
                            )
                        "
                        :aria-pressed="form.repeat_days.includes(String(day.value))"
                        @click="toggleDay(String(day.value))"
                    >
                        {{ day.label }}
                    </button>
                </div>
            </div>

            <UiField :label="t.admin.email.messages" :error="form.errors.message">
                <UiRichText v-model="form.message" />
            </UiField>

            <div class="flex items-center justify-end gap-3 pt-2">
                <UiButton variant="secondary" :href="urls.close">{{ t.common.cancel }}</UiButton>
                <UiButton type="submit" :loading="checking" :disabled="form.processing">
                    {{ t.common.submit }}
                </UiButton>
            </div>
        </form>
    </UiCard>

    <!-- Konfirmasi penerima: daftar dihitung dari filter yang diisi. -->
    <UiModal v-model="confirmOpen" :title="t.admin.email.confirmTitle" size="xl">
        <template #header>
            <h2 class="text-base font-semibold text-ink">{{ t.admin.email.confirmTitle }}</h2>
            <p class="mt-0.5 text-sm text-ink-subtle">
                {{ tr(t.admin.email.confirmLead, { count: total }) }}
            </p>
        </template>

        <div
            v-if="noFilter && total > 0"
            class="mb-3 rounded-control bg-warning-soft px-4 py-3 text-sm text-warning-ink ring-1 ring-warning/25"
        >
            {{ t.admin.email.noFilterWarning }}
        </div>

        <div
            v-if="total === 0"
            class="mb-3 rounded-control bg-danger-soft px-4 py-3 text-sm text-danger-ink ring-1 ring-danger/20"
        >
            {{ t.admin.email.noMatch }}
        </div>

        <UiTable
            :empty="recipients.length === 0"
            :empty-title="t.admin.email.emptyTitle"
            :empty-message="t.admin.email.emptyMessage"
        >
            <template #head>
                <UiTh sort-key="name" :sort="sort" :dir="dir" @sort="sortBy">{{ t.fields.name }}</UiTh>
                <UiTh sort-key="email" :sort="sort" :dir="dir" @sort="sortBy">{{ t.auth.email }}</UiTh>
                <UiTh sort-key="bu" :sort="sort" :dir="dir" @sort="sortBy">{{ t.fields.businessUnit }}</UiTh>
                <UiTh sort-key="unit" :sort="sort" :dir="dir" @sort="sortBy">{{ t.fields.unit }}</UiTh>
                <UiTh sort-key="job_level" :sort="sort" :dir="dir" @sort="sortBy">
                    {{ t.admin.email.jobLevel }}
                </UiTh>
            </template>

            <tr v-for="recipient in pageRecipients" :key="recipient.email">
                <UiTd strong>{{ recipient.name }}</UiTd>
                <UiTd>{{ recipient.email }}</UiTd>
                <UiTd>{{ recipient.bu ?? '—' }}</UiTd>
                <UiTd>{{ recipient.unit ?? '—' }}</UiTd>
                <UiTd>{{ recipient.job_level ?? '—' }}</UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />

        <p v-if="truncated" class="mt-2 text-xs text-ink-subtle">
            {{ tr(t.admin.email.showingFirst, { shown: recipients.length, total }) }}
        </p>

        <template #footer>
            <UiButton variant="secondary" @click="confirmOpen = false">{{ t.common.back }}</UiButton>
            <UiButton :loading="form.processing" @click="submit">
                {{ t.admin.email.confirmSubmit }}
            </UiButton>
        </template>
    </UiModal>

    <!-- Hasil pengiriman, ditampilkan sekali sesudah redirect. -->
    <UiModal v-model="resultOpen" size="sm">
        <div class="text-center">
            <div
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full"
                :class="delivered ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning-ink'"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path v-if="delivered" stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                </svg>
            </div>

            <h3 class="mt-4 text-lg font-bold text-ink">
                {{ delivered ? t.admin.email.savedTitle : t.admin.email.noRecipientsTitle }}
            </h3>
            <p class="mt-1.5 text-sm text-ink-muted">
                {{
                    delivered
                        ? tr(t.admin.email.savedBody, { count: sentCount ?? 0 })
                        : t.admin.email.noRecipientsBody
                }}
            </p>
        </div>

        <template #footer>
            <UiButton autofocus @click="resultOpen = false">OK</UiButton>
        </template>
    </UiModal>
</template>
