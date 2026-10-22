<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    UiBadge,
    UiButton,
    UiCard,
    UiDateTime,
    UiEmptyState,
    UiField,
    UiFileUpload,
    UiInput,
    UiPageHeader,
    UiTextarea,
} from '@/components/ui';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';

interface Access {
    employee_can_view: boolean;
    employee_can_download: boolean;
    committee_can_view: boolean;
    committee_can_download: boolean;
}

interface Guideline {
    id: number;
    title: string;
    description: string | null;
    file_name: string;
    is_active: boolean;
    uploader: string | null;
    created_at: string | null;
    access: Access;
    can_view: boolean;
    can_download: boolean;
    urls: { view: string; download: string; toggle: string; access: string; destroy: string };
}

const props = defineProps<{
    guidelines: Guideline[];
    canManage: boolean;
    urls: { store: string };
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

const active = computed(() => props.guidelines.filter((g) => g.is_active));
const inactive = computed(() => props.guidelines.filter((g) => !g.is_active));

/* ---- Upload ------------------------------------------------------------ */

const form = useForm<{
    title: string;
    description: string;
    file: File[];
    employee_can_view: boolean;
    employee_can_download: boolean;
    committee_can_view: boolean;
    committee_can_download: boolean;
}>({
    title: '',
    description: '',
    file: [],
    employee_can_view: true,
    employee_can_download: false,
    committee_can_view: false,
    committee_can_download: false,
});

const upload = (): void => {
    // Server menerima satu berkas pada field `file`, bukan array.
    form.transform((data) => ({ ...data, file: data.file[0] ?? null })).post(props.urls.store, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

/* ---- Pengelolaan ------------------------------------------------------- */

const busy = ref<number | null>(null);

const toggle = (guideline: Guideline): void => {
    busy.value = guideline.id;
    router.post(guideline.urls.toggle, {}, { preserveScroll: true, onFinish: () => (busy.value = null) });
};

const saveAccess = (guideline: Guideline, key: keyof Access, value: boolean): void => {
    router.post(
        guideline.urls.access,
        { ...guideline.access, [key]: value },
        { preserveScroll: true },
    );
};

const remove = async (guideline: Guideline): Promise<void> => {
    const ok = await confirm({
        title: t.value.guidelines.deleteTitle,
        message: tr(t.value.guidelines.deleteMessage, { name: guideline.title }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(guideline.urls.destroy, { preserveScroll: true });
    }
};

const ACCESS_FIELDS = computed<Array<{ key: keyof Access; group: string; label: string }>>(() => [
    { key: 'employee_can_view', group: t.value.guidelines.employee, label: t.value.common.view },
    { key: 'employee_can_download', group: t.value.guidelines.employee, label: t.value.common.download },
    { key: 'committee_can_view', group: t.value.guidelines.committee, label: t.value.common.view },
    { key: 'committee_can_download', group: t.value.guidelines.committee, label: t.value.common.download },
]);
</script>

<template>
    <Head :title="t.guidelines.head" />

    <UiPageHeader :title="t.guidelines.head" :description="t.guidelines.description" />

    <div class="space-y-5">
        <template
            v-for="group in [
                { key: 'active', label: t.guidelines.active, items: active },
                { key: 'inactive', label: t.guidelines.inactive, items: inactive },
            ]"
            :key="group.key"
        >
            <section v-if="group.items.length || (group.key === 'active' && !guidelines.length)">
                <h2 v-if="canManage" class="mb-2 text-sm font-semibold uppercase tracking-wide text-ink-subtle">
                    {{ group.label }}
                </h2>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <UiCard
                        v-for="guideline in group.items"
                        :key="guideline.id"
                        :class="guideline.is_active ? '' : 'opacity-70'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-ink">{{ guideline.title }}</h3>
                                <p v-if="guideline.description" class="mt-0.5 text-sm text-ink-muted">
                                    {{ guideline.description }}
                                </p>
                                <p class="mt-1 truncate text-xs text-ink-subtle">
                                    {{ guideline.file_name }}
                                    <template v-if="guideline.uploader"> · {{ guideline.uploader }}</template>
                                    · <UiDateTime :value="guideline.created_at" mode="date" />
                                </p>
                            </div>

                            <UiBadge :tone="guideline.is_active ? 'success' : 'neutral'" class="shrink-0" dot>
                                {{ guideline.is_active ? t.guidelines.active : t.guidelines.inactive }}
                            </UiBadge>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <UiButton
                                v-if="guideline.can_view"
                                size="sm"
                                variant="secondary"
                                :href="guideline.urls.view"
                                external
                            >
                                {{ t.common.view }}
                            </UiButton>

                            <UiButton
                                v-if="guideline.can_download"
                                size="sm"
                                variant="secondary"
                                :href="guideline.urls.download"
                                external
                            >
                                {{ t.common.download }}
                            </UiButton>

                            <template v-if="canManage">
                                <UiButton
                                    size="sm"
                                    variant="subtle"
                                    :loading="busy === guideline.id"
                                    @click="toggle(guideline)"
                                >
                                    {{ guideline.is_active ? t.guidelines.deactivate : t.guidelines.activate }}
                                </UiButton>

                                <UiButton size="sm" variant="danger" @click="remove(guideline)">
                                    {{ t.common.delete }}
                                </UiButton>
                            </template>
                        </div>

                        <!-- Hak akses per peran; perubahannya langsung disimpan. -->
                        <div v-if="canManage" class="mt-3 border-t border-line pt-3">
                            <p class="mb-1.5 text-xs font-medium uppercase tracking-wider text-ink-subtle">
                                {{ t.guidelines.access }}
                            </p>

                            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                                <label
                                    v-for="field in ACCESS_FIELDS"
                                    :key="field.key"
                                    class="flex cursor-pointer items-center gap-1.5 text-ink-muted"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-line-strong text-brand focus:ring-brand/40"
                                        :checked="guideline.access[field.key]"
                                        @change="saveAccess(guideline, field.key, ($event.target as HTMLInputElement).checked)"
                                    />
                                    <span class="text-xs text-ink-subtle">{{ field.group }}</span>
                                    {{ field.label }}
                                </label>
                            </div>
                        </div>
                    </UiCard>
                </div>

                <UiEmptyState
                    v-if="!guidelines.length"
                    :title="t.guidelines.emptyTitle"
                    :message="canManage ? t.guidelines.emptyManage : t.guidelines.emptyViewer"
                />
            </section>
        </template>

        <UiCard v-if="canManage" :title="t.guidelines.uploadTitle">
            <form class="space-y-4" @submit.prevent="upload">
                <UiField :label="t.guidelines.title" required :error="form.errors.title">
                    <UiInput v-model="form.title" />
                </UiField>

                <UiField :label="t.fields.description" :error="form.errors.description">
                    <UiTextarea v-model="form.description" :rows="2" />
                </UiField>

                <UiField :label="t.guidelines.file" required :error="form.errors.file">
                    <UiFileUpload v-model="form.file" />
                </UiField>

                <div>
                    <p class="mb-1.5 text-xs font-medium text-ink-label">{{ t.guidelines.access }}</p>

                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        <label class="flex cursor-pointer items-center gap-1.5 text-ink-muted">
                            <input v-model="form.employee_can_view" type="checkbox" class="rounded border-line-strong text-brand focus:ring-brand/40" />
                            <span class="text-xs text-ink-subtle">{{ t.guidelines.employee }}</span>
                            {{ t.common.view }}
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5 text-ink-muted">
                            <input v-model="form.employee_can_download" type="checkbox" class="rounded border-line-strong text-brand focus:ring-brand/40" />
                            <span class="text-xs text-ink-subtle">{{ t.guidelines.employee }}</span>
                            {{ t.common.download }}
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5 text-ink-muted">
                            <input v-model="form.committee_can_view" type="checkbox" class="rounded border-line-strong text-brand focus:ring-brand/40" />
                            <span class="text-xs text-ink-subtle">{{ t.guidelines.committee }}</span>
                            {{ t.common.view }}
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5 text-ink-muted">
                            <input v-model="form.committee_can_download" type="checkbox" class="rounded border-line-strong text-brand focus:ring-brand/40" />
                            <span class="text-xs text-ink-subtle">{{ t.guidelines.committee }}</span>
                            {{ t.common.download }}
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <UiButton type="submit" :loading="form.processing" :disabled="!form.file.length">
                        {{ t.guidelines.upload }}
                    </UiButton>
                </div>
            </form>
        </UiCard>
    </div>
</template>
