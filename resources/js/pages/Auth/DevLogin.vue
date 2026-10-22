<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiField, UiInput } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/composables/useLocale';

// Halaman tamu: tanpa sidebar/topbar.
defineOptions({ layout: null });

/**
 * Langkah 1 developer login — verifikasi kunci bersama. Kuncinya dicek di
 * server (DevLoginController), bukan di sini.
 */
const { t } = useLocale();

const form = useForm({ access_key: '' });

const submit = (): void => {
    form.post(route('dev.login.store'), { onFinish: () => form.reset('access_key') });
};
</script>

<template>
    <Head :title="t.devLogin.head" />

    <AuthLayout :title="t.devLogin.title" :subtitle="t.devLogin.subtitle">
        <p class="mb-4 rounded-control bg-warning-soft px-4 py-3 text-sm font-medium text-warning-ink">
            {{ t.devLogin.warning }}
        </p>

        <form class="space-y-4" @submit.prevent="submit">
            <UiField :label="t.devLogin.accessKey" :error="form.errors.access_key">
                <UiInput
                    v-model="form.access_key"
                    type="password"
                    autocomplete="off"
                    :placeholder="t.devLogin.accessKeyPlaceholder"
                />
            </UiField>

            <UiButton type="submit" block size="lg" :loading="form.processing">
                {{ t.devLogin.continue }}
            </UiButton>
        </form>

        <div class="mt-6 border-t border-line pt-4 text-center">
            <Link
                :href="route('login')"
                class="text-sm text-ink-subtle underline-offset-2 hover:underline"
            >
                {{ t.devLogin.backToSignIn }}
            </Link>
        </div>
    </AuthLayout>
</template>
