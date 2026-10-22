<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, useSlots, watch } from 'vue';
import { cx } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        /** Matikan untuk dialog yang wajib dijawab lewat tombolnya. */
        closeOnBackdrop?: boolean;
    }>(),
    { size: 'md', closeOnBackdrop: true },
);

const open = defineModel<boolean>({ required: true });

const slots = useSlots();
const panel = ref<HTMLElement | null>(null);
const titleId = useId();

const SIZES = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };

const close = (): void => {
    open.value = false;
};

const onBackdrop = (): void => {
    if (props.closeOnBackdrop) {
        close();
    }
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        close();
        return;
    }

    if (event.key !== 'Tab' || !panel.value) {
        return;
    }

    // Jebak fokus di dalam dialog: Tab dari elemen terakhir kembali ke yang
    // pertama, Shift+Tab sebaliknya.
    const focusable = panel.value.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (!first || !last) {
        return;
    }

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

/** Kembalikan fokus ke pemicu saat dialog ditutup. */
let restoreFocusTo: HTMLElement | null = null;

const lockScroll = (locked: boolean): void => {
    document.body.style.overflow = locked ? 'hidden' : '';
};

watch(open, async (isOpen) => {
    if (isOpen) {
        restoreFocusTo = document.activeElement as HTMLElement | null;
        lockScroll(true);
        await nextTick();
        panel.value?.querySelector<HTMLElement>('[autofocus]')?.focus() ?? panel.value?.focus();
    } else {
        lockScroll(false);
        restoreFocusTo?.focus();
        restoreFocusTo = null;
    }
});

onBeforeUnmount(() => lockScroll(false));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="title ? titleId : undefined"
            @keydown="onKeydown"
        >
            <div class="fixed inset-0 animate-fade-in bg-black/50" @click="onBackdrop" />

            <div
                ref="panel"
                tabindex="-1"
                :class="
                    cx(
                        'relative w-full animate-slide-up rounded-card border border-line bg-surface shadow-overlay',
                        'max-h-[calc(100vh-2rem)] overflow-y-auto scroll-slim outline-none',
                        SIZES[size],
                    )
                "
            >
                <header v-if="title || slots.header" class="border-b border-line px-5 py-4">
                    <slot name="header">
                        <h2 :id="titleId" class="text-base font-semibold text-ink">{{ title }}</h2>
                        <p v-if="description" class="mt-1 text-sm text-ink-subtle">{{ description }}</p>
                    </slot>
                </header>

                <div class="px-5 py-4">
                    <slot />
                </div>

                <footer
                    v-if="slots.footer"
                    class="flex justify-end gap-3 border-t border-line bg-surface-muted px-5 py-3"
                >
                    <slot name="footer" :close="close" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
