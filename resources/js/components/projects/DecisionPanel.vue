<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { UiButton, UiTextarea } from '@/components/ui';
import { useConfirm } from '@/composables/useConfirm';
import { useLocale } from '@/composables/useLocale';
import { reportFields } from '@/composables/useReportContext';

export interface DecisionAction {
    key: string;
    label: string;
    url: string;
    variant: 'success' | 'warning' | 'danger';
    /** Approve boleh tanpa catatan; Revision Required & Reject wajib. */
    requiresNote: boolean;
    confirmTitle: string;
    confirmMessage: string;
    confirmLabel: string;
}

/**
 * Panel keputusan yang dipakai Sponsor, committee, dan change request.
 *
 * Aturan catatan ditegakkan juga di server — pengecekan di sini hanya menahan
 * submit lebih awal supaya pengguna tidak kehilangan apa yang sudah diketik.
 */
const props = defineProps<{ actions: DecisionAction[]; placeholder: string }>();

const form = useForm<{ note: string }>({ note: '' });

const noteError = ref(false);
const pendingKey = ref<string | null>(null);
const noteField = ref<HTMLElement | null>(null);

const { confirm } = useConfirm();
const { t } = useLocale();

const run = async (action: DecisionAction): Promise<void> => {
    if (action.requiresNote && form.note.trim() === '') {
        noteError.value = true;
        noteField.value?.querySelector('textarea')?.focus();
        return;
    }

    noteError.value = false;

    const ok = await confirm({
        title: action.confirmTitle,
        message: action.confirmMessage,
        confirmLabel: action.confirmLabel,
        tone: action.variant === 'danger' ? 'danger' : action.variant === 'warning' ? 'warning' : 'brand',
    });

    if (!ok) {
        return;
    }

    pendingKey.value = action.key;
    form.transform((data) => ({ ...data, ...reportFields() })).post(action.url, {
        preserveScroll: true,
        onFinish: () => (pendingKey.value = null),
    });
};

const VARIANTS = { success: 'success', warning: 'warning', danger: 'danger' } as const;
</script>

<template>
    <div>
        <div ref="noteField" class="mb-3">
            <UiTextarea
                v-model="form.note"
                :rows="2"
                :placeholder="placeholder"
                @update:model-value="noteError = false"
            />
            <p v-if="noteError" class="mt-1 text-xs text-danger">
                {{ t.projects.decisionNoteRequired }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <UiButton
                v-for="action in props.actions"
                :key="action.key"
                :variant="VARIANTS[action.variant]"
                :loading="pendingKey === action.key"
                :disabled="form.processing"
                @click="run(action)"
            >
                {{ action.label }}
            </UiButton>
        </div>
    </div>
</template>
