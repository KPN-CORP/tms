<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiCard, UiField, UiInput, UiPageHeader, UiSelect, UiStatCard } from '@/components/ui';
import { useRemoteOptions } from '@/composables/useRemoteOptions';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { cleanQuery } from '@/lib/query';
import type { Tone } from '@/lib/status';
import type { SelectOption } from '@/types/models';
import type { SharedProps } from '@/types/inertia';

interface MetricCard {
    metric: string;
    label: string;
    value: number | string;
    tone: Tone;
    href: string;
    external: boolean;
}

const props = defineProps<{
    ideaCards: MetricCard[];
    projectCards: MetricCard[];
    canActAs: boolean;
    as: string;
    views: Record<string, string>;
    filters: { bu: string | null; unit: string | null; from: string | null; to: string | null };
    hasFilter: boolean;
    businessUnits: string[];
}>();

const page = usePage<SharedProps>();
const { t } = useLocale();
const { metric: metricLabel } = useLabels();

const user = computed(() => page.props.auth.user);

/** Pilihan "Act as" dikirim server sebagai key => label Inggris. */
const viewLabel = (key: string, fallback: string): string =>
    (t.value.dashboard.views as Record<string, string | undefined>)[key] ?? fallback;

/**
 * Filter disimpan lokal dan baru dikirim saat Apply — sengaja tidak auto-submit,
 * sama seperti versi Blade: rentang periode biasanya diisi dua kolom sekaligus.
 */
const form = reactive({ ...props.filters });

/** Server adalah sumber kebenaran; samakan kembali sesudah setiap kunjungan. */
watch(
    () => props.filters,
    (filters) => Object.assign(form, filters),
);

const buOptions = computed<SelectOption[]>(() =>
    props.businessUnits.map((name) => ({ value: name, label: name })),
);

const selectedBu = computed(() => form.bu);
const { options: unitOptions, loading: unitsLoading } = useRemoteOptions(route('org.unit-names'), selectedBu);

// Ganti Business Unit mengosongkan Unit agar tidak tertinggal kombinasi yang
// tidak nyambung.
watch(
    () => form.bu,
    (_value, previous) => {
        if (previous !== undefined) {
            form.unit = null;
        }
    },
);

const applying = ref(false);

const apply = (): void => {
    router.get(route('dashboard'), cleanQuery({ ...form, as: props.as }), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (applying.value = true),
        onFinish: () => (applying.value = false),
    });
};

const reset = (): void => {
    router.get(route('dashboard'), { as: props.as }, { preserveScroll: true });
};

const actAs = (key: string): void => {
    // Filter yang sedang aktif ikut terbawa saat berpindah cakupan.
    router.get(route('dashboard'), cleanQuery({ ...props.filters, as: key }), { preserveScroll: true });
};

const errors = computed(() => page.props.errors ?? {});
</script>

<template>
    <Head :title="t.dashboard.head" />

    <UiPageHeader
        :title="tr(t.dashboard.welcome, { name: user?.name ?? '' })"
        :description="t.dashboard.description"
    >
        <template v-if="canActAs" #actions>
            <!-- "Act as" hanya muncul bagi pemegang 'dashboard.act-as'. Cakupannya
                 dikunci lagi di controller, bukan sekadar disembunyikan di sini. -->
            <div
                class="inline-flex rounded-control bg-surface p-1 shadow-card ring-1 ring-line/70"
                role="group"
                :aria-label="t.dashboard.dataScope"
            >
                <button
                    v-for="(label, key) in views"
                    :key="key"
                    type="button"
                    class="rounded px-3 py-1.5 text-sm font-semibold transition-colors"
                    :class="as === key ? 'bg-brand text-white' : 'text-ink-muted hover:text-ink'"
                    :aria-pressed="as === key"
                    @click="actAs(String(key))"
                >
                    {{ viewLabel(String(key), label) }}
                </button>
            </div>
        </template>
    </UiPageHeader>

    <div class="space-y-5">
        <UiCard flush>
            <form class="flex flex-wrap items-end gap-3 p-4" @submit.prevent="apply">
                <div class="w-full sm:w-52">
                    <UiField :label="t.list.businessUnit">
                        <UiSelect v-model="form.bu" :options="buOptions" :placeholder="t.list.allBusinessUnits" />
                    </UiField>
                </div>

                <div class="w-full sm:w-52">
                    <UiField :label="t.list.unit">
                        <UiSelect
                            v-model="form.unit"
                            :options="unitOptions"
                            :disabled="unitsLoading"
                            :placeholder="t.list.allUnits"
                        />
                    </UiField>
                </div>

                <div class="w-full sm:w-44">
                    <UiField :label="t.fields.periodFrom" :error="errors.from">
                        <UiInput v-model="form.from" type="date" :max="form.to ?? undefined" />
                    </UiField>
                </div>

                <div class="w-full sm:w-44">
                    <UiField :label="t.fields.periodTo" :error="errors.to">
                        <UiInput v-model="form.to" type="date" :min="form.from ?? undefined" />
                    </UiField>
                </div>

                <UiButton type="submit" :loading="applying">{{ t.common.apply }}</UiButton>
                <UiButton v-if="hasFilter" variant="ghost" @click="reset">{{ t.common.reset }}</UiButton>
            </form>
        </UiCard>

        <section>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-ink-subtle">{{ t.dashboard.ideaMonitoring }}</h2>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                <UiStatCard
                    v-for="card in ideaCards"
                    :key="card.metric"
                    :label="metricLabel(card.metric, card.label)"
                    :value="card.value"
                    :tone="card.tone"
                    :href="card.href"
                    :external="card.external"
                />
            </div>
        </section>

        <section>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-ink-subtle">{{ t.dashboard.projectMonitoring }}</h2>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <UiStatCard
                    v-for="card in projectCards"
                    :key="card.metric"
                    :label="metricLabel(card.metric, card.label)"
                    :value="card.value"
                    :tone="card.tone"
                    :href="card.href"
                    :external="card.external"
                />
            </div>
        </section>
    </div>
</template>
