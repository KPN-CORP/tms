<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import UiLanguageSwitcher from '@/components/ui/UiLanguageSwitcher.vue';
import { initials } from '@/lib/format';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import type { SharedProps } from '@/types/inertia';

defineProps<{ sidebarOpen: boolean }>();

const emit = defineEmits<{ toggleSidebar: [] }>();

const page = usePage<SharedProps>();
const { t } = useLocale();
const { preference, isDark, setPreference, toggle } = useTheme();

const user = computed(() => page.props.auth.user);

const menuOpen = ref(false);
const menuRoot = ref<HTMLElement | null>(null);

const onDocumentClick = (event: MouseEvent): void => {
    if (menuOpen.value && !menuRoot.value?.contains(event.target as Node)) {
        menuOpen.value = false;
    }
};

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        menuOpen.value = false;
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

/** Labelnya ikut kamus, jadi daftarnya menyimpan kunci -- bukan teks jadi. */
const THEMES = [
    { value: 'light', key: 'themeLight' },
    { value: 'dark', key: 'themeDark' },
    { value: 'system', key: 'themeSystem' },
] as const;
</script>

<template>
    <!-- Tinggi dikunci ke --topbar-height agar garis bawahnya sejajar dengan
         garis bawah blok logo di sidebar. -->
    <header
        class="sticky top-0 z-30 flex h-topbar shrink-0 items-center border-b border-line bg-surface px-4 sm:px-8"
    >
        <button
            type="button"
            class="mr-3 text-ink-subtle transition-colors hover:text-brand"
            :aria-expanded="sidebarOpen"
            :aria-label="t.shell.toggleNavigation"
            @click="emit('toggleSidebar')"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18" />
            </svg>
        </button>

        <!-- Konteks Report: penanda bahwa kewenangan lintas organisasi sedang
             aktif, supaya tidak ada yang menindak atas nama orang lain tanpa
             sadar. Lihat App\Support\ReportOverride. -->
        <p
            v-if="page.props.reportContext"
            class="hidden items-center gap-1.5 rounded-full bg-warning-soft px-3 py-1 text-xs font-semibold text-warning-ink ring-1 ring-inset ring-warning/20 sm:inline-flex"
        >
            <span class="h-1.5 w-1.5 rounded-full bg-warning" aria-hidden="true" />
            {{ t.topbar.reportContext }}
        </p>

        <div class="ml-auto flex min-w-0 items-center gap-3 sm:gap-5">
            <!-- Pengalih bahasa. Posisinya sama dengan kpn-tmp: paling kiri di
                 kelompok kanan, sebelum pemisah dan blok pengguna. -->
            <UiLanguageSwitcher align="right" collapse-label />

            <span class="hidden h-8 w-px shrink-0 bg-line sm:block" aria-hidden="true" />

            <!-- Pengalih tema. Satu klik membalik terang/gelap; pilihan "System"
                 tetap tersedia di menu pengguna. -->
            <button
                type="button"
                class="rounded-control p-2 text-ink-subtle transition-colors hover:bg-surface-muted hover:text-brand"
                :title="isDark ? t.topbar.switchToLight : t.topbar.switchToDark"
                :aria-label="isDark ? t.topbar.switchToLight : t.topbar.switchToDark"
                :aria-pressed="isDark"
                @click="toggle"
            >
                <svg
                    v-if="isDark"
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="4" />
                    <path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>

                <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
                </svg>
            </button>

            <span class="hidden h-8 w-px shrink-0 bg-line sm:block" aria-hidden="true" />

            <div ref="menuRoot" class="relative">
                <button
                    type="button"
                    class="flex min-w-0 items-center gap-3 rounded transition-opacity hover:opacity-80"
                    :aria-expanded="menuOpen"
                    aria-haspopup="menu"
                    @click="menuOpen = !menuOpen"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-avatar text-sm font-semibold text-white"
                        aria-hidden="true"
                    >
                        {{ initials(user?.name) }}
                    </span>

                    <span class="hidden min-w-0 text-left leading-tight sm:block">
                        <span class="block max-w-[14rem] truncate text-sm font-bold text-ink">
                            {{ user?.name ?? t.topbar.guest }}
                        </span>
                        <span class="block max-w-[14rem] truncate text-xs text-ink-subtle">
                            {{ user?.email }}
                        </span>
                    </span>
                </button>

                <div
                    v-if="menuOpen"
                    class="absolute right-0 z-40 mt-2 w-56 animate-slide-up overflow-hidden rounded-card border border-line bg-surface shadow-raised"
                    role="menu"
                >
                    <div class="border-b border-line px-4 py-3 sm:hidden">
                        <p class="truncate text-sm font-bold text-ink">{{ user?.name }}</p>
                        <p class="truncate text-xs text-ink-subtle">{{ user?.email }}</p>
                    </div>

                    <div class="p-2">
                        <p class="px-2 pb-1.5 pt-1 text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            {{ t.topbar.appearance }}
                        </p>

                        <div class="flex gap-1 rounded-control bg-surface-muted p-1">
                            <button
                                v-for="theme in THEMES"
                                :key="theme.value"
                                type="button"
                                class="flex-1 rounded px-2 py-1.5 text-xs font-medium transition-colors"
                                :class="
                                    preference === theme.value
                                        ? 'bg-surface text-ink shadow-card'
                                        : 'text-ink-subtle hover:text-ink-muted'
                                "
                                role="menuitemradio"
                                :aria-checked="preference === theme.value"
                                @click="setPreference(theme.value)"
                            >
                                {{ t.topbar[theme.key] }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
