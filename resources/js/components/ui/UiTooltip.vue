<script setup lang="ts">
import { ref } from 'vue';

/**
 * Tooltip yang di-teleport ke <body> dan diposisikan dari koordinat layar, jadi
 * isinya tidak terpotong oleh `overflow-hidden` pada kartu atau tabel.
 *
 * Dipasang pada elemen yang bisa difokus agar isinya juga terbaca lewat papan
 * ketik, bukan hanya saat disorot tetikus.
 */
withDefaults(defineProps<{ label?: string }>(), {});

const open = ref(false);
const position = ref({ x: 0, y: 0 });

const show = (event: Event): void => {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();

    position.value = { x: Math.round(rect.left), y: Math.round(rect.bottom + 6) };
    open.value = true;
};
</script>

<template>
    <span
        class="inline-flex cursor-help items-center rounded"
        tabindex="0"
        :aria-label="label"
        @mouseenter="show"
        @focus="show"
        @mouseleave="open = false"
        @blur="open = false"
    >
        <slot />

        <Teleport to="body">
            <div
                v-if="open"
                class="pointer-events-none fixed z-[70] animate-fade-in rounded-control border border-line bg-surface px-3 py-2 text-xs shadow-raised"
                :style="{ left: `${position.x}px`, top: `${position.y}px` }"
                role="tooltip"
            >
                <slot name="content" />
            </div>
        </Teleport>
    </span>
</template>
