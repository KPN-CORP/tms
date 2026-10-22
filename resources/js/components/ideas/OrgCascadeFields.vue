<script setup lang="ts">
import { computed, toRef, watch } from 'vue';
import { route } from 'ziggy-js';
import { UiField, UiSelect } from '@/components/ui';
import { useRemoteOptions } from '@/composables/useRemoteOptions';
import { useLocale } from '@/composables/useLocale';
import type { SelectOption } from '@/types/models';

/**
 * Target organisasi sebuah ide: Business Unit -> Unit / Company / Location.
 *
 * Ketiga dropdown turunan mengambil daftarnya dari hcis sesuai BU terpilih,
 * karena isinya terlalu besar untuk dikirim seluruhnya bersama halaman.
 */
const props = defineProps<{
    businessUnits: string[];
    errors: Partial<Record<string, string>>;
}>();

const businessUnit = defineModel<string>('businessUnit', { required: true });
const department = defineModel<string>('department', { required: true });
const company = defineModel<string>('company', { required: true });
const location = defineModel<string>('location', { required: true });

const { t } = useLocale();

const parent = toRef(() => businessUnit.value || undefined);

const departments = useRemoteOptions(route('org.departments'), parent);
const companies = useRemoteOptions(route('org.companies'), parent);
const locations = useRemoteOptions(route('org.locations'), parent);

// Ganti Business Unit mengosongkan ketiga turunannya agar tidak tertinggal
// kombinasi yang tidak nyambung. Nilai awal dari server tidak ikut dikosongkan.
watch(businessUnit, (_value, previous) => {
    if (previous !== undefined) {
        department.value = '';
        company.value = '';
        location.value = '';
    }
});

const buOptions = computed<SelectOption[]>(() =>
    props.businessUnits.map((name) => ({ value: name, label: name })),
);
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <UiField :label="t.ideas.targetedBusinessUnit" required :error="errors.business_unit">
                <UiSelect v-model="businessUnit" :options="buOptions" :placeholder="t.ideas.selectBusinessUnit" />
            </UiField>

            <UiField :label="t.ideas.targetedUnit" required :error="errors.department">
                <UiSelect
                    v-model="department"
                    :options="departments.options.value"
                    :disabled="!businessUnit || departments.loading.value"
                    :placeholder="t.ideas.selectUnit"
                />
            </UiField>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <UiField :label="t.ideas.targetedCompany" :hint="t.common.optional" :error="errors.company">
                <UiSelect
                    v-model="company"
                    :options="companies.options.value"
                    :disabled="!businessUnit || companies.loading.value"
                    :placeholder="t.ideas.selectCompany"
                />
            </UiField>

            <UiField :label="t.ideas.targetedLocation" :hint="t.common.optional" :error="errors.location">
                <UiSelect
                    v-model="location"
                    :options="locations.options.value"
                    :disabled="!businessUnit || locations.loading.value"
                    :placeholder="t.ideas.selectLocation"
                />
            </UiField>
        </div>
    </div>
</template>
