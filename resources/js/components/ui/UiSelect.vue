<script lang="ts">
/**
 * Akar komponen ini adalah <div> pembungkus, jadi atribut yang jatuh otomatis
 * harus diteruskan sendiri ke tombol pemicu.
 */
export default { inheritAttrs: false };
</script>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { cx } from '@/lib/format';
import { CONTROL_CLASSES, controlBorder, useFieldContext } from '@/composables/useFieldContext';
import { useFloatingMenu } from '@/composables/useFloatingMenu';
import { useLocale } from '@/composables/useLocale';
import type { SelectOption } from '@/types/models';

/**
 * Select yang BISA DICARI — pengganti <select> bawaan.
 *
 * Dulu komponen ini memakai <select> asli, yang tidak bisa disaring; versi
 * Blade memakai TomSelect untuk itu. Sekarang pencarian jadi perilaku bawaan
 * semua dropdown, dan menunya di-teleport sehingga tidak terpotong di dalam
 * dialog atau tabel yang menggulir.
 */
const props = withDefaults(
    defineProps<{
        options?: SelectOption[];
        /**
         * Opsi bertingkat (<optgroup> pada versi lama), dipakai dropdown UoM
         * yang dikelompokkan per kategori. Dipakai sebagai ganti `options`.
         */
        groups?: Array<{ label: string; options: SelectOption[] }>;
        /** Baris kosong di paling atas; null berarti tidak ada. */
        placeholder?: string | null;
        disabled?: boolean;
        /**
         * Kotak pencarian disembunyikan bila opsinya tidak lebih dari ini.
         * Bawaannya 0 — semua dropdown bisa dicari, apa pun panjangnya, supaya
         * perilakunya seragam dan tidak perlu dihafal per tempat.
         */
        searchThreshold?: number;
    }>(),
    { options: () => [], placeholder: null, searchThreshold: 0 },
);

const model = defineModel<string | number | null>();

const field = useFieldContext();
const { t } = useLocale();
const { trigger, menu, open, menuStyle, toggle, show, close } = useFloatingMenu();

const search = ref('');
const searchInput = ref<HTMLInputElement | null>(null);
const activeIndex = ref(-1);

/** Semua opsi sebagai satu daftar datar, apa pun bentuk masukannya. */
const allOptions = computed<SelectOption[]>(() =>
    props.groups ? props.groups.flatMap((group) => group.options) : props.options,
);

const matches = (option: SelectOption, term: string): boolean =>
    option.label.toLowerCase().includes(term) || String(option.value).toLowerCase().includes(term);

/** Grup yang kehabisan isi setelah disaring tidak ikut dirender. */
const visibleGroups = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!props.groups) {
        return null;
    }

    return props.groups
        .map((group) => ({
            label: group.label,
            options: term ? group.options.filter((o) => matches(o, term)) : group.options,
        }))
        .filter((group) => group.options.length > 0);
});

const flatOptions = computed<SelectOption[]>(() => {
    const term = search.value.trim().toLowerCase();

    if (visibleGroups.value) {
        return visibleGroups.value.flatMap((group) => group.options);
    }

    return term ? props.options.filter((o) => matches(o, term)) : props.options;
});

/** Baris yang bisa disorot papan ketik, termasuk baris placeholder. */
const navigable = computed<Array<SelectOption | null>>(() =>
    props.placeholder !== null && !search.value.trim()
        ? [null, ...flatOptions.value]
        : [...flatOptions.value],
);

const selectedLabel = computed(
    () => allOptions.value.find((o) => o.value === model.value)?.label ?? '',
);

const showSearch = computed(() => allOptions.value.length > props.searchThreshold);

const choose = (option: SelectOption | null): void => {
    model.value = option ? option.value : null;
    close();
};

const openMenu = async (): Promise<void> => {
    if (props.disabled) {
        return;
    }

    search.value = '';
    activeIndex.value = navigable.value.findIndex(
        (o) => (o ? o.value : null) === (model.value ?? null),
    );
    show();
    await nextTick();
    searchInput.value?.focus();
};

const onTriggerClick = (): void => {
    if (open.value) {
        close();
    } else {
        void openMenu();
    }
};

const onKeydown = (event: KeyboardEvent): void => {
    if (!open.value) {
        if (['Enter', ' ', 'ArrowDown'].includes(event.key)) {
            event.preventDefault();
            void openMenu();
        }

        return;
    }

    const items = navigable.value;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = items.length ? (activeIndex.value + 1) % items.length : -1;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = items.length ? (activeIndex.value - 1 + items.length) % items.length : -1;
    } else if (event.key === 'Enter') {
        event.preventDefault();

        if (activeIndex.value >= 0 && activeIndex.value < items.length) {
            choose(items[activeIndex.value] ?? null);
        }
    } else if (event.key === 'Tab') {
        close();
    }
};

// Menyaring mengubah daftar, jadi sorotan dikembalikan ke baris pertama.
watch(search, () => (activeIndex.value = navigable.value.length ? 0 : -1));

const indexOf = (option: SelectOption): number =>
    navigable.value.findIndex((o) => o !== null && o.value === option.value);

const triggerClasses = computed(() =>
    cx(
        CONTROL_CLASSES,
        controlBorder(field.invalid()),
        'h-control flex items-center justify-between gap-2 text-left',
        props.disabled && 'cursor-not-allowed',
    ),
);

const ROW = 'flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm transition-colors';
</script>

<template>
    <div :class="['relative', $attrs.class]">
        <button
            :id="($attrs.id as string) ?? (field.id || undefined)"
            ref="trigger"
            type="button"
            :class="triggerClasses"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :aria-invalid="field.invalid() || undefined"
            :aria-describedby="field.describedBy()"
            @click="onTriggerClick"
            @keydown="onKeydown"
        >
            <span :class="cx('truncate', !selectedLabel && 'text-ink-subtle')">
                {{ selectedLabel || placeholder || 'Select…' }}
            </span>

            <svg
                class="h-4 w-4 shrink-0 text-ink-subtle transition-transform"
                :class="open && 'rotate-180'"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
            </svg>
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
                        @keydown="onKeydown"
                    />
                </div>

                <ul class="flex-1 overflow-y-auto scroll-slim py-1" role="listbox">
                    <!-- Baris placeholder berfungsi sebagai "kosongkan pilihan";
                         filter memakainya untuk kembali ke "semua". -->
                    <li v-if="placeholder !== null && !search.trim()">
                        <button
                            type="button"
                            :class="
                                cx(
                                    ROW,
                                    activeIndex === 0 ? 'bg-surface-muted' : '',
                                    model === null || model === undefined
                                        ? 'font-medium text-brand'
                                        : 'text-ink-subtle',
                                )
                            "
                            role="option"
                            :aria-selected="model === null || model === undefined"
                            @mouseenter="activeIndex = 0"
                            @click="choose(null)"
                        >
                            {{ placeholder }}
                        </button>
                    </li>

                    <template v-if="visibleGroups">
                        <li v-for="group in visibleGroups" :key="group.label">
                            <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                                {{ group.label }}
                            </p>

                            <button
                                v-for="option in group.options"
                                :key="option.value"
                                type="button"
                                :class="
                                    cx(
                                        ROW,
                                        indexOf(option) === activeIndex ? 'bg-surface-muted' : '',
                                        option.value === model ? 'font-medium text-brand' : 'text-ink-muted',
                                    )
                                "
                                role="option"
                                :aria-selected="option.value === model"
                                @mouseenter="activeIndex = indexOf(option)"
                                @click="choose(option)"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                                <svg
                                    v-if="option.value === model"
                                    class="h-3.5 w-3.5 shrink-0"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.79a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </li>
                    </template>

                    <li v-for="option in flatOptions" v-else :key="option.value">
                        <button
                            type="button"
                            :class="
                                cx(
                                    ROW,
                                    indexOf(option) === activeIndex ? 'bg-surface-muted' : '',
                                    option.value === model ? 'font-medium text-brand' : 'text-ink-muted',
                                )
                            "
                            role="option"
                            :aria-selected="option.value === model"
                            @mouseenter="activeIndex = indexOf(option)"
                            @click="choose(option)"
                        >
                            <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                            <svg
                                v-if="option.value === model"
                                class="h-3.5 w-3.5 shrink-0"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.79a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </li>

                    <li
                        v-if="!flatOptions.length"
                        class="px-3 py-4 text-center text-xs text-ink-subtle"
                    >
                        {{ t.controls.noMatches }}
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>
