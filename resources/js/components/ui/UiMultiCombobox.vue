<script setup lang="ts">
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useFloatingMenu } from '@/composables/useFloatingMenu';
import { useLocale } from '@/composables/useLocale';
import { cx } from '@/lib/format';
import { CONTROL_CLASSES, controlBorder, useFieldContext } from '@/composables/useFieldContext';
import type { SelectOption } from '@/types/models';

/**
 * Pilihan GANDA dengan pencarian ke server — untuk daftar yang terlalu besar
 * dikirim seluruhnya (employee hcis).
 *
 * Label tiap pilihan disimpan terpisah dari hasil pencarian: daftar hasil
 * berganti setiap kali orang mengetik, sedangkan chip yang sudah dipilih harus
 * tetap terbaca.
 */
const props = withDefaults(
    defineProps<{
        searchUrl: string;
        valueKey?: string;
        placeholder?: string | null;
        /** Label pilihan awal dari server. */
        initialOptions?: SelectOption[];
        minChars?: number;
    }>(),
    { valueKey: 'id', placeholder: null, initialOptions: () => [], minChars: 1 },
);

const model = defineModel<Array<string | number>>({ default: () => [] });

const field = useFieldContext();
const { t } = useLocale();

const labels = reactive<Record<string, string>>({});
const { trigger, menu, open, menuStyle, show, close } = useFloatingMenu();
const term = ref('');
const results = ref<SelectOption[]>([]);
const loading = ref(false);

const seedLabels = (options: SelectOption[]): void => {
    for (const option of options) {
        labels[String(option.value)] = option.label;
    }
};

seedLabels(props.initialOptions);

watch(() => props.initialOptions, seedLabels);

let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | null = null;

const search = async (): Promise<void> => {
    const query = term.value.trim();

    if (query.length < props.minChars) {
        results.value = [];
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

        const rows: unknown = await response.json();

        results.value = Array.isArray(rows)
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
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            results.value = [];
        }
    } finally {
        loading.value = false;
    }
};

watch(term, () => {
    clearTimeout(timer);
    timer = setTimeout(() => void search(), 300);
});

const add = (option: SelectOption): void => {
    labels[String(option.value)] = option.label;

    if (!model.value.includes(option.value)) {
        model.value = [...model.value, option.value];
    }

    term.value = '';
    results.value = [];
};

const remove = (value: string | number): void => {
    model.value = model.value.filter((entry) => entry !== value);
};

// Menutup saat klik di luar ditangani useFloatingMenu.
onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <div class="relative">
        <div
            :class="cx(CONTROL_CLASSES, controlBorder(field.invalid()), 'min-h-control h-auto py-1.5')"
            ref="trigger"
            @click="show()"
        >
            <div class="flex flex-wrap items-center gap-1.5">
                <span
                    v-for="value in model"
                    :key="value"
                    class="inline-flex items-center gap-1 rounded-full bg-surface-muted px-2 py-0.5 text-xs text-ink-muted"
                >
                    {{ labels[String(value)] ?? value }}
                    <button
                        type="button"
                        class="rounded text-ink-subtle transition-colors hover:text-danger"
                        :aria-label="`Remove ${labels[String(value)] ?? value}`"
                        @click.stop="remove(value)"
                    >
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </span>

                <input
                    :id="field.id || undefined"
                    v-model="term"
                    type="search"
                    class="min-w-[8rem] flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-subtle focus:ring-0"
                    :placeholder="model.length ? '' : (placeholder ?? t.controls.search)"
                    @focus="show()"
                />
            </div>
        </div>

        <Teleport to="body">
            <ul
                v-if="open && (results.length || loading || term.trim())"
                ref="menu"
                class="overflow-y-auto scroll-slim rounded-control border border-line bg-surface py-1 shadow-raised"
                :style="menuStyle"
                role="listbox"
            >
            <li
                v-for="option in results"
                :key="option.value"
                role="option"
                :aria-selected="model.includes(option.value)"
                class="cursor-pointer px-3 py-2 text-sm text-ink-muted transition-colors hover:bg-surface-muted"
                @click="add(option)"
            >
                {{ option.label }}
            </li>

            <li v-if="loading" class="px-3 py-2 text-sm text-ink-subtle">Searching…</li>
            <li v-else-if="!results.length" class="px-3 py-2 text-sm text-ink-subtle">
                {{ t.controls.noMatches }}
            </li>
            </ul>
        </Teleport>
    </div>
</template>
