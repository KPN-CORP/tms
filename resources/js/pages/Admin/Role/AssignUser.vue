<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { UiButton, UiCard, UiField, UiMultiCombobox, UiPageHeader } from '@/components/ui';
import { tr, useLocale } from '@/composables/useLocale';
import type { SelectOption } from '@/types/models';

const props = defineProps<{
    role: { id: number; name: string };
    assigned: SelectOption[];
    urls: { store: string; index: string; userSearch: string };
}>();

/**
 * Daftar user TIDAK dimuat seluruhnya (hcis punya ribuan) — yang terkirim ke
 * halaman hanya yang sudah terpilih; sisanya dicari ke server saat mengetik.
 */
const { t } = useLocale();

const form = useForm<{ users: Array<string | number> }>({
    users: props.assigned.map((option) => option.value),
});

const submit = (): void => {
    form.post(props.urls.store, { preserveScroll: true });
};
</script>

<template>
    <Head :title="tr(t.admin.roles.assignHead, { name: role.name })" />

    <UiPageHeader
        :title="tr(t.admin.roles.assignTitle, { name: role.name })"
        :description="t.admin.roles.assignDescription"
        :back-href="urls.index"
    />

    <form @submit.prevent="submit">
        <UiCard>
            <UiField :label="t.admin.roles.users" :error="form.errors.users">
                <UiMultiCombobox
                    v-model="form.users"
                    :search-url="urls.userSearch"
                    value-key="id"
                    :initial-options="assigned"
                    :placeholder="t.admin.roles.assignSearch"
                />
            </UiField>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <UiButton variant="secondary" :href="urls.index">{{ t.common.cancel }}</UiButton>
                    <UiButton type="submit" :loading="form.processing">{{ t.common.save }}</UiButton>
                </div>
            </template>
        </UiCard>
    </form>
</template>
