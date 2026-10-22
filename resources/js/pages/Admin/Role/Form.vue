<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    UiButton,
    UiCard,
    UiField,
    UiInput,
    UiMultiCombobox,
    UiMultiSelect,
    UiPageHeader,
} from '@/components/ui';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import type { SelectOption } from '@/types/models';

interface PermissionGroup {
    label: string;
    permissions: Array<{ id: number; name: string; label: string }>;
}

interface Values {
    name: string;
    business_units: string[];
    companies: string[];
    locations: string[];
    employees: number[];
    permissions: number[];
}

const props = defineProps<{
    role: { id: number; name: string } | null;
    values: Values;
    permissionGroups: PermissionGroup[];
    permissionCount: number;
    businessUnits: string[];
    employeeLabels: SelectOption[];
    action: string;
    method: 'post' | 'put';
    urls: { index: string; userSearch: string; companies: string; locations: string };
}>();

const { t } = useLocale();
const { permission, permissionGroup } = useLabels();

const form = useForm<Values>({ ...props.values });

/* ---- Restrict: Company & Location mengikuti Business Unit -------------- */

const companyOptions = ref<SelectOption[]>([]);
const locationOptions = ref<SelectOption[]>([]);

/**
 * Endpoint org menerima satu `bu` per permintaan, sedangkan role boleh dibatasi
 * ke beberapa BU sekaligus — jadi hasilnya digabung dan dibuat unik.
 */
const loadCascade = async (): Promise<void> => {
    const units = form.business_units;

    if (!units.length) {
        companyOptions.value = [];
        locationOptions.value = [];
        return;
    }

    const fetchAll = async (url: string): Promise<SelectOption[]> => {
        const responses = await Promise.all(
            units.map((bu) =>
                fetch(`${url}?bu=${encodeURIComponent(bu)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then((r) => (r.ok ? r.json() : []))
                    .catch(() => []),
            ),
        );

        const names = new Set<string>();

        for (const rows of responses) {
            for (const row of Array.isArray(rows) ? rows : []) {
                const name = typeof row === 'string' ? row : (row?.text ?? row?.name ?? row?.value);

                if (name) {
                    names.add(String(name));
                }
            }
        }

        return [...names].sort().map((name) => ({ value: name, label: name }));
    };

    [companyOptions.value, locationOptions.value] = await Promise.all([
        fetchAll(props.urls.companies),
        fetchAll(props.urls.locations),
    ]);
};

watch(() => [...form.business_units], () => void loadCascade(), { immediate: true });

const buOptions = computed<SelectOption[]>(() =>
    props.businessUnits.map((name) => ({ value: name, label: name })),
);

/* ---- Permission: tab per kategori -------------------------------------- */

const activeGroup = ref(props.permissionGroups[0]?.label ?? '');

const selected = computed(() => new Set(form.permissions));

const groupChecked = (group: PermissionGroup): number =>
    group.permissions.filter((p) => selected.value.has(p.id)).length;

const toggleGroup = (group: PermissionGroup, checked: boolean): void => {
    const ids = group.permissions.map((p) => p.id);

    form.permissions = checked
        ? [...new Set([...form.permissions, ...ids])]
        : form.permissions.filter((id) => !ids.includes(id));
};

const toggleAll = (checked: boolean): void => {
    form.permissions = checked
        ? props.permissionGroups.flatMap((g) => g.permissions.map((p) => p.id))
        : [];
};

const togglePermission = (id: number, checked: boolean): void => {
    form.permissions = checked
        ? [...new Set([...form.permissions, id])]
        : form.permissions.filter((entry) => entry !== id);
};

const allChecked = computed(() => form.permissions.length === props.permissionCount);

const submit = (): void => {
    if (props.method === 'put') {
        form.put(props.action, { preserveScroll: true });
    } else {
        form.post(props.action, { preserveScroll: true });
    }
};
</script>

<template>
    <Head
        :title="
            role
                ? tr(t.admin.roles.formEditHead, { name: role.name })
                : t.admin.roles.formCreateHead
        "
    />

    <UiPageHeader
        :title="
            role
                ? tr(t.admin.roles.formEditTitle, { name: role.name })
                : t.admin.roles.formCreateHead
        "
        :back-href="urls.index"
    />

    <form class="space-y-5" @submit.prevent="submit">
        <UiCard>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <UiField :label="t.admin.roles.roleName" required :error="form.errors.name">
                    <UiInput v-model="form.name" />
                </UiField>
            </div>

            <!-- Restrict: dimensi yang dikosongkan berarti "semua" untuk role ini.
                 Lingkupnya diunion lintas role, jadi satu role tanpa batas membuka
                 seluruh dimensi itu (RoleScopeService). -->
            <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <UiField
                    :label="t.admin.roles.restrictGroupCompany"
                    :hint="t.admin.roles.emptyMeansAll"
                    :error="form.errors.business_units"
                >
                    <UiMultiSelect
                        v-model="form.business_units"
                        :options="buOptions"
                        :placeholder="t.list.allBusinessUnits"
                    />
                </UiField>

                <UiField
                    :label="t.admin.roles.restrictCompany"
                    :hint="t.admin.roles.followsBu"
                    :error="form.errors.companies"
                >
                    <UiMultiSelect
                        v-model="form.companies"
                        :options="companyOptions"
                        :disabled="!form.business_units.length"
                        :placeholder="t.admin.roles.allCompanies"
                    />
                </UiField>

                <UiField
                    :label="t.admin.roles.restrictLocation"
                    :hint="t.admin.roles.followsBu"
                    :error="form.errors.locations"
                >
                    <UiMultiSelect
                        v-model="form.locations"
                        :options="locationOptions"
                        :disabled="!form.business_units.length"
                        :placeholder="t.admin.roles.allLocations"
                    />
                </UiField>

                <UiField
                    :label="t.admin.roles.restrictEmployee"
                    :hint="t.admin.roles.emptyMeansAll"
                    :error="form.errors.employees"
                >
                    <UiMultiCombobox
                        v-model="form.employees"
                        :search-url="urls.userSearch"
                        value-key="id"
                        :initial-options="employeeLabels"
                        :placeholder="t.admin.roles.searchEmployee"
                    />
                </UiField>
            </div>
        </UiCard>

        <UiCard>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-ink">{{ t.admin.roles.permissions }}</h3>

                <div class="flex items-center gap-3 text-sm">
                    <span class="text-ink-subtle">
                        {{
                            tr(t.admin.roles.selectedCount, {
                                selected: form.permissions.length,
                                total: permissionCount,
                            })
                        }}
                    </span>

                    <label class="flex cursor-pointer select-none items-center gap-2 rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">
                        <input
                            type="checkbox"
                            class="rounded border-line-strong text-brand focus:ring-brand/40"
                            :checked="allChecked"
                            @change="toggleAll(($event.target as HTMLInputElement).checked)"
                        />
                        <span class="font-medium text-ink-muted">{{ t.admin.roles.checkAll }}</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-col gap-6 sm:flex-row">
                <!-- Tab vertikal: kategori bisa banyak, jadi daftarnya bisa digulir. -->
                <div class="max-h-80 w-full shrink-0 overflow-y-auto scroll-slim border-line pr-2 sm:w-56 sm:border-r">
                    <div class="flex flex-col gap-1">
                        <button
                            v-for="group in permissionGroups"
                            :key="group.label"
                            type="button"
                            class="flex items-center justify-between gap-2 rounded-control px-3 py-2 text-left font-medium transition-colors"
                            :class="
                                activeGroup === group.label
                                    ? 'bg-brand-soft text-brand-ink'
                                    : 'text-ink-subtle hover:bg-surface-muted hover:text-ink-muted'
                            "
                            @click="activeGroup = group.label"
                        >
                            <span class="truncate">{{ permissionGroup(group.label) }}</span>
                            <span
                                class="shrink-0 rounded-full px-1.5 py-0.5 text-xs"
                                :class="
                                    groupChecked(group) > 0
                                        ? 'bg-brand-soft text-brand-ink'
                                        : 'bg-surface-muted text-ink-subtle'
                                "
                            >
                                {{ groupChecked(group) }}/{{ group.permissions.length }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <template v-for="group in permissionGroups" :key="group.label">
                        <div v-show="activeGroup === group.label">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-sm font-semibold text-ink-muted">
                                    {{ permissionGroup(group.label) }}
                                </span>

                                <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-ink-muted">
                                    <input
                                        type="checkbox"
                                        class="rounded border-line-strong text-brand focus:ring-brand/40"
                                        :checked="groupChecked(group) === group.permissions.length"
                                        @change="toggleGroup(group, ($event.target as HTMLInputElement).checked)"
                                    />
                                    {{ tr(t.admin.roles.selectAllIn, { group: permissionGroup(group.label) }) }}
                                </label>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <label
                                    v-for="entry in group.permissions"
                                    :key="entry.id"
                                    class="flex cursor-pointer items-center gap-3 text-sm text-ink-muted"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-line-strong text-brand focus:ring-brand/40"
                                        :checked="selected.has(entry.id)"
                                        @change="togglePermission(entry.id, ($event.target as HTMLInputElement).checked)"
                                    />
                                    <span>{{ permission(entry.name, entry.label) }}</span>
                                </label>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </UiCard>

        <div class="flex justify-end gap-3">
            <UiButton variant="secondary" :href="urls.index">{{ t.common.cancel }}</UiButton>
            <UiButton type="submit" :loading="form.processing">
                {{ role ? t.admin.roles.updateRole : t.admin.roles.formCreateHead }}
            </UiButton>
        </div>
    </form>
</template>
