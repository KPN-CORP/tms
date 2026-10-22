<script setup lang="ts">
import { computed } from 'vue';
import { cx } from '@/lib/format';
import type { Tone } from '@/lib/status';

const props = withDefaults(
    defineProps<{
        tone?: Tone;
        size?: 'sm' | 'md';
        /** Titik kecil di depan label -- membantu membedakan status tanpa warna saja. */
        dot?: boolean;
    }>(),
    { tone: 'neutral', size: 'sm' },
);

/**
 * Nada memakai token semantik, jadi mode gelap ikut benar tanpa varian kedua.
 * 'accent' sengaja memakai ungu bawaan Tailwind: satu-satunya nada yang tidak
 * punya makna status sendiri (dipakai Completion Review).
 */
const TONES: Record<Tone, string> = {
    neutral: 'bg-neutral-soft text-neutral-ink ring-neutral/20',
    info: 'bg-info-soft text-info-ink ring-info/20',
    success: 'bg-success-soft text-success-ink ring-success/20',
    warning: 'bg-warning-soft text-warning-ink ring-warning/20',
    danger: 'bg-danger-soft text-danger-ink ring-danger/20',
    brand: 'bg-brand-soft text-brand-ink ring-brand/20',
    accent: 'bg-brand/10 text-brand ring-brand/20',
};

const DOTS: Record<Tone, string> = {
    neutral: 'bg-neutral',
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    brand: 'bg-brand',
    accent: 'bg-brand',
};

const classes = computed(() =>
    cx(
        'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full font-medium ring-1 ring-inset',
        props.size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
        TONES[props.tone],
    ),
);
</script>

<template>
    <span :class="classes">
        <span v-if="dot" :class="cx('h-1.5 w-1.5 rounded-full', DOTS[props.tone])" aria-hidden="true" />
        <slot />
    </span>
</template>
