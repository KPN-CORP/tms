<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import type { IdeaAttachment } from './types';

defineProps<{
    attachments: IdeaAttachment[];
    ideaId: number;
    /** Sembunyikan tombol hapus di halaman detail yang read-only. */
    readonly?: boolean;
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

const remove = async (attachment: IdeaAttachment): Promise<void> => {
    const ok = await confirm({
        title: t.value.ideas.deleteAttachmentTitle,
        message: tr(t.value.ideas.deleteAttachmentMessage, { name: attachment.file_name }),
        confirmLabel: t.value.common.yesDelete,
        tone: 'danger',
    });

    if (ok) {
        router.delete(attachment.urls.destroy, { preserveScroll: true });
    }
};
</script>

<template>
    <ul class="space-y-1.5">
        <li
            v-for="attachment in attachments"
            :key="attachment.id"
            class="flex items-center justify-between gap-3 rounded-control border border-line px-3 py-2 text-sm"
        >
            <!-- Unduhan menembak controller langsung, jadi tetap <a> biasa, bukan <Link>. -->
            <a
                :href="attachment.urls.download"
                class="truncate font-medium text-brand underline-offset-2 hover:underline"
            >
                {{ attachment.file_name }}
            </a>

            <div class="flex shrink-0 items-center gap-3 text-xs">
                <a :href="attachment.urls.view" target="_blank" rel="noopener" class="text-ink-subtle hover:text-ink">
                    {{ t.common.view }}
                </a>

                <button
                    v-if="!readonly"
                    type="button"
                    class="font-medium text-danger hover:underline"
                    @click="remove(attachment)"
                >
                    {{ t.common.delete }}
                </button>
            </div>
        </li>
    </ul>
</template>
