<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    UiBadge,
    UiButton,
    UiCard,
    UiField,
    UiInput,
    UiListFooter,
    UiPageHeader,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import { useClientTable } from '@/composables/useClientTable';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';

interface RequiredRole {
    role: string;
    total: number;
}

interface Category {
    id: number;
    code: string;
    name: string;
    leader_grade_min: number | null;
    leader_grade_max: number | null;
    sponsor_grade_min: number | null;
    sponsor_grade_max: number | null;
    max_team_members: number | null;
    required_roles: RequiredRole[];
    is_active: boolean;
    urls: { edit: string; update: string; toggle: string; destroy: string };
}

const props = defineProps<{
    categories: Category[];
    editing: {
        id: number;
        name: string;
        code: string;
        leader_grade_min: number | null;
        leader_grade_max: number | null;
        sponsor_grade_min: number | null;
        sponsor_grade_max: number | null;
        max_team_members: number | null;
        required_roles: RequiredRole[];
    } | null;
    urls: { index: string; store: string; update: string | null };
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

const {
    rows: pageCategories,
    sort,
    dir,
    perPage,
    meta,
    sortBy,
    setPage,
} = useClientTable({
    rows: () => props.categories,
    columns: {
        code: (category) => category.code,
        name: (category) => category.name,
        // Rentang grade diurutkan dari batas bawahnya — itu yang menentukan
        // siapa yang mulai memenuhi syarat.
        leader_grade: (category) => category.leader_grade_min,
        sponsor_grade: (category) => category.sponsor_grade_min,
        max_team: (category) => category.max_team_members,
        required_roles: (category) => category.required_roles.length,
    },
    defaultSort: 'code',
});

/**
 * Satu form untuk tambah & ubah — `editing` dari server yang menentukan tujuan
 * submit, sama seperti versi Blade yang memakai `?edit=`.
 */
interface Values {
    name: string;
    code: string;
    leader_grade_min: string;
    leader_grade_max: string;
    sponsor_grade_min: string;
    sponsor_grade_max: string;
    max_team_members: string;
    roles: string[];
    totals: string[];
}

const asText = (value: number | null | undefined): string => (value === null || value === undefined ? '' : String(value));

const initialValues = (): Values => ({
    name: props.editing?.name ?? '',
    code: props.editing?.code ?? '',
    leader_grade_min: asText(props.editing?.leader_grade_min),
    leader_grade_max: asText(props.editing?.leader_grade_max),
    sponsor_grade_min: asText(props.editing?.sponsor_grade_min),
    sponsor_grade_max: asText(props.editing?.sponsor_grade_max),
    max_team_members: asText(props.editing?.max_team_members),
    roles: (props.editing?.required_roles ?? []).map((r) => r.role),
    totals: (props.editing?.required_roles ?? []).map((r) => String(r.total)),
});

const form = useForm<Values>(initialValues());

// Baris role dikelola terpisah lalu disalin ke roles[]/totals[] saat submit,
// karena server menerimanya sebagai dua array sejajar.
const roleRows = reactive<RequiredRole[]>([]);

const seed = (): void => {
    form.defaults(initialValues());
    form.reset();
    roleRows.splice(0, roleRows.length, ...(props.editing?.required_roles ?? []).map((r) => ({ ...r })));

    if (!roleRows.length) {
        roleRows.push({ role: '', total: 1 });
    }
};

seed();

watch(() => props.editing, seed);

const submitting = ref(false);

const submit = (): void => {
    form.roles = roleRows.map((row) => row.role);
    form.totals = roleRows.map((row) => String(row.total));
    submitting.value = true;

    const done = { preserveScroll: true, onFinish: () => (submitting.value = false) };

    if (props.editing && props.urls.update) {
        form.put(props.urls.update, done);
    } else {
        form.post(props.urls.store, done);
    }
};

const cancelEdit = (): void => router.get(props.urls.index, {}, { preserveScroll: true });

const toggle = (category: Category): void => {
    router.post(category.urls.toggle, {}, { preserveScroll: true });
};

const remove = async (category: Category): Promise<void> => {
    const ok = await confirm({
        title: t.value.admin.category.deleteTitle,
        message: tr(t.value.admin.category.deleteMessage, { name: category.name }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(category.urls.destroy, { preserveScroll: true });
    }
};

const grade = (min: number | null, max: number | null): string =>
    min === null && max === null ? '—' : `${min ?? '—'} – ${max ?? '—'}`;
</script>

<template>
    <Head :title="t.admin.category.head" />

    <UiPageHeader :title="t.admin.category.head" :description="t.admin.category.description" />

    <div class="space-y-5">
        <UiCard flush :title="t.admin.category.listTitle">
            <UiTable
                :empty="categories.length === 0"
                :empty-title="t.admin.category.emptyTitle"
                :empty-message="t.admin.category.emptyMessage"
            >
                <template #head>
                    <UiTh sort-key="code" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.category.code }}
                    </UiTh>
                    <UiTh sort-key="name" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.category.name }}
                    </UiTh>
                    <UiTh sort-key="leader_grade" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.category.leaderGrade }}
                    </UiTh>
                    <UiTh sort-key="sponsor_grade" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.category.sponsorGrade }}
                    </UiTh>
                    <UiTh sort-key="max_team" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.category.maxTeam }}
                    </UiTh>
                    <UiTh sort-key="required_roles" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.category.requireRole }}
                    </UiTh>
                    <UiTh align="right">{{ t.admin.category.actions }}</UiTh>
                </template>

                <tr v-for="category in pageCategories" :key="category.id" class="align-top transition-colors hover:bg-surface-muted">
                    <UiTd strong>{{ category.code }}</UiTd>
                    <UiTd>{{ category.name }}</UiTd>
                    <UiTd nowrap>{{ grade(category.leader_grade_min, category.leader_grade_max) }}</UiTd>
                    <UiTd nowrap>{{ grade(category.sponsor_grade_min, category.sponsor_grade_max) }}</UiTd>
                    <UiTd>{{ category.max_team_members ?? '—' }}</UiTd>

                    <UiTd>
                        <span v-if="!category.required_roles.length" class="text-ink-subtle">—</span>
                        <span v-else class="flex flex-wrap gap-1">
                            <UiBadge v-for="role in category.required_roles" :key="role.role" tone="neutral">
                                {{ role.role }} × {{ role.total }}
                            </UiBadge>
                        </span>
                    </UiTd>

                    <UiTd align="right">
                        <div class="flex items-center justify-end gap-2">
                            <UiButton
                                size="sm"
                                :variant="category.is_active ? 'success' : 'subtle'"
                                @click="toggle(category)"
                            >
                                {{ category.is_active ? t.admin.category.active : t.admin.category.inactive }}
                            </UiButton>

                            <UiButton size="sm" variant="secondary" :href="category.urls.edit">
                                {{ t.common.edit }}
                            </UiButton>
                            <UiButton size="sm" variant="danger" @click="remove(category)">
                                {{ t.common.delete }}
                            </UiButton>
                        </div>
                    </UiTd>
                </tr>
            </UiTable>

            <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />
        </UiCard>

        <UiCard
            :title="
                editing
                    ? tr(t.admin.category.editTitle, { code: editing.code })
                    : t.admin.category.addTitle
            "
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <UiField :label="t.admin.category.name" required :error="form.errors.name">
                        <UiInput v-model="form.name" />
                    </UiField>

                    <UiField :label="t.admin.category.code" required :error="form.errors.code">
                        <UiInput v-model="form.code" />
                    </UiField>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <UiField :label="t.admin.category.leaderGradeMin" :error="form.errors.leader_grade_min">
                        <UiInput v-model="form.leader_grade_min" type="number" />
                    </UiField>

                    <UiField :label="t.admin.category.leaderGradeMax" :error="form.errors.leader_grade_max">
                        <UiInput v-model="form.leader_grade_max" type="number" />
                    </UiField>

                    <UiField :label="t.admin.category.maxPersonInTeam" required :error="form.errors.max_team_members">
                        <UiInput v-model="form.max_team_members" type="number" />
                    </UiField>

                    <UiField :label="t.admin.category.sponsorGradeMin" :error="form.errors.sponsor_grade_min">
                        <UiInput v-model="form.sponsor_grade_min" type="number" />
                    </UiField>

                    <UiField :label="t.admin.category.sponsorGradeMax" :error="form.errors.sponsor_grade_max">
                        <UiInput v-model="form.sponsor_grade_max" type="number" />
                    </UiField>
                </div>

                <div>
                    <p class="mb-1 block text-xs font-medium text-ink-label">{{ t.admin.category.requireRole }}</p>

                    <div class="space-y-2">
                        <div v-for="(row, index) in roleRows" :key="index" class="flex items-center gap-2">
                            <UiInput
                                v-model="row.role"
                                :placeholder="t.admin.category.rolePlaceholder"
                                class="min-w-0 flex-1"
                            />
                            <div class="w-28 shrink-0">
                                <UiInput
                                    v-model.number="row.total"
                                    type="number"
                                    :placeholder="t.admin.category.qtyPlaceholder"
                                />
                            </div>
                            <button
                                v-if="roleRows.length > 1"
                                type="button"
                                class="shrink-0 rounded p-2 text-danger transition-colors hover:bg-danger-soft"
                                :aria-label="t.admin.category.removeRole"
                                @click="roleRows.splice(index, 1)"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <UiButton size="sm" variant="secondary" class="mt-2" @click="roleRows.push({ role: '', total: 1 })">
                        {{ t.admin.category.addRole }}
                    </UiButton>
                </div>

                <div class="flex justify-end gap-3">
                    <UiButton v-if="editing" variant="secondary" @click="cancelEdit">
                        {{ t.common.cancel }}
                    </UiButton>
                    <UiButton type="submit" :loading="submitting">
                        {{ editing ? t.common.update : t.common.add }}
                    </UiButton>
                </div>
            </form>
        </UiCard>
    </div>
</template>
