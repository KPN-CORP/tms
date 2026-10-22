<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiField, UiInput } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/composables/useLocale';

defineOptions({ layout: null });

defineProps<{ status: string | null }>();

const { t } = useLocale();

const form = useForm({ email: '' });

const submit = (): void => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head :title="t.auth.forgotTitle" />

    <AuthLayout :title="t.auth.forgotTitle" :subtitle="t.auth.forgotSubtitle">
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

            <UiButton type="submit" block size="lg" :loading="form.processing">
                {{ t.auth.sendResetLink }}
            </UiButton>
        </form>
    </AuthLayout>
</template>
