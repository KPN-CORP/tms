<script setup lang="ts">
import { useSlots } from 'vue';
import { cx } from '@/lib/format';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        /** Matikan padding isi untuk kartu yang memuat tabel penuh lebar. */
        flush?: boolean;
    }>(),
    { flush: false },
);

const slots = useSlots();
</script>

<template>
    <section class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
        <header
            v-if="title || slots.header || slots.actions"
            class="flex items-start justify-between gap-4 border-b border-line bg-surface-muted/70 px-5 py-3.5"
        >
            <div class="min-w-0">
                <slot name="header">
                    <h2 class="truncate text-sm font-semibold text-ink">{{ title }}</h2>
                    <p v-if="description" class="mt-0.5 text-xs text-ink-subtle">{{ description }}</p>
                </slot>
            </div>

            <div v-if="slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <div :class="cx(!flush && 'p-5')">
            <slot />
        </div>

        <footer v-if="slots.footer" class="border-t border-line bg-surface-muted px-5 py-3">
            <slot name="footer" />
        </footer>
    </section>
</template>
