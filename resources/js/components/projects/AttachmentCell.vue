<script setup lang="ts">
import { ref } from 'vue';
import { UiButton, UiModal } from '@/components/ui';
import { tr, useLocale } from '@/composables/useLocale';
import type { RowAttachment } from './types';

/**
 * Sel lampiran pada tabel: satu ikon dokumen.
 *
 * Satu berkas langsung membuka tab baru; lebih dari satu memunculkan daftar,
 * supaya kolomnya tetap sempit berapa pun jumlah berkasnya.
 */
defineProps<{
    attachments: RowAttachment[];
    /** Judul kecil di dialog daftar — nama activity atau item budget. */
    caption?: string | null;
}>();

const { t } = useLocale();

const open = ref(false);
</script>

<template>
    <span v-if="!attachments.length" class="text-ink-subtle">-</span>

    <a
        v-else-if="attachments.length === 1 && attachments[0]"
        :href="attachments[0].view_url"
        target="_blank"
        rel="noopener"
        :title="attachments[0].file_name"
        class="inline-flex items-center gap-1 text-brand hover:underline"
    >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4a1 1 0 0 0 1 1h4M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7l-4-4Z" />
        </svg>
        <span class="sr-only">{{ attachments[0].file_name }}</span>
    </a>

    <template v-else>
        <button
            type="button"
            class="inline-flex items-center gap-1 text-brand hover:underline"
            :title="tr(t.projects.attachments.fileCount, { count: attachments.length })"
            @click="open = true"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4a1 1 0 0 0 1 1h4M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7l-4-4Z" />
            </svg>
            <span class="font-semibold">{{ attachments.length }}</span>
        </button>

        <UiModal
            v-model="open"
            :title="t.projects.attachments.dialogTitle"
            :description="caption ?? undefined"
            size="sm"
        >
            <ul class="space-y-2">
                <li v-for="attachment in attachments" :key="attachment.id">
                    <a
                        :href="attachment.view_url"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-2 rounded-control border border-line px-3 py-2 transition-colors hover:bg-surface-muted"
                    >
                        <svg class="h-4 w-4 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4a1 1 0 0 0 1 1h4M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7l-4-4Z" />
                        </svg>
                        <span class="truncate text-sm text-ink-muted">{{ attachment.file_name }}</span>
                    </a>
                </li>
            </ul>

            <template #footer>
                <UiButton variant="secondary" @click="open = false">{{ t.common.close }}</UiButton>
            </template>
        </UiModal>
    </template>
</template>
