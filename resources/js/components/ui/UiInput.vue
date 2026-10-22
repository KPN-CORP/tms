<script lang="ts">
/**
 * Akar komponen ini adalah <div> pembungkus (untuk ikon leading/trailing), jadi
 * atribut yang jatuh otomatis — `id`, `autocomplete`, `name` — akan mendarat di
 * <div> itu, bukan di <input>. `inheritAttrs: false` mematikan perilaku tersebut
 * supaya atribut bisa diteruskan ke <input> secara eksplisit; `class` sengaja
 * tetap di pembungkus karena dipakai untuk tata letak.
 */
export default { inheritAttrs: false };
</script>

<script setup lang="ts">
import { computed, useAttrs, useSlots } from 'vue';
import { cx } from '@/lib/format';
import { CONTROL_CLASSES, controlBorder, useFieldContext } from '@/composables/useFieldContext';

const props = withDefaults(
    defineProps<{
        type?: 'text' | 'email' | 'password' | 'number' | 'date' | 'search' | 'tel' | 'url';
        placeholder?: string;
        disabled?: boolean;
        readonly?: boolean;
        min?: string | number;
        max?: string | number;
        step?: string | number;
    }>(),
    { type: 'text' },
);

const model = defineModel<string | number | null>();

const field = useFieldContext();
const slots = useSlots();
const attrs = useAttrs();

/** `class` tetap di pembungkus; sisanya diteruskan ke <input>. */
const inputAttrs = computed(() => {
    const { class: _class, ...rest } = attrs;

    return rest;
});

const classes = computed(() =>
    cx(
        CONTROL_CLASSES,
        controlBorder(field.invalid()),
        'h-control',
        slots.leading && 'pl-9',
        slots.trailing && 'pr-9',
    ),
);
</script>

<template>
    <div :class="['relative', $attrs.class]">
        <span
            v-if="slots.leading"
            class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-ink-subtle"
        >
            <slot name="leading" />
        </span>

        <input
            v-bind="inputAttrs"
            :id="(inputAttrs.id as string) ?? (field.id || undefined)"
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :min="min"
            :max="max"
            :step="step"
            :aria-invalid="field.invalid() || undefined"
            :aria-describedby="field.describedBy()"
            :class="classes"
        />

        <span
            v-if="slots.trailing"
            class="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-ink-subtle"
        >
            <slot name="trailing" />
        </span>
    </div>
</template>
