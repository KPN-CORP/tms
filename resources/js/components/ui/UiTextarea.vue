<script setup lang="ts">
import { computed } from 'vue';
import { cx } from '@/lib/format';
import { CONTROL_CLASSES, controlBorder, useFieldContext } from '@/composables/useFieldContext';

withDefaults(
    defineProps<{
        rows?: number;
        placeholder?: string;
        disabled?: boolean;
        maxlength?: number;
    }>(),
    { rows: 4 },
);

const model = defineModel<string | null>();

const field = useFieldContext();

const classes = computed(() => cx(CONTROL_CLASSES, controlBorder(field.invalid()), 'py-2 leading-relaxed'));
</script>

<template>
    <textarea
        :id="field.id || undefined"
        v-model="model"
        :rows="rows"
        :placeholder="placeholder"
        :disabled="disabled"
        :maxlength="maxlength"
        :aria-invalid="field.invalid() || undefined"
        :aria-describedby="field.describedBy()"
        :class="classes"
    />
</template>
