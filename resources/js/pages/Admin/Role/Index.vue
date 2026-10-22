<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    UiBadge,
    UiButton,
    UiCard,
    UiListFooter,
    UiPageHeader,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import { useClientTable } from '@/composables/useClientTable';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';

interface RoleRow {
    id: number;
    name: string;
    permission_count: number;
    permission_labels: Array<{ name: string; label: string }>;
    restrict: { business_units: number; companies: number; locations: number; employees: number };
    users_count: number;
    urls: { edit: string; assign: string; destroy: string };
}

const props = defineProps<{ roles: RoleRow[]; urls: { create: string } }>();

const { confirm } = useConfirm();
const { t } = useLocale();
const { permission } = useLabels();

/** Restrict diurutkan dari total pembatasannya — makin kecil makin terbuka. */
const {
    rows: pageRoles,
    sort,
    dir,
    perPage,
    meta,
    sortBy,
    setPage,
} = useClientTable({
    rows: () => props.roles,
    columns: {
        name: (role) => role.name,
        permissions: (role) => role.permission_count,
        restrict: (role) =>
            role.restrict.business_units +
            role.restrict.companies +
            role.restrict.locations +
            role.restrict.employees,
        users: (role) => role.users_count,
    },
    defaultSort: 'name',
});

const remove = async (role: RoleRow): Promise<void> => {
    const ok = await confirm({
        title: t.value.admin.roles.deleteTitle,
        message: tr(t.value.admin.roles.deleteMessage, { name: role.name }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(role.urls.destroy, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t.admin.roles.head" />

    <UiPageHeader :title="t.admin.roles.head" :description="t.admin.roles.description">
        <template #actions>
            <UiButton :href="urls.create">{{ t.admin.roles.createRole }}</UiButton>
        </template>
    </UiPageHeader>

    <UiCard flush>
        <UiTable
            :empty="roles.length === 0"
            :empty-title="t.admin.roles.emptyTitle"
            :empty-message="t.admin.roles.emptyMessage"
        >
            <template #head>
                <UiTh sort-key="name" :sort="sort" :dir="dir" @sort="sortBy">
                    {{ t.admin.roles.role }}
                </UiTh>
                <UiTh sort-key="permissions" :sort="sort" :dir="dir" @sort="sortBy">
                    {{ t.admin.roles.permissions }}
                </UiTh>
                <UiTh sort-key="restrict" :sort="sort" :dir="dir" @sort="sortBy">
                    {{ t.admin.roles.restrict }}
                </UiTh>
                <UiTh sort-key="users" :sort="sort" :dir="dir" @sort="sortBy">
                    {{ t.admin.roles.users }}
                </UiTh>
                <UiTh align="right">{{ t.admin.roles.actions }}</UiTh>
            </template>

            <tr v-for="role in pageRoles" :key="role.id" class="align-top transition-colors hover:bg-surface-muted">
                <UiTd strong>{{ role.name }}</UiTd>

                <UiTd>
                    <span class="flex flex-wrap items-center gap-1">
                        <UiBadge v-for="entry in role.permission_labels" :key="entry.name" tone="neutral">
                            {{ permission(entry.name, entry.label) }}
                        </UiBadge>
                        <span v-if="role.permission_count > role.permission_labels.length" class="text-xs text-ink-subtle">
                            +{{ role.permission_count - role.permission_labels.length }}
                        </span>
                        <span v-if="!role.permission_count" class="text-ink-subtle">—</span>
                    </span>
                </UiTd>

                <!-- Dimensi kosong berarti "semua" untuk role ini, dan satu role
                     tanpa batas membuka seluruh dimensi itu (RoleScopeService). -->
                <UiTd nowrap>
                    {{ role.restrict.business_units }} / {{ role.restrict.companies }} /
                    {{ role.restrict.locations }} / {{ role.restrict.employees }}
                </UiTd>

                <UiTd>{{ role.users_count }}</UiTd>

                <UiTd align="right">
                    <div class="flex items-center justify-end gap-2">
                        <UiButton size="sm" variant="secondary" :href="role.urls.assign">
                            {{ t.admin.roles.assignUsers }}
                        </UiButton>
                        <UiButton size="sm" variant="secondary" :href="role.urls.edit">
                            {{ t.common.edit }}
                        </UiButton>
                        <UiButton size="sm" variant="danger" @click="remove(role)">
                            {{ t.common.delete }}
                        </UiButton>
                    </div>
                </UiTd>
            </tr>
        </UiTable>

        <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />
    </UiCard>
</template>
