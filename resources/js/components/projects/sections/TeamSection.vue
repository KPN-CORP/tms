<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    UiButton,
    UiCombobox,
    UiField,
    UiInput,
    UiListFooter,
    UiModal,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import CollapsibleSection from '../CollapsibleSection.vue';
import { useClientTable } from '@/composables/useClientTable';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { reportFields } from '@/composables/useReportContext';
import type { RoleSlot, TeamMember } from '../types';

const props = defineProps<{
    members: TeamMember[];
    canEdit: boolean;
    /** Slot role yang masih kurang untuk kategori project ini. */
    roleSlots: RoleSlot[];
    categoryCode: string | null;
    storeUrl: string;
    userSearchUrl: string;
    /** Error bernama 'member' dari server (validasi / batas anggota). */
    memberErrors: string[];
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

/**
 * Sort & halaman dikerjakan di klien: barisnya sudah ada seluruhnya di payload
 * Project Detail, dan mengubahnya tidak pernah mengubah apa yang tersimpan —
 * pengeditan berjalan lewat dialog, bukan lewat baris yang sedang tampil.
 */
const {
    rows: pageMembers,
    sort,
    dir,
    perPage,
    meta,
    sortBy,
    setPage,
} = useClientTable({
    rows: () => props.members,
    columns: {
        name: (member) => member.name,
        role: (member) => member.role,
    },
});

/* ---- Edit satu anggota ------------------------------------------------- */

const editing = ref<TeamMember | null>(null);

const editForm = useForm<{ user_id: number | null; role: string; _version: string | null }>({
    user_id: null,
    role: '',
    _version: null,
});

const openEdit = (member: TeamMember): void => {
    editing.value = member;
    editForm.defaults({ user_id: member.user_id, role: member.role ?? '', _version: member.version });
    editForm.reset();
    editForm.clearErrors();
};

const saveEdit = (): void => {
    const member = editing.value;

    if (!member) {
        return;
    }

    editForm
        .transform((data) => ({ ...data, ...reportFields() }))
        .put(member.urls.update, {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
        });
};

const remove = async (member: TeamMember): Promise<void> => {
    const ok = await confirm({
        title: t.value.projects.team.removeTitle,
        message: tr(t.value.projects.team.removeMessage, {
            name: member.name ?? t.value.projects.team.thisMember,
        }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(member.urls.destroy, { data: reportFields(), preserveScroll: true });
    }
};

/* ---- Tambah anggota (banyak baris sekaligus) --------------------------- */

interface NewRow {
    key: number;
    role: string;
    /** Baris dari kebutuhan role kategori: namanya terkunci. */
    locked: boolean;
    user_id: number | null;
}

const adding = ref(false);
const rows = ref<NewRow[]>([]);
const addError = ref('');

let nextKey = 0;

/**
 * Baris awal = satu baris per slot role yang masih kurang (role terkunci).
 * Bila kategori tidak punya kebutuhan role, mulai dengan satu baris manual.
 */
const buildRows = (): NewRow[] => {
    const built: NewRow[] = [];

    for (const slot of props.roleSlots) {
        for (let i = 0; i < slot.remaining; i++) {
            built.push({ key: nextKey++, role: slot.role, locked: true, user_id: null });
        }
    }

    if (!built.length) {
        built.push({ key: nextKey++, role: '', locked: false, user_id: null });
    }

    return built;
};

const openAdd = (): void => {
    rows.value = buildRows();
    addError.value = '';
    adding.value = true;
};

// Server menolak (mis. batas anggota) → dialog dibuka lagi agar pesannya terbaca.
watch(
    () => props.memberErrors,
    (errors) => {
        if (errors.length) {
            adding.value = true;
        }
    },
    { immediate: true },
);

const addRow = (): void => {
    rows.value.push({ key: nextKey++, role: '', locked: false, user_id: null });
};

const submitting = ref(false);

const submitAdd = (): void => {
    // Baris tanpa nama tidak ikut dikirim — slot required boleh diisi bertahap.
    const filled = rows.value.filter((row) => row.user_id !== null);

    if (!filled.length) {
        addError.value = t.value.projects.team.selectAtLeastOne;
        return;
    }

    addError.value = '';
    submitting.value = true;

    router.post(
        props.storeUrl,
        {
            ...reportFields(),
            rows: filled.map((row) => ({ role: row.role, user_id: row.user_id })),
        },
        {
            preserveScroll: true,
            onSuccess: () => (adding.value = false),
            onFinish: () => (submitting.value = false),
        },
    );
};

const columns = computed(() => (props.canEdit ? 3 : 2));
</script>

<template>
    <CollapsibleSection :title="t.projects.team.title" storage-key="team" anchor="section-team">
        <template #raw>
            <UiTable
                :empty="members.length === 0"
                :empty-title="t.projects.team.emptyTitle"
                :empty-message="t.projects.team.emptyMessage"
            >
                <template #head>
                    <UiTh sort-key="name" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.team.name }}
                    </UiTh>
                    <UiTh sort-key="role" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.projects.team.roleInProject }}
                    </UiTh>
                    <UiTh v-if="canEdit" align="right">{{ t.fields.action }}</UiTh>
                </template>

                <tr v-for="member in pageMembers" :key="member.id" class="transition-colors hover:bg-surface-muted">
                    <UiTd strong>{{ member.name ?? '-' }}</UiTd>
                    <UiTd>{{ member.role ?? '-' }}</UiTd>

                    <UiTd v-if="canEdit" align="right">
                        <div class="flex items-center justify-end gap-3 text-xs">
                            <button type="button" class="font-medium text-brand hover:underline" @click="openEdit(member)">
                                {{ t.common.edit }}
                            </button>
                            <button type="button" class="font-medium text-danger hover:underline" @click="remove(member)">
                                {{ t.common.delete }}
                            </button>
                        </div>
                    </UiTd>
                </tr>
            </UiTable>

            <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />

            <div v-if="canEdit" class="flex justify-end border-t border-line p-4">
                <UiButton size="sm" @click="openAdd">{{ t.projects.team.addMember }}</UiButton>
            </div>
        </template>
    </CollapsibleSection>

    <!-- Dialog: ubah satu anggota -->
    <UiModal
        :model-value="editing !== null"
        :title="t.projects.team.editTitle"
        @update:model-value="editing = null"
    >
        <div class="space-y-3">
            <UiField :label="t.projects.team.name" :error="editForm.errors.user_id">
                <UiCombobox
                    v-model="editForm.user_id"
                    :search-url="userSearchUrl"
                    value-key="id"
                    :initial-label="editing?.name"
                    :placeholder="t.projects.team.namePlaceholder"
                />
            </UiField>

            <UiField :label="t.projects.team.roleInProject" :error="editForm.errors.role">
                <UiInput v-model="editForm.role" />
            </UiField>
        </div>

        <template #footer>
            <UiButton variant="secondary" @click="editing = null">{{ t.common.cancel }}</UiButton>
            <UiButton :loading="editForm.processing" @click="saveEdit">{{ t.common.save }}</UiButton>
        </template>
    </UiModal>

    <!-- Dialog: tambah anggota, beberapa baris sekaligus -->
    <UiModal v-model="adding" :title="t.projects.team.addTitle" size="lg">
        <p v-if="roleSlots.length" class="mb-4 text-sm text-ink-muted">
            {{ t.projects.team.requiredRoles }} <b>{{ categoryCode }}</b
            >:
        </p>

        <ul
            v-if="memberErrors.length"
            class="mb-3 list-inside list-disc rounded-control bg-danger-soft px-3 py-2 text-sm text-danger-ink ring-1 ring-danger/20"
        >
            <li v-for="(message, index) in memberErrors" :key="index">{{ message }}</li>
        </ul>

        <div class="space-y-2">
            <div class="hidden grid-cols-12 gap-3 px-1 text-xs font-semibold uppercase text-ink-subtle sm:grid">
                <div class="col-span-3">{{ t.projects.team.roleInProject }}</div>
                <div class="col-span-9">{{ t.projects.team.name }}</div>
            </div>

            <div v-for="(row, index) in rows" :key="row.key" class="grid grid-cols-12 items-center gap-3">
                <div class="col-span-12 sm:col-span-3">
                    <label class="mb-1 block text-xs font-medium text-ink-label sm:hidden">
                        {{ t.projects.team.roleInProject }}
                    </label>
                    <UiInput v-model="row.role" :readonly="row.locked" :placeholder="t.projects.team.rolePlaceholder" />
                </div>

                <div :class="!row.locked && rows.length > 1 ? 'col-span-10 sm:col-span-8' : 'col-span-12 sm:col-span-9'">
                    <label class="mb-1 block text-xs font-medium text-ink-label sm:hidden">
                        {{ t.projects.team.name }}
                    </label>
                    <UiCombobox
                        v-model="row.user_id"
                        :search-url="userSearchUrl"
                        value-key="id"
                        :placeholder="t.projects.team.namePlaceholder"
                    />
                </div>

                <div v-if="!row.locked && rows.length > 1" class="col-span-2 flex justify-end sm:col-span-1">
                    <button
                        type="button"
                        class="rounded p-1.5 text-danger transition-colors hover:bg-danger-soft"
                        :aria-label="t.projects.team.removeRow"
                        @click="rows.splice(index, 1)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <UiButton size="sm" variant="secondary" class="mt-3" @click="addRow">
            {{ t.projects.team.addRow }}
        </UiButton>

        <p v-if="addError" class="mt-2 text-sm text-danger">{{ addError }}</p>

        <template #footer>
            <UiButton variant="secondary" @click="adding = false">{{ t.common.close }}</UiButton>
            <UiButton :loading="submitting" @click="submitAdd">
                {{ t.projects.team.addMemberButton }}
            </UiButton>
        </template>
    </UiModal>
</template>
