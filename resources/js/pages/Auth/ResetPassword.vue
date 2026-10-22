<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiField, UiInput } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/composables/useLocale';

defineOptions({ layout: null });

const props = defineProps<{ email: string | null; token: string }>();

const { t } = useLocale();

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="t.auth.resetTitle" />

    <AuthLayout :title="t.auth.resetTitle">
        <form class="space-y-4" @submit.prevent="submit">
            <UiField :label="t.auth.email" :error="form.errors.email">
                <UiInput v-model="form.email" type="email" autocomplete="username" />
            </UiField>

            <UiField :label="t.auth.newPassword" :error="form.errors.password">
                <UiInput v-model="form.password" type="password" autocomplete="new-password" />
            </UiField>

            <UiField :label="t.auth.confirmPasswordLabel" :error="form.errors.password_confirmation">
                <UiInput v-model="form.password_confirmation" type="password" autocomplete="new-password" />
            </UiField>

            <UiButton type="submit" block size="lg" :loading="form.processing">{{ t.auth.resetPassword }}</UiButton>
        </form>
    </AuthLayout>
</template>
