<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import UiFlagIcon from './UiFlagIcon.vue';
import { useLocale, type Locale } from '@/composables/useLocale';

/**
 * Pengalih EN/ID. Satu komponen dipakai topbar DAN halaman tamu, supaya
 * keduanya tidak bisa berbeda perilaku.
 *
 * Bentuk menunya sengaja menyalin menu pengguna di AppTopbar (absolute +
 * penutup saat klik di luar) — bukan useFloatingMenu, yang mengunci lebar menu
 * ke lebar pemicunya.
 */
const props = withDefaults(
    defineProps<{
        /** Tepi tempat menu ditambatkan. */
        align?: 'left' | 'right';
        /** Sembunyikan label di bawah `sm` dan sisakan benderanya saja. */
        collapseLabel?: boolean;
    }>(),
    { align: 'left', collapseLabel: false },
);

const { locale, t, setLocale } = useLocale();

// Setiap bahasa ditulis dalam bahasanya sendiri (endonim), jadi daftarnya
// terbaca sama apa pun bahasa yang sedang aktif.
const LANGUAGES: Locale[] = ['en', 'id'];

const labelOf = (code: Locale): string => t.value.language[code];

// Hanya alternatifnya yang didaftar: bahasa aktif sudah tertulis di pemicu.
const alternatives = computed(() => LANGUAGES.filter((code) => code !== locale.value));

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const choose = async (code: Locale): Promise<void> => {
    await setLocale(code);
    open.value = false;
};

const onDocumentClick = (event: MouseEvent): void => {
    if (open.value && !root.value?.contains(event.target as Node)) {
        open.value = false;
    }
};

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <div ref="root" class="relative shrink-0">
        <button
            type="button"
            class="flex items-center gap-2 rounded-control px-2 py-1.5 text-sm font-semibold text-ink-muted transition-colors hover:bg-surface-muted hover:text-brand"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-label="t.language.label"
            @click="open = !open"
        >
            <UiFlagIcon :locale="locale" />

            <span :class="props.collapseLabel ? 'hidden sm:inline' : 'inline'">
                {{ labelOf(locale) }}
            </span>

            <svg
                class="h-3 w-3 shrink-0 transition-transform"
                :class="[open ? 'rotate-180' : '', props.collapseLabel ? 'hidden sm:block' : 'block']"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute z-40 mt-2 min-w-[10rem] animate-slide-up overflow-hidden rounded-card border border-line bg-surface p-1.5 shadow-raised"
            :class="props.align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left'"
            role="menu"
        >
            <button
                v-for="code in alternatives"
                :key="code"
                type="button"
                class="flex w-full items-center gap-2.5 rounded-control px-3 py-2 text-left text-sm font-medium text-ink-muted transition-colors hover:bg-surface-muted hover:text-brand"
                role="menuitem"
                @click="choose(code)"
            >
                <UiFlagIcon :locale="code" />
                <span>{{ labelOf(code) }}</span>
            </button>
        </div>
    </div>
</template>
