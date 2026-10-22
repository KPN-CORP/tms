<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        value: string | number | null | undefined;
        /** Pertahankan baris baru untuk teks panjang (problem, description). */
        multiline?: boolean;
        fallback?: string;
    }>(),
    { fallback: '-' },
);
</script>

<template>
    <div>
        <p class="mb-1 text-xs font-medium text-ink-label">{{ label }}</p>

        <!-- Tampil seperti input yang dinonaktifkan supaya halaman detail dan
             halaman form terbaca sebagai satu rangkaian. -->
        <div
            class="rounded-control border border-line bg-surface-muted px-4 py-2 text-sm text-ink-muted"
            :class="multiline && 'min-h-16 whitespace-pre-line leading-relaxed'"
        >
            {{ value || fallback }}
        </div>
    </div>
</template>
