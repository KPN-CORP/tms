<script setup lang="ts">
import { computed, toRef, useSlots } from 'vue';
import { route } from 'ziggy-js';
import { useLocale } from '@/composables/useLocale';
import UiButton from '../ui/UiButton.vue';
import UiInput from '../ui/UiInput.vue';
import UiSelect from '../ui/UiSelect.vue';
import { useRemoteOptions } from '@/composables/useRemoteOptions';
import type { ListQuery, SelectOption } from '@/types/models';

/**
 * Baris filter untuk semua halaman daftar: pencarian + Business Unit + Unit
 * (+ Status opsional).
 *
 * Dua keluarga filter hidup berdampingan di aplikasi ini:
 *  - berbasis NAMA (`bu` / `unit`, snapshot dari Darwinbox) — My Ideas,
 *    My Project, Dashboard;
 *  - berbasis FK lokal (`business_unit_id` / `department_id`) — halaman admin.
 *
 * Keduanya ditangani di sini lewat `buField`/`unitField` dan `units`, supaya
 * perilaku filter (debounce, cascade, reset) hanya punya satu implementasi.
 */
const props = withDefaults(
    defineProps<{
        filters: ListQuery;
        businessUnits?: SelectOption[] | null;
        /**
         * Daftar Unit yang sudah ada di klien. Bila dibiarkan kosong dan
         * `buField` memakai nama, Unit diambil dari endpoint hcis sesuai BU
         * terpilih — daftarnya terlalu besar untuk dikirim seluruhnya.
         */
        units?: SelectOption[] | null;
        statuses?: SelectOption[] | null;
        buField?: 'bu' | 'business_unit_id';
        unitField?: 'unit' | 'department_id';
        /** Dibiarkan kosong berarti pakai teks bawaan dari kamus. */
        searchPlaceholder?: string | null;
        active?: boolean;
    }>(),
    {
        businessUnits: null,
        units: null,
        statuses: null,
        buField: 'bu',
        unitField: 'unit',
        searchPlaceholder: null,
        active: false,
    },
);

const emit = defineEmits<{ reset: [] }>();

const slots = useSlots();
const { t } = useLocale();

const placeholder = computed(() => props.searchPlaceholder ?? t.value.list.searchPlaceholder);

const selectedBu = computed(() => props.filters[props.buField] as string | number | undefined);

/** Hanya untuk filter berbasis nama; FK lokal selalu memakai daftar `units`. */
const remote = useRemoteOptions(route('org.unit-names'), toRef(() => (props.units ? undefined : selectedBu.value)));

const unitOptions = computed<SelectOption[]>(() => {
    if (!props.units) {
        return remote.options.value;
    }

    const bu = selectedBu.value;

    return bu ? props.units.filter((option) => String(option.parent ?? '') === String(bu)) : props.units;
});

/** Ganti Business Unit mengosongkan Unit agar kombinasinya tidak tertinggal. */
const onBuChange = (value: string | number | null | undefined): void => {
    props.filters[props.buField] = value ?? undefined;
    props.filters[props.unitField] = undefined;
};
</script>

<template>
    <div class="flex flex-wrap items-end gap-3 border-b border-line px-4 py-4">
        <div class="w-full sm:w-64">
            <label for="list-search" class="mb-1 block text-xs font-medium text-ink-label">{{ t.list.search }}</label>
            <UiInput id="list-search" v-model="filters.q" type="search" :placeholder="placeholder">
                <template #leading>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.383 9.838l3.89 3.889a.75.75 0 1 0 1.06-1.06l-3.889-3.89A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" />
                    </svg>
                </template>
            </UiInput>
        </div>

        <div v-if="businessUnits" class="w-full sm:w-56">
            <label class="mb-1 block text-xs font-medium text-ink-label">{{ t.list.businessUnit }}</label>
            <UiSelect
                :model-value="filters[buField] as string | number | null"
                :options="businessUnits"
                :placeholder="t.list.allBusinessUnits"
                @update:model-value="onBuChange"
            />
        </div>

        <div v-if="businessUnits" class="w-full sm:w-56">
            <label class="mb-1 block text-xs font-medium text-ink-label">{{ t.list.unit }}</label>
            <UiSelect
                :model-value="filters[unitField] as string | number | null"
                :options="unitOptions"
                :disabled="remote.loading.value"
                :placeholder="t.list.allUnits"
                @update:model-value="filters[unitField] = $event ?? undefined"
            />
        </div>

        <div v-if="statuses" class="w-full sm:w-48">
            <label class="mb-1 block text-xs font-medium text-ink-label">{{ t.list.status }}</label>
            <UiSelect v-model="filters.status" :options="statuses" :placeholder="t.list.allStatuses" />
        </div>

        <slot name="extra" />

        <UiButton v-if="active" variant="ghost" @click="emit('reset')">{{ t.list.reset }}</UiButton>

        <div v-if="slots.actions" class="ml-auto flex items-end gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
