<script setup lang="ts">
import { computed } from 'vue';
import UiButton from './UiButton.vue';
import UiModal from './UiModal.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useLocale } from '@/composables/useLocale';

/**
 * Dipasang sekali di AppLayout. Halaman tidak merender ini sendiri -- cukup
 * `await confirm({ ... })` lewat composable-nya.
 */
const { pending, accept, cancel } = useConfirm();
const { t } = useLocale();

const open = computed({
    get: () => pending.value !== null,
    set: (value: boolean) => {
        if (!value) {
            cancel();
        }
    },
});

const ICON_TONES = {
    brand: 'bg-brand-soft text-brand',
    danger: 'bg-danger-soft text-danger',
    warning: 'bg-warning-soft text-warning-ink',
};
</script>

<template>
    <UiModal v-model="open" size="sm" :close-on-backdrop="false">
        <div v-if="pending" class="flex items-start gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                :class="ICON_TONES[pending.tone]"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                </svg>
            </div>

            <div class="min-w-0 pt-0.5">
                <h2 class="text-base font-semibold text-ink">{{ pending.title ?? t.confirm.title }}</h2>
                <p class="mt-1 text-sm text-ink-muted">{{ pending.message }}</p>
            </div>
        </div>

        <template #footer>
            <UiButton variant="secondary" @click="cancel">
                {{ pending?.cancelLabel ?? t.confirm.cancel }}
            </UiButton>
            <UiButton
                autofocus
                :variant="pending?.tone === 'danger' ? 'danger' : 'primary'"
                @click="accept"
            >
                {{ pending?.confirmLabel ?? t.confirm.accept }}
            </UiButton>
        </template>
    </UiModal>
</template>
