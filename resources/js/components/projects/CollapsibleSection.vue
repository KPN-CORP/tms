<script setup lang="ts">
import { onMounted, ref, useSlots, watch } from 'vue';

/**
 * Section pada halaman Project Detail: header merah yang bisa dibuka-tutup,
 * posisinya diingat per pengguna.
 *
 * Kunci localStorage memakai awalan 'proj.sec.' yang sama dengan versi Blade,
 * jadi setelan yang sudah tersimpan di browser pengguna tetap terbaca.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        /** Potongan terakhir kunci penyimpanan, mis. 'impl' -> proj.sec.impl. */
        storageKey: string;
        /** Dipakai untuk tautan #anchor dari halaman lain. */
        anchor?: string;
        defaultOpen?: boolean;
    }>(),
    { defaultOpen: true },
);

const slots = useSlots();
const open = ref(props.defaultOpen);

const fullKey = `proj.sec.${props.storageKey}`;

onMounted(() => {
    try {
        const stored = localStorage.getItem(fullKey);

        if (stored !== null) {
            open.value = stored === '1';
        }
    } catch {
        // Mode privat memblokir localStorage — pakai nilai bawaan saja.
    }
});

watch(open, (value) => {
    try {
        localStorage.setItem(fullKey, value ? '1' : '0');
    } catch {
        // Diabaikan: posisi buka/tutup bukan data yang harus bertahan.
    }
});
</script>

<template>
    <section
        :id="anchor"
        class="scroll-mt-6 overflow-hidden rounded-card border border-line bg-surface shadow-card"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 border-b border-line bg-surface-muted/70 px-5 py-3.5 text-left text-sm font-semibold text-ink transition-colors hover:bg-surface-muted"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="flex items-center gap-3">
                {{ title }}
                <slot name="titleExtra" />
            </span>

            <svg
                class="h-4 w-4 shrink-0 transition-transform"
                :class="open ? '' : 'rotate-180'"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m18 15-6-6-6 6" />
            </svg>
        </button>

        <div v-show="open">
            <slot name="raw">
                <div class="p-5">
                    <slot />
                </div>
            </slot>

            <div v-if="slots.footer" class="flex items-center justify-between gap-3 border-t border-line p-4">
                <slot name="footer" />
            </div>
        </div>
    </section>
</template>
