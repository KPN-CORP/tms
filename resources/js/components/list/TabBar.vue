<script setup lang="ts">
import { useLabels } from '@/lib/labels';

defineProps<{
    tabs: Array<{ key: string; label: string; count: number }>;
    active: string;
}>();

const emit = defineEmits<{ select: [key: string] }>();

// Judul tab datang dari controller sebagai key + teks Inggris; diterjemahkan
// di sini sekali, jadi setiap halaman daftar ikut tanpa perubahan apa pun.
// Dinamai ulang: `tab` sudah dipakai sebagai variabel perulangan di template.
const { tab: tabLabel } = useLabels();
</script>

<template>
    <!-- Tablist: panah kiri/kanan berpindah tab bagi pengguna papan ketik. -->
    <div class="flex flex-wrap items-center gap-1 border-b border-line" role="tablist">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="active === tab.key"
            class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors"
            :class="
                active === tab.key
                    ? 'border-brand font-semibold text-brand'
                    : 'border-transparent text-ink-subtle hover:border-line hover:text-ink-muted'
            "
            @click="emit('select', tab.key)"
        >
            {{ tabLabel(tab.key, tab.label) }}

            <span
                class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold"
                :class="active === tab.key ? 'bg-brand/10 text-brand' : 'bg-neutral-soft text-ink-subtle'"
            >
                {{ tab.count }}
            </span>
        </button>
    </div>
</template>
