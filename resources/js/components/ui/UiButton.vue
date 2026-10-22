<script setup lang="ts">
import { computed } from 'vue';
import AppLink from './AppLink.vue';
import { cx } from '@/lib/format';
import UiSpinner from './UiSpinner.vue';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success' | 'warning' | 'subtle';
type Size = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        variant?: Variant;
        size?: Size;
        /** Render sebagai tautan (navigasi) alih-alih <button>. */
        href?: string;
        /**
         * Halaman tujuan masih dirender Blade, jadi harus dibuka sebagai
         * navigasi biasa — <Link> Inertia akan gagal di respons non-Inertia.
         * Lihat App\Support\Navigation\InertiaRoutes.
         */
        external?: boolean;
        /** Metode untuk href; `post`/`delete` otomatis pakai Link Inertia. */
        method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
        loading?: boolean;
        block?: boolean;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        type: 'button',
        method: 'get',
    },
);

const VARIANTS: Record<Variant, string> = {
    primary: 'bg-brand text-white hover:bg-brand-strong',
    secondary: 'border border-line bg-surface text-ink-muted hover:bg-surface-muted',
    ghost: 'text-ink-muted hover:bg-surface-muted hover:text-ink',
    danger: 'bg-danger text-white hover:brightness-95',
    success: 'bg-success text-white hover:brightness-95',
    warning: 'bg-warning text-white hover:brightness-95',
    subtle: 'border border-transparent bg-surface-muted text-ink-muted hover:bg-line/60 hover:text-ink',
};

const SIZES: Record<Size, string> = {
    sm: 'h-8 gap-1.5 px-3 text-xs',
    md: 'h-control gap-2 px-4 text-sm',
    lg: 'h-11 gap-2 px-6 text-sm',
};

const classes = computed(() =>
    cx(
        'inline-flex items-center justify-center rounded-control font-semibold whitespace-nowrap',
        'transition-colors duration-150 select-none',
        'disabled:opacity-50 disabled:pointer-events-none',
        VARIANTS[props.variant],
        SIZES[props.size],
        props.block && 'w-full',
    ),
);

const isInactive = computed(() => props.disabled || props.loading);
</script>

<template>
    <AppLink v-if="href && !isInactive" :href="href" :external="external" :method="method" :class="classes">
        <slot name="icon" />
        <slot />
    </AppLink>

    <button v-else :type="type" :disabled="isInactive" :class="classes">
        <UiSpinner v-if="loading" class="h-4 w-4" />
        <slot v-else name="icon" />
        <slot />
    </button>
</template>
