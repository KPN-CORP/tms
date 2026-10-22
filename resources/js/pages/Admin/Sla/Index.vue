<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    UiBadge,
    UiButton,
    UiCard,
    UiField,
    UiInput,
    UiListFooter,
    UiPageHeader,
    UiSelect,
    UiTable,
    UiTd,
    UiTh,
} from '@/components/ui';
import { useClientTable } from '@/composables/useClientTable';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import type { SelectOption } from '@/types/models';

interface Setting {
    id: number;
    approval_type: string;
    approval_label: string;
    status: string | null;
    status_label: string | null;
    days: number;
    is_active: boolean;
    urls: { edit: string; toggle: string; destroy: string };
}

const props = defineProps<{
    settings: Setting[];
    typeOptions: SelectOption[];
    statusOptions: SelectOption[];
    editing: { id: number; approval_type: string; status: string | null; days: number } | null;
    urls: { index: string; store: string; update: string | null };
}>();

const { confirm } = useConfirm();
const { t } = useLocale();
const { approvalType, ideaStatus } = useLabels();

/**
 * Diurutkan dari teks yang TERLIHAT, bukan kodenya: kolomnya menampilkan label
 * terjemahan, jadi urutannya ikut berubah saat bahasanya diganti.
 */
const {
    rows: pageSettings,
    sort,
    dir,
    perPage,
    meta,
    sortBy,
    setPage,
} = useClientTable({
    rows: () => props.settings,
    columns: {
        approval_type: (setting) => approvalType(setting.approval_type, setting.approval_label),
        status: (setting) => (setting.status ? ideaStatus(setting.status, setting.status_label) : null),
        days: (setting) => setting.days,
        is_active: (setting) => setting.is_active,
    },
    defaultSort: 'approval_type',
});

/** Pilihan datang sebagai kode + label Inggris; kodenya yang diterjemahkan. */
const types = computed(() =>
    props.typeOptions.map((option) => ({
        ...option,
        label: approvalType(String(option.value), option.label),
    })),
);

// 'submitted' / 'review' -- kode status idea, kamusnya sudah ada.
const statuses = computed(() =>
    props.statusOptions.map((option) => ({
        ...option,
        label: ideaStatus(String(option.value), option.label),
    })),
);

const form = useForm<{ approval_type: string | null; status: string | null; days: string }>({
    approval_type: props.editing?.approval_type ?? null,
    status: props.editing?.status ?? null,
    days: props.editing ? String(props.editing.days) : '',
});

watch(
    () => props.editing,
    (editing) => {
        form.defaults({
            approval_type: editing?.approval_type ?? null,
            status: editing?.status ?? null,
            days: editing ? String(editing.days) : '',
        });
        form.reset();
    },
);

const submitting = ref(false);

const submit = (): void => {
    submitting.value = true;
    const done = { preserveScroll: true, onFinish: () => (submitting.value = false) };

    if (props.editing && props.urls.update) {
        form.put(props.urls.update, done);
    } else {
        form.post(props.urls.store, done);
    }
};

const toggle = (setting: Setting): void => {
    router.post(setting.urls.toggle, {}, { preserveScroll: true });
};

const remove = async (setting: Setting): Promise<void> => {
    const type = approvalType(setting.approval_type, setting.approval_label);

    const label = setting.status
        ? `${type} — ${ideaStatus(setting.status, setting.status_label)}`
        : type;

    const ok = await confirm({
        title: t.value.admin.sla.deleteTitle,
        message: tr(t.value.admin.sla.deleteMessage, { label }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(setting.urls.destroy, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t.admin.sla.head" />

    <UiPageHeader :title="t.admin.sla.head" :description="t.admin.sla.description" />

    <div class="space-y-5">
        <UiCard flush :title="t.admin.sla.listTitle">
            <UiTable
                :empty="settings.length === 0"
                :empty-title="t.admin.sla.emptyTitle"
                :empty-message="t.admin.sla.emptyMessage"
            >
                <template #head>
                    <UiTh sort-key="approval_type" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.sla.approvalType }}
                    </UiTh>
                    <UiTh sort-key="status" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.fields.status }}
                    </UiTh>
                    <UiTh sort-key="days" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.sla.reviewLimit }}
                    </UiTh>
                    <UiTh sort-key="is_active" :sort="sort" :dir="dir" @sort="sortBy">
                        {{ t.admin.sla.active }}
                    </UiTh>
                    <UiTh align="right">{{ t.admin.category.actions }}</UiTh>
                </template>

                <tr v-for="setting in pageSettings" :key="setting.id" class="transition-colors hover:bg-surface-muted">
                    <UiTd strong>{{ approvalType(setting.approval_type, setting.approval_label) }}</UiTd>
                    <UiTd>
                        {{ setting.status ? ideaStatus(setting.status, setting.status_label) : '—' }}
                    </UiTd>
                    <UiTd>{{ setting.days }}</UiTd>

                    <UiTd>
                        <UiBadge :tone="setting.is_active ? 'success' : 'neutral'" dot>
                            {{ setting.is_active ? t.admin.sla.active : t.admin.sla.inactive }}
                        </UiBadge>
                    </UiTd>

                    <UiTd align="right">
                        <div class="flex items-center justify-end gap-2">
                            <UiButton size="sm" variant="subtle" @click="toggle(setting)">
                                {{ setting.is_active ? t.admin.sla.deactivate : t.admin.sla.activate }}
                            </UiButton>
                            <UiButton size="sm" variant="secondary" :href="setting.urls.edit">
                                {{ t.common.edit }}
                            </UiButton>
                            <UiButton size="sm" variant="danger" @click="remove(setting)">
                                {{ t.common.delete }}
                            </UiButton>
                        </div>
                    </UiTd>
                </tr>
            </UiTable>

            <UiListFooter v-model:per-page="perPage" :meta="meta" @page="setPage" />
        </UiCard>

        <UiCard :title="editing ? t.admin.sla.editTitle : t.admin.sla.addTitle">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <UiField :label="t.admin.sla.approvalType" required :error="form.errors.approval_type">
                        <UiSelect
                            v-model="form.approval_type"
                            :options="types"
                            :placeholder="t.admin.sla.selectType"
                        />
                    </UiField>

                    <UiField :label="t.fields.status" required :error="form.errors.status">
                        <UiSelect
                            v-model="form.status"
                            :options="statuses"
                            :placeholder="t.admin.sla.selectStatus"
                        />
                    </UiField>

                    <UiField :label="t.admin.sla.reviewLimit" required :error="form.errors.days">
                        <UiInput v-model="form.days" type="number" min="1" max="365" />
                    </UiField>
                </div>

                <div class="flex justify-end gap-3">
                    <UiButton v-if="editing" variant="secondary" :href="urls.index">
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
