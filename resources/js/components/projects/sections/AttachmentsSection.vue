<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { UiButton, UiFileUpload } from '@/components/ui';
import CollapsibleSection from '../CollapsibleSection.vue';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { reportFields } from '@/composables/useReportContext';
import type { ProjectAttachmentRow } from '../types';

const props = defineProps<{
    attachments: ProjectAttachmentRow[];
    canUpload: boolean;
    storeUrl: string;
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

const form = useForm<{ attachments: File[] }>({ attachments: [] });

const upload = (): void => {
    form.transform((data) => ({ ...data, ...reportFields() })).post(props.storeUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const remove = async (attachment: ProjectAttachmentRow): Promise<void> => {
    if (!attachment.destroy_url) {
        return;
    }

    const ok = await confirm({
        title: t.value.projects.attachments.deleteTitle,
        message: tr(t.value.projects.attachments.deleteMessage, { name: attachment.file_name }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(attachment.destroy_url, { data: reportFields(), preserveScroll: true });
    }
};

const sizeLabel = (bytes: number | null): string =>
    bytes ? `${Math.round(bytes / 1024)} KB` : '';

const attachmentError = ref<string>();
</script>

<template>
    <CollapsibleSection :title="t.projects.attachments.title" storage-key="att" anchor="section-attachments">
        <div class="space-y-4">
            <div v-if="attachments.length">
                <div
                    v-for="attachment in attachments"
                    :key="attachment.id"
                    class="flex items-center justify-between border-b border-line py-2 text-sm last:border-0"
                >
                    <!-- Gambar & PDF dibuka di tab baru; tipe lain langsung diunduh. -->
                    <a
                        :href="attachment.view_url ?? attachment.download_url"
                        :target="attachment.view_url ? '_blank' : undefined"
                        :rel="attachment.view_url ? 'noopener' : undefined"
                        class="truncate text-brand hover:underline"
                    >
                        {{ attachment.file_name }}
                    </a>

                    <span class="ml-3 flex shrink-0 items-center gap-3">
                        <a :href="attachment.download_url" class="text-xs text-brand hover:underline">
                            {{ t.common.download }}
                        </a>
                        <span class="text-xs text-ink-subtle">{{ sizeLabel(attachment.file_size) }}</span>
                        <button
                            v-if="attachment.destroy_url"
                            type="button"
                            class="text-xs font-medium text-danger hover:underline"
                            @click="remove(attachment)"
                        >
                            {{ t.common.delete }}
                        </button>
                    </span>
                </div>
            </div>

            <p v-else class="text-sm text-ink-subtle">{{ t.projects.attachments.empty }}</p>

            <div v-if="canUpload">
                <UiFileUpload v-model="form.attachments" :error="attachmentError" />

                <div v-if="form.attachments.length" class="mt-3 flex justify-end">
                    <UiButton :loading="form.processing" @click="upload">
                        {{ t.projects.attachments.upload }}
                    </UiButton>
                </div>
            </div>
        </div>
    </CollapsibleSection>
</template>
