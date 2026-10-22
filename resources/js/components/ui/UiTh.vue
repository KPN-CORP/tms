<script setup lang="ts">
import { computed } from 'vue';
import { cx } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        /** Kunci sort; harus ada di whitelist HasListQuery milik controller. */
        sortKey?: string;
        /** Kunci yang sedang aktif beserta arahnya. */
        sort?: string | null;
        dir?: 'asc' | 'desc' | null;
        align?: 'left' | 'right' | 'center';
        /** Lebar tetap, mis. 'w-32'. */
        width?: string;
    }>(),
    { align: 'left' },
);

const emit = defineEmits<{ sort: [key: string] }>();

const active = computed(() => Boolean(props.sortKey) && props.sort === props.sortKey);

const ariaSort = computed(() => {
    if (!props.sortKey) {
        return undefined;
    }

    return active.value ? (props.dir === 'desc' ? 'descending' : 'ascending') : 'none';
});

const alignment = computed(
    () => ({ left: 'text-left', right: 'text-right', center: 'text-center' })[props.align],
);
</script>

<template>
    <th
        scope="col"
        :aria-sort="ariaSort"
        :class="
            cx(
                'px-5 py-3 text-xs font-semibold uppercase tracking-wider text-ink-subtle',
                alignment,
                width,
            )
        "
    >
        <button
            v-if="sortKey"
            type="button"
            class="group inline-flex items-center gap-1.5 rounded uppercase tracking-wider transition-colors hover:text-ink-muted"
            :class="active && 'text-ink-muted'"
            @click="emit('sort', sortKey)"
        >
            <slot />

            <svg
                class="h-3 w-3 shrink-0 transition-opacity"
                :class="active ? 'opacity-100' : 'opacity-0 group-hover:opacity-40'"
                viewBox="0 0 12 12"
                fill="currentColor"
                aria-hidden="true"
            >
                <path v-if="active && dir === 'desc'" d="M6 9.5 1.5 4h9L6 9.5Z" />
                <path v-else d="M6 2.5 10.5 8h-9L6 2.5Z" />
            </svg>
        </button>

        <slot v-else />
    </th>
</template>
