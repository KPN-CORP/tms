<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiField, UiInput } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/composables/useLocale';

// Halaman tamu: tanpa sidebar/topbar. AuthLayout dipakai di template agar
// judulnya bisa dikirim sebagai prop.
defineOptions({ layout: null });

defineProps<{ canResetPassword: boolean; canDevLogin: boolean; status: string | null }>();

/**
 * Masuk lewat tabel users hcis. LoginRequest mencoba Auth::attempt dulu, lalu
 * HcisAuthService untuk hashing lama — tidak ada yang berubah di sisi ini.
 */
const { t } = useLocale();

const form = useForm({ email: '', password: '', remember: false });

const submit = (): void => {
    form.post(route('login'), { onFinish: () => form.reset('password') });
};
</script>

<template>
    <Head :title="t.auth.signInHead" />

    <AuthLayout :title="t.auth.signInTitle" :subtitle="t.auth.signInSubtitle">
        <p
            v-if="status"
            class="mb-4 rounded-control bg-success-soft px-4 py-3 text-sm font-medium text-success-ink"
        >
            {{ status }}
        </p>

        <form class="space-y-4" @submit.prevent="submit">
            <UiField :label="t.auth.email" :error="form.errors.email">
                <UiInput v-model="form.email" type="email" autocomplete="username" />
            </UiField>

            <UiField :label="t.auth.password" :error="form.errors.password">
                <UiInput v-model="form.password" type="password" autocomplete="current-password" />
            </UiField>

            <div class="flex items-center justify-between gap-3">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-muted">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="rounded border-line-strong text-brand focus:ring-brand/40"
                    />
                    {{ t.auth.rememberMe }}
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-ink-subtle underline-offset-2 hover:underline"
                >
                    {{ t.auth.forgotPassword }}
                </Link>
            </div>

            <UiButton type="submit" block size="lg" :loading="form.processing">{{ t.auth.signIn }}</UiButton>
        </form>

        <!-- Jalan masuk lokal/QA; server menyembunyikannya bila kunci kosong. -->
        <div v-if="canDevLogin" class="mt-6 border-t border-line pt-4 text-center">
            <Link
                :href="route('dev.login')"
                class="text-sm text-ink-subtle underline-offset-2 hover:underline"
            >
                {{ t.auth.developerLogin }}
            </Link>
        </div>
    </AuthLayout>
</template>
