<script setup lang="ts">
import { computed } from 'vue';
import { cx } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        align?: 'left' | 'right' | 'center';
        /** Teks tebal untuk kolom identitas (ID, judul). */
        strong?: boolean;
        /** Jangan bungkus baris -- untuk tanggal dan angka. */
        nowrap?: boolean;
        muted?: boolean;
    }>(),
    { align: 'left' },
);

const alignment = computed(
    () => ({ left: 'text-left', right: 'text-right', center: 'text-center' })[props.align],
);
</script>

<template>
    <td
        :class="
            cx(
                'px-5 py-3 align-middle',
                alignment,
                strong ? 'font-semibold text-ink' : muted ? 'text-ink-subtle' : 'text-ink-muted',
                nowrap && 'whitespace-nowrap',
            )
        "
    >
        <slot />
    </td>
</template>
