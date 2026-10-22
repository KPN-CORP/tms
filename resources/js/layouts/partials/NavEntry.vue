<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLink from '@/components/ui/AppLink.vue';
import { cx } from '@/lib/format';
import { tr, useLocale } from '@/composables/useLocale';
import type { NavItem } from '@/types/inertia';

const props = defineProps<{ item: NavItem; depth?: number }>();

const emit = defineEmits<{ navigate: [item: NavItem] }>();

const { t } = useLocale();

/**
 * Label menu datang dari server sebagai pasangan kunci + teks Inggris. Yang
 * dipakai adalah terjemahan dari kuncinya; teks server jadi cadangan, supaya
 * menu baru yang belum masuk kamus tetap terbaca alih-alih kosong.
 */
const label = computed<string>(() => {
    const dictionary = t.value.nav as unknown as Record<string, string | undefined>;

    return dictionary[props.item.key] ?? props.item.label;
});

const badgeTitle = computed(() => tr(t.value.shell.tasksWaiting, { count: props.item.badge }));

/**
 * Parent terbuka sendiri saat salah satu anaknya aktif, tapi sesudah itu
 * pengguna yang memegang kendali — karena layout persisten, state buka/tutup
 * bertahan antar navigasi.
 */
const expanded = ref(props.item.active);

watch(
    () => props.item.active,
    (active) => {
        if (active) {
            expanded.value = true;
        }
    },
);

/**
 * Penanda aktif berupa GARIS KIRI tebal, bukan latar membulat — mengikuti
 * kpn-tmp / kpn-coi. Border kiri selalu ada (transparan saat tidak aktif) agar
 * teksnya tidak bergeser saat status berubah.
 */
const BASE = 'flex w-full items-center gap-3 border-l-4 border-transparent text-sm transition-all';

const stateClasses = (active: boolean): string =>
    active
        ? 'border-brand bg-brand-soft font-bold text-brand'
        : 'text-ink-muted hover:bg-brand-soft hover:text-brand';
</script>

<template>
    <!-- Parent ber-anak: tombol pembuka, bukan tautan. -->
    <div v-if="item.children.length">
        <button
            type="button"
            :class="cx(BASE, stateClasses(item.active), 'px-6 py-3')"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            <span class="truncate">{{ label }}</span>

            <svg
                class="ml-auto h-3 w-3 shrink-0 transition-transform"
                :class="expanded ? 'rotate-180' : ''"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            </svg>
        </button>

        <div v-show="expanded" class="bg-surface-muted/40">
            <NavEntry
                v-for="child in item.children"
                :key="child.key + (child.href ?? '')"
                :item="child"
                :depth="(depth ?? 0) + 1"
                @navigate="emit('navigate', $event)"
            />
        </div>
    </div>

    <AppLink
        v-else-if="item.href"
        :href="item.href"
        :external="item.external"
        :class="cx(BASE, stateClasses(item.active), depth ? 'py-2.5 pl-14 pr-6' : 'px-6 py-3')"
        :aria-current="item.active ? 'page' : undefined"
        @click="emit('navigate', item)"
    >
        <span class="truncate">{{ label }}</span>

        <!-- Badge = tugas yang menunggu keputusan user ini. -->
        <span
            v-if="item.badge > 0"
            class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-brand px-1.5 py-0.5 text-[0.6875rem] font-bold leading-none text-white"
            :title="badgeTitle"
        >
            {{ item.badge > 99 ? '99+' : item.badge }}
        </span>
    </AppLink>
</template>
