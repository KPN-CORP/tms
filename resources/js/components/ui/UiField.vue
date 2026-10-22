<script setup lang="ts">
import { provide, useId } from 'vue';

const props = defineProps<{
    label?: string;
    hint?: string;
    error?: string;
    required?: boolean;
}>();

/**
 * Pembungkus label + pesan bantuan + pesan kesalahan.
 *
 * `id` dan `aria-describedby` dialirkan ke kontrol di dalamnya lewat provide,
 * jadi penghubungan label-kontrol tidak perlu ditulis ulang di tiap halaman --
 * dan pembaca layar selalu dapat pasangan yang benar.
 */
const id = useId();
const describedBy = `${id}-desc`;

provide('field', {
    id,
    describedBy: () => (props.error || props.hint ? describedBy : undefined),
    invalid: () => Boolean(props.error),
});
</script>

<template>
    <div class="min-w-0">
        <label v-if="label" :for="id" class="mb-1 block text-xs font-medium text-ink-label">
            {{ label }}
            <span v-if="required" class="text-danger" aria-hidden="true">*</span>
        </label>

        <slot />

        <p v-if="error" :id="describedBy" class="mt-1 text-xs font-medium text-danger">{{ error }}</p>
        <p v-else-if="hint" :id="describedBy" class="mt-1 text-xs text-ink-subtle">{{ hint }}</p>
    </div>
</template>
