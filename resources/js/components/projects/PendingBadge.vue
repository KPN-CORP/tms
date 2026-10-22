<script setup lang="ts">
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import type { PendingChange } from './types';

/**
 * Penanda nilai yang sudah diisi tapi belum berlaku.
 *
 * Project yang sedang berjalan tidak menerima perubahan langsung: suntingan
 * Leader ditahan sebagai change request (ProjectChangeStagingService) dan baru
 * diterapkan setelah disetujui. Tanpa penanda ini isian pengguna seolah hilang.
 */
const props = defineProps<{ pending: PendingChange }>();

const { t } = useLocale();

const label = computed(() =>
    props.pending.status === 'pending'
        ? t.value.projects.pending.waiting
        : t.value.projects.pending.notSubmitted,
);
</script>

<template>
    <span
        class="ml-1 inline-flex items-center rounded-full bg-warning-soft px-1.5 py-0.5 text-[10px] font-semibold text-warning-ink"
    >
        {{ label }}
    </span>
</template>
