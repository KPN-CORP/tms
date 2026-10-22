<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { reportFields } from '@/composables/useReportContext';
import type { RowAttachment } from './types';

/**
 * Daftar lampiran yang sudah tersimpan pada satu baris, dengan tombol hapus.
 *
 * Di versi Blade tiap tombol hapus perlu form tersendiri di luar dialog karena
 * form tidak boleh bersarang; di sini cukup satu permintaan DELETE.
 */
defineProps<{ attachments: RowAttachment[] }>();

const { confirm } = useConfirm();
const { t } = useLocale();

const remove = async (attachment: RowAttachment): Promise<void> => {
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
</script>

<template>
    <ul v-if="attachments.length" class="space-y-1">
        <li
            v-for="attachment in attachments"
            :key="attachment.id"
            class="flex items-center justify-between gap-2 rounded-control border border-line px-3 py-1.5 text-sm"
        >
            <a
                :href="attachment.view_url"
                target="_blank"
                rel="noopener"
                class="flex min-w-0 items-center gap-2 text-brand hover:underline"
            >
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4a1 1 0 0 0 1 1h4M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7l-4-4Z" />
                </svg>
                <span class="truncate">{{ attachment.file_name }}</span>
            </a>

            <button
                type="button"
                class="shrink-0 text-xs font-medium text-danger hover:underline"
                @click="remove(attachment)"
            >
                {{ t.common.delete }}
            </button>
        </li>
    </ul>
</template>
