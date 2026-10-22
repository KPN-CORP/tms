<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiField, UiInput } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/composables/useLocale';

defineOptions({ layout: null });

const { t } = useLocale();

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

const submit = (): void => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="t.register.head" />

    <AuthLayout :title="t.register.title">
        <form class="space-y-4" @submit.prevent="submit">
            <UiField :label="t.fields.name" :error="form.errors.name">
                <UiInput v-model="form.name" autocomplete="name" />
            </UiField>

            <UiField :label="t.auth.email" :error="form.errors.email">
                <UiInput v-model="form.email" type="email" autocomplete="username" />
            </UiField>

            <UiField :label="t.auth.password" :error="form.errors.password">
                <UiInput v-model="form.password" type="password" autocomplete="new-password" />
            </UiField>

            <UiField :label="t.auth.confirmPasswordLabel" :error="form.errors.password_confirmation">
                <UiInput v-model="form.password_confirmation" type="password" autocomplete="new-password" />
            </UiField>

            <UiButton type="submit" block size="lg" :loading="form.processing">
                {{ t.register.submit }}
            </UiButton>

            <p class="text-center text-sm text-ink-subtle">
                {{ t.register.alreadyRegistered }}
                <Link :href="route('login')" class="text-brand underline-offset-2 hover:underline">
                    {{ t.auth.signIn }}
                </Link>
            </p>
        </form>
    </AuthLayout>
</template>
