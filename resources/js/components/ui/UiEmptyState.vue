<script setup lang="ts">
import { useSlots } from 'vue';

defineProps<{ title: string; message?: string }>();

const slots = useSlots();
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-surface-muted text-ink-subtle">
            <slot name="icon">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h10M4 17h7" />
                </svg>
            </slot>
        </div>

        <div>
            <p class="text-sm font-semibold text-ink">{{ title }}</p>
            <p v-if="message" class="mt-1 text-sm text-ink-subtle">{{ message }}</p>
        </div>

        <div v-if="slots.action" class="mt-1">
            <slot name="action" />
        </div>
    </div>
</template>
