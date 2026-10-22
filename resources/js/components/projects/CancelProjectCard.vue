<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { UiButton, UiCard, UiField, UiModal, UiTextarea } from '@/components/ui';
import { useLocale } from '@/composables/useLocale';

/**
 * Pembatalan project.
 *
 * Alasannya WAJIB dan tidak punya kolom sendiri: ProjectController menyimpannya
 * di project_status_logs.remarks dengan awalan "Cancelled: " (lihat
 * Project::cancellation()). Karena itu dialog ini selalu meminta teks, bukan
 * sekadar konfirmasi ya/tidak.
 */
const props = defineProps<{ action: string }>();

const { t } = useLocale();

const open = ref(false);

const form = useForm({ reason: '' });

const submit = (): void => {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <UiCard :title="t.projects.cancelCard.title">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-ink-muted">{{ t.projects.cancelCard.body }}</p>

            <UiButton variant="danger" class="shrink-0" @click="open = true">
                {{ t.projects.cancelCard.button }}
            </UiButton>
        </div>
    </UiCard>

    <UiModal
        v-model="open"
        :title="t.projects.cancelCard.modalTitle"
        :description="t.projects.cancelCard.modalDescription"
        size="sm"
        :close-on-backdrop="false"
    >
        <UiField :label="t.fields.reason" required :error="form.errors.reason">
            <UiTextarea
                v-model="form.reason"
                :rows="4"
                :maxlength="2000"
                :placeholder="t.projects.cancelCard.reasonPlaceholder"
            />
        </UiField>

        <template #footer>
            <UiButton variant="secondary" @click="open = false">
                {{ t.projects.cancelCard.keep }}
            </UiButton>
            <UiButton
                variant="danger"
                :loading="form.processing"
                :disabled="form.reason.trim() === ''"
                @click="submit"
            >
                {{ t.projects.cancelCard.confirm }}
            </UiButton>
        </template>
    </UiModal>
</template>
