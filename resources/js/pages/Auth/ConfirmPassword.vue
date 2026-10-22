<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiField, UiInput } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/composables/useLocale';

defineOptions({ layout: null });

const { t } = useLocale();

const form = useForm({ password: '' });

const submit = (): void => {
    form.post(route('password.confirm'), { onFinish: () => form.reset() });
};
</script>

<template>
    <Head :title="t.auth.confirmTitle" />

    <AuthLayout :title="t.auth.confirmTitle" :subtitle="t.auth.confirmSubtitle">
        <form class="space-y-4" @submit.prevent="submit">
            <UiField :label="t.auth.password" :error="form.errors.password">
                <UiInput v-model="form.password" type="password" autocomplete="current-password" />
            </UiField>

            <UiButton type="submit" block size="lg" :loading="form.processing">{{ t.auth.confirm }}</UiButton>
        </form>
    </AuthLayout>
</template>
