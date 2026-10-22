<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { cx } from '@/lib/format';
import { useLocale } from '@/composables/useLocale';
import type { NavGroup, NavItem, SharedProps } from '@/types/inertia';
import NavEntry from './NavEntry.vue';

const props = defineProps<{
    open: boolean;
    /** Di layar sempit sidebar melayang di atas konten dengan backdrop. */
    floating: boolean;
}>();

const emit = defineEmits<{ close: [] }>();

const page = usePage<SharedProps>();
const { t } = useLocale();

const groups = computed(() => page.props.nav?.groups ?? []);

/** Sama seperti NavEntry: kunci dulu, teks server sebagai cadangan. */
const groupLabel = (group: NavGroup): string | null => {
    if (!group.key) {
        return group.label;
    }

    const dictionary = t.value.nav.groups as unknown as Record<string, string | undefined>;

    return dictionary[group.key] ?? group.label;
};

/** Logout tetap POST agar sesi benar-benar dimatikan server. */
const logout = (): void => {
    router.post(route('logout'));
};

/**
 * HANYA di mode melayang panel ditutup setelah memilih menu — di sana ia
 * menutupi isi halaman. Di layar lebar sidebar menetap: menutupnya setiap kali
 * berpindah halaman membuat menu seolah hilang sendiri.
 */
const onNavigate = (item: NavItem): void => {
    if (props.floating && item.href) {
        emit('close');
    }
};
</script>

<template>
    <!-- Backdrop hanya ada saat sidebar melayang. -->
    <div
        v-if="floating && open"
        class="fixed inset-0 z-40 animate-fade-in bg-black/50 lg:hidden"
        @click="emit('close')"
    />

    <aside
        :class="
            cx(
                'flex w-sidebar shrink-0 flex-col border-r border-line bg-surface',
                floating
                    ? 'fixed inset-y-0 left-0 z-50 shadow-overlay transition-transform duration-200'
                    : 'h-full transition-[margin] duration-200',
                floating && !open && '-translate-x-full',
                !floating && !open && '-ml-sidebar',
            )
        "
        :aria-label="t.shell.mainNavigation"
    >
        <!-- Blok logo. Tingginya sama dengan topbar supaya garis bawah keduanya
             menyambung sebagai satu garis lurus. -->
        <div class="flex h-topbar shrink-0 items-center gap-3 border-b border-line px-6">
            <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-3 rounded">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand text-lg font-bold text-white"
                    aria-hidden="true"
                >
                    T
                </span>
                <span class="truncate font-bold text-brand">{{ t.app.name }}</span>
            </Link>

            <button
                v-if="floating"
                type="button"
                class="ml-auto text-ink-subtle transition-colors hover:text-brand"
                :aria-label="t.shell.closeMenu"
                @click="emit('close')"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        </div>

        <nav class="min-h-0 flex-1 overflow-y-auto scroll-slim py-4">
            <div v-for="(group, index) in groups" :key="group.key ?? `top-${index}`" class="mb-6 last:mb-2">
                <p
                    v-if="groupLabel(group)"
                    class="px-6 pb-2 text-xs font-semibold uppercase tracking-wider text-ink-subtle/70"
                >
                    {{ groupLabel(group) }}
                </p>

                <NavEntry
                    v-for="item in group.items"
                    :key="item.key + (item.href ?? '')"
                    :item="item"
                    @navigate="onNavigate"
                />
            </div>
        </nav>

        <div class="mt-auto">
            <button
                type="button"
                class="flex w-full items-center gap-3 border-l-4 border-transparent border-t border-t-line px-6 py-3 text-sm text-ink-muted transition-all hover:bg-brand-soft hover:font-bold hover:text-brand"
                @click="logout"
            >
                <svg class="w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17l5-5-5-5M20 12H9M12 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6" />
                </svg>
                {{ t.shell.logout }}
            </button>

            <div class="border-t border-line px-6 py-4">
                <p class="text-xs text-ink-subtle">{{ t.app.longName }}</p>
            </div>
        </div>
    </aside>
</template>
