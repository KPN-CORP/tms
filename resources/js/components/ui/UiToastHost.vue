<script setup lang="ts">
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast';
import { useLocale } from '@/composables/useLocale';
import type { SharedProps } from '@/types/inertia';

/**
 * Menampilkan prop `flash` dari server sebagai notifikasi, lalu menyimpannya di
 * store sendiri supaya halaman juga bisa memunculkan pesan sisi klien.
 */
const page = usePage<SharedProps>();
const { toasts, push, dismiss } = useToast();
const { t } = useLocale();

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) {
            return;
        }

        if (flash.success) {
            push('success', flash.success);
        }

        if (flash.error) {
            push('error', flash.error);
        }

        if (flash.status) {
            push('info', flash.status);
        }
    },
    { immediate: true, deep: true },
);

const TONES = {
    success: { ring: 'border-success/30', bar: 'bg-success', icon: 'text-success' },
    error: { ring: 'border-danger/30', bar: 'bg-danger', icon: 'text-danger' },
    info: { ring: 'border-info/30', bar: 'bg-info', icon: 'text-info' },
};
</script>

<template>
    <!-- aria-live: pembaca layar mengumumkan pesan baru tanpa memindah fokus. -->
    <div
        class="pointer-events-none fixed right-4 top-4 z-[60] flex w-full max-w-sm flex-col gap-2"
        role="region"
        aria-live="polite"
        :aria-label="t.controls.notifications"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="pointer-events-auto flex animate-slide-in-right items-start gap-3 overflow-hidden rounded-card border bg-surface p-3 pl-0 shadow-raised"
            :class="TONES[toast.tone].ring"
        >
            <span class="h-full w-1 shrink-0 self-stretch" :class="TONES[toast.tone].bar" />

            <svg
                class="mt-0.5 h-5 w-5 shrink-0"
                :class="TONES[toast.tone].icon"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path v-if="toast.tone === 'success'" stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                <path v-else-if="toast.tone === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z" />
            </svg>

            <p class="min-w-0 flex-1 py-0.5 text-sm text-ink">{{ toast.message }}</p>

            <button
                type="button"
                class="shrink-0 rounded p-1 text-ink-subtle transition-colors hover:bg-surface-muted hover:text-ink"
                :aria-label="t.controls.dismissNotification"
                @click="dismiss(toast.id)"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        </div>
    </div>
</template>
