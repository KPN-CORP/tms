<script setup lang="ts">
import { computed, useSlots } from 'vue';
import UiLanguageSwitcher from '@/components/ui/UiLanguageSwitcher.vue';

/**
 * Cangkang halaman tamu (login, reset password, dsb) — tanpa sidebar/topbar.
 *
 * Bentuknya mengikuti kpn-tmp: kartu putih di atas gradasi slate, dengan tanda
 * brand berupa kotak merah bata membulat.
 */
withDefaults(defineProps<{ title?: string; subtitle?: string }>(), {});

const slots = useSlots();
const hasHeading = computed(() => Boolean(slots.heading));
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-gradient-to-b from-slate-100 to-slate-200 px-4 py-10 dark:from-surface-sunken dark:to-surface-sunken"
    >
        <div class="w-full max-w-md rounded-2xl bg-surface p-8 shadow-lg ring-1 ring-black/5 sm:p-10">
            <!-- Bahasa bisa diganti SEBELUM masuk: halaman ini tidak punya
                 topbar, jadi pengalihnya ditempel di pojok kartu. -->
            <div class="mb-2 flex justify-end">
                <UiLanguageSwitcher align="right" />
            </div>

            <div v-if="title || hasHeading" class="mb-7 text-center">
                <slot name="heading">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand text-2xl font-bold text-white shadow-card"
                        aria-hidden="true"
                    >
                        T
                    </div>

                    <h1 class="text-2xl font-bold text-brand">{{ title }}</h1>

                    <p v-if="subtitle" class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-ink-subtle">
                        {{ subtitle }}
                    </p>
                </slot>
            </div>

            <slot />
        </div>
    </div>
</template>
