<script setup lang="ts">
import { computed } from 'vue';
import AppLink from './AppLink.vue';
import { cx, number } from '@/lib/format';
import type { Tone } from '@/lib/status';

const props = withDefaults(
    defineProps<{
        label: string;
        value: number | string | null;
        /** Keterangan kecil di bawah angka. */
        caption?: string;
        tone?: Tone;
        /** Kartu jadi tautan ke Report dengan metrik yang sama. */
        href?: string;
        /** Tujuan masih halaman Blade — lihat AppLink. */
        external?: boolean;
    }>(),
    { tone: 'neutral', external: false },
);

const ACCENTS: Record<Tone, string> = {
    neutral: 'text-neutral-ink',
    info: 'text-info-ink',
    success: 'text-success-ink',
    warning: 'text-warning-ink',
    danger: 'text-danger-ink',
    brand: 'text-brand-ink',
    accent: 'text-brand-ink',
};

const BARS: Record<Tone, string> = {
    neutral: 'bg-neutral',
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    brand: 'bg-brand',
    accent: 'bg-brand',
};

const display = computed(() =>
    typeof props.value === 'number' ? number(props.value, '0') : (props.value ?? '-'),
);
</script>

<template>
    <component
        :is="href ? AppLink : 'div'"
        :href="href"
        :external="href ? external : undefined"
        :class="
            cx(
                'group relative block overflow-hidden rounded-card border border-line bg-surface p-4 shadow-card',
                href && 'transition-shadow hover:shadow-raised focus-visible:shadow-raised',
            )
        "
    >
        <span class="absolute inset-y-0 left-0 w-1" :class="BARS[tone]" aria-hidden="true" />

        <p class="text-xs font-semibold uppercase tracking-wide text-ink-subtle">{{ label }}</p>

        <p class="mt-2 text-2xl font-bold tabular-nums" :class="ACCENTS[tone]">{{ display }}</p>

        <p v-if="caption" class="mt-1 text-xs text-ink-subtle">{{ caption }}</p>

        <svg
            v-if="href"
            class="absolute right-3 top-3 h-4 w-4 text-ink-subtle opacity-0 transition-opacity group-hover:opacity-100"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path fill-rule="evenodd" d="M7.21 5.23a.75.75 0 0 1 1.06-.02l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 1 1-1.04-1.08L11.168 10 7.19 6.29a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
        </svg>
    </component>
</template>
