<script lang="ts">
export default { inheritAttrs: false };
</script>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { cx } from '@/lib/format';
import { CONTROL_CLASSES, controlBorder, useFieldContext } from '@/composables/useFieldContext';
import { useFloatingMenu } from '@/composables/useFloatingMenu';
import { useLocale } from '@/composables/useLocale';
import type { SelectOption } from '@/types/models';

/**
 * Pilihan GANDA yang bisa dicari, dari daftar yang sudah ada di klien —
 * dipakai PIC, restrict Business Unit, dan filter penerima email.
 *
 * Untuk daftar yang terlalu besar dikirim ke klien (employee hcis) pakai
 * UiMultiCombobox, yang mencari ke server.
 */
const props = withDefaults(
    defineProps<{
        options: SelectOption[];
        placeholder?: string | null;
        disabled?: boolean;
        /**
         * Kotak pencarian disembunyikan bila opsinya tidak lebih dari ini.
         * Bawaannya 0 — semua dropdown bisa dicari, apa pun panjangnya, supaya
         * perilakunya seragam dan tidak perlu dihafal per tempat.
         */
        searchThreshold?: number;
    }>(),
    { placeholder: null, searchThreshold: 0 },
);

const model = defineModel<Array<string | number>>({ default: () => [] });

const field = useFieldContext();
const { t } = useLocale();
const { trigger, menu, open, menuStyle, show, close } = useFloatingMenu();

const search = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.options;
    }

    return props.options.filter(
        (option) =>
            option.label.toLowerCase().includes(term) ||
            String(option.value).toLowerCase().includes(term),
    );
});

const showSearch = computed(() => props.options.length > props.searchThreshold);

const toggleOption = (value: string | number): void => {
    model.value = model.value.includes(value)
        ? model.value.filter((entry) => entry !== value)
        : [...model.value, value];
};

const clearAll = (): void => {
    model.value = [];
};

const onTriggerClick = async (): Promise<void> => {
    if (props.disabled) {
        return;
    }

    if (open.value) {
        close();
        return;
    }

    search.value = '';
    show();
    await nextTick();
    searchInput.value?.focus();
};

/** Ringkasan di pemicu: nama bila satu, jumlah bila lebih. */
const summary = computed(() => {
    const picked = model.value;

    if (!picked.length) {
        return '';
    }

    if (picked.length === 1) {
        return props.options.find((o) => o.value === picked[0])?.label ?? String(picked[0]);
    }

    return `${picked.length} selected`;
});

const selectedLabels = computed(() =>
    props.options.filter((o) => model.value.includes(o.value)).map((o) => o.label),
);
</script>

<template>
    <div :class="['relative', $attrs.class]">
        <button
            :id="($attrs.id as string) ?? (field.id || undefined)"
            ref="trigger"
            type="button"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :aria-invalid="field.invalid() || undefined"
            :aria-describedby="field.describedBy()"
            :class="
                cx(
                    CONTROL_CLASSES,
                    controlBorder(field.invalid()),
                    'h-control flex items-center justify-between gap-2 text-left',
                    disabled && 'cursor-not-allowed',
                )
            "
            @click="onTriggerClick"
        >
            <span :class="cx('truncate', !summary && 'text-ink-subtle')">{{ summary || placeholder || t.controls.select }}</span>

            <span class="flex shrink-0 items-center gap-1">
                <span
                    v-if="model.length && !disabled"
                    class="rounded p-0.5 text-ink-subtle transition-colors hover:bg-surface-muted hover:text-ink"
                    role="button"
                    tabindex="0"
                    :aria-label="t.controls.clearSelection"
                    @click.stop="clearAll"
                    @keydown.enter.stop.prevent="clearAll"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </span>

                <svg
                    class="h-4 w-4 text-ink-subtle transition-transform"
                    :class="open && 'rotate-180'"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                </svg>
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="menu"
                class="flex flex-col overflow-hidden rounded-control border border-line bg-surface shadow-raised"
                :style="menuStyle"
            >
                <div v-if="showSearch" class="shrink-0 border-b border-line p-2">
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="search"
                        :placeholder="t.controls.search"
                        class="w-full rounded border border-line bg-surface px-2 py-1.5 text-sm text-ink placeholder:text-ink-subtle"
                    />
                </div>

                <ul class="flex-1 overflow-y-auto scroll-slim py-1" role="listbox" aria-multiselectable="true">
                    <li v-for="option in filtered" :key="option.value">
                        <label
                            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-ink-muted transition-colors hover:bg-surface-muted"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-line-strong text-brand focus:ring-brand/40"
                                :checked="model.includes(option.value)"
                                @change="toggleOption(option.value)"
                            />
                            <span class="truncate">{{ option.label }}</span>
                        </label>
                    </li>

                    <li v-if="!filtered.length" class="px-3 py-4 text-center text-xs text-ink-subtle">
                        {{ options.length ? t.controls.noMatches : t.controls.nothingToChoose }}
                    </li>
                </ul>
            </div>
        </Teleport>

        <!-- Daftar lengkap tetap terbaca walau pemicu hanya menampilkan jumlah. -->
        <p v-if="selectedLabels.length > 1" class="mt-1 truncate text-xs text-ink-subtle">
            {{ selectedLabels.join(', ') }}
        </p>
    </div>
</template>
