<script lang="ts">
export default { inheritAttrs: false };
</script>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { cx } from '@/lib/format';
import { CONTROL_CLASSES, controlBorder, useFieldContext } from '@/composables/useFieldContext';
import { useFloatingMenu } from '@/composables/useFloatingMenu';
import { useLocale } from '@/composables/useLocale';
import type { SelectOption } from '@/types/models';

/**
 * Dropdown dengan pencarian ke server — pengganti TomSelect.
 *
 * Dipakai untuk daftar yang terlalu besar dikirim seluruhnya (employee hcis).
 * Endpoint dipanggil dengan `?q=`, hasilnya maksimal 30 baris dari server.
 *
 * Pilihan yang sedang aktif disimpan sebagai `selectedLabel`, bukan dicari di
 * dalam daftar hasil: daftar berubah setiap kali orang mengetik, dan label
 * pilihan harus tetap terbaca walau tidak ada di hasil pencarian terakhir.
 */
const props = withDefaults(
    defineProps<{
        /** Endpoint JSON yang menerima `?q=`. */
        searchUrl: string;
        /** Kunci pada setiap baris yang dipakai sebagai nilai. */
        valueKey?: string;
        placeholder?: string | null;
        /** Label pilihan awal dari server (mode edit / draft). */
        initialLabel?: string | null;
        disabled?: boolean;
        minChars?: number;
    }>(),
    { valueKey: 'employee_id', placeholder: null, minChars: 1 },
);

const model = defineModel<string | number | null>();

const field = useFieldContext();
const { t } = useLocale();
const { trigger, menu, open, menuStyle, show, close } = useFloatingMenu();

const term = ref('');
const options = ref<SelectOption[]>([]);
const loading = ref(false);
const activeIndex = ref(-1);
const selectedLabel = ref(props.initialLabel ?? '');
const input = ref<HTMLInputElement | null>(null);

watch(
    () => props.initialLabel,
    (label) => {
        if (label) {
            selectedLabel.value = label;
        }
    },
);

// Nilai dikosongkan dari luar (mis. form.reset) → label ikut hilang.
watch(model, (value) => {
    if (value === null || value === undefined || value === '') {
        selectedLabel.value = '';
    }
});

let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | null = null;

const search = async (): Promise<void> => {
    const query = term.value.trim();

    if (query.length < props.minChars) {
        options.value = [];
        return;
    }

    controller?.abort();
    controller = new AbortController();
    loading.value = true;

    try {
        const response = await fetch(`${props.searchUrl}?q=${encodeURIComponent(query)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const rows: unknown = await response.json();

        options.value = Array.isArray(rows)
            ? rows
                  .map((row) => {
                      const record = row as Record<string, unknown>;
                      const value = record[props.valueKey];
                      const label = record.label ?? record.name ?? value;

                      return value === undefined || value === null
                          ? null
                          : { value: value as string | number, label: String(label) };
                  })
                  .filter((option): option is SelectOption => option !== null)
            : [];

        activeIndex.value = options.value.length ? 0 : -1;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            options.value = [];
        }
    } finally {
        loading.value = false;
    }
};

watch(term, () => {
    clearTimeout(timer);
    timer = setTimeout(() => void search(), 300);
});

const choose = (option: SelectOption): void => {
    model.value = option.value;
    selectedLabel.value = option.label;
    close();
    term.value = '';
};

const clear = (): void => {
    model.value = null;
    selectedLabel.value = '';
    term.value = '';
};

const openMenu = async (): Promise<void> => {
    if (props.disabled) {
        return;
    }

    show();
    await nextTick();
    input.value?.focus();
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        close();
        return;
    }

    if (!options.value.length) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % options.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value - 1 + options.value.length) % options.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const option = options.value[activeIndex.value];

        if (option) {
            choose(option);
        }
    }
};

// Menutup saat klik di luar ditangani useFloatingMenu.
onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
});

const hasValue = computed(() => model.value !== null && model.value !== undefined && model.value !== '');

const buttonClasses = computed(() =>
    cx(
        CONTROL_CLASSES,
        controlBorder(field.invalid()),
        'h-control flex items-center justify-between gap-2 text-left',
        props.disabled && 'cursor-not-allowed',
    ),
);
</script>

<template>
    <div :class="['relative', $attrs.class]">
        <button
            :id="($attrs.id as string) ?? (field.id || undefined)"
            ref="trigger"
            type="button"
            :class="buttonClasses"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :aria-invalid="field.invalid() || undefined"
            :aria-describedby="field.describedBy()"
            @click="open ? close() : openMenu()"
        >
            <span :class="cx('truncate', !hasValue && 'text-ink-subtle')">
                {{ selectedLabel || placeholder || t.controls.search }}
            </span>

            <span class="flex shrink-0 items-center gap-1">
                <span
                    v-if="hasValue && !disabled"
                    class="rounded p-0.5 text-ink-subtle transition-colors hover:bg-surface-muted hover:text-ink"
                    role="button"
                    tabindex="0"
                    :aria-label="t.controls.clearSelection"
                    @click.stop="clear"
                    @keydown.enter.stop.prevent="clear"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </span>

                <svg class="h-4 w-4 text-ink-subtle" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
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
                <div class="shrink-0 border-b border-line p-2">
                    <input
                        ref="input"
                        v-model="term"
                        type="search"
                        class="w-full rounded border border-line bg-surface px-2 py-1.5 text-sm text-ink placeholder:text-ink-subtle"
                        :placeholder="t.controls.typeToSearch"
                        @keydown="onKeydown"
                    />
                </div>

                <ul class="flex-1 overflow-y-auto scroll-slim py-1" role="listbox">
                    <li
                        v-for="(option, index) in options"
                        :key="option.value"
                        role="option"
                        :aria-selected="option.value === model"
                        class="cursor-pointer px-3 py-2 text-sm transition-colors"
                        :class="index === activeIndex ? 'bg-surface-muted' : 'text-ink-muted hover:bg-surface-muted'"
                        @mouseenter="activeIndex = index"
                        @click="choose(option)"
                    >
                        {{ option.label }}
                    </li>

                    <li v-if="loading" class="px-3 py-2 text-sm text-ink-subtle">Searching…</li>

                    <li v-else-if="!options.length" class="px-3 py-2 text-sm text-ink-subtle">
                        {{ term.trim().length < minChars ? t.controls.typeToSearchHint : t.controls.noMatches }}
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>
