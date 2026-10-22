<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/composables/useLocale';

defineOptions({ layout: null });

const props = defineProps<{ status: string | null }>();

const { t } = useLocale();

const form = useForm({});

const justSent = computed(() => props.status === 'verification-link-sent');

const resend = (): void => {
    form.post(route('verification.send'));
};

const logout = (): void => {
    form.post(route('logout'));
};
</script>

<template>
    <Head :title="t.auth.verifyHead" />

    <AuthLayout :title="t.auth.verifyTitle">
        <p class="text-sm text-ink-muted">{{ t.auth.verifyBody }}</p>

        <p
            v-if="justSent"
            class="mt-4 rounded-control bg-success-soft px-4 py-3 text-sm font-medium text-success-ink"
        >
            {{ t.auth.verifySent }}
        </p>

        <div class="mt-6 flex items-center justify-between gap-3">
            <UiButton :loading="form.processing" @click="resend">{{ t.auth.resendEmail }}</UiButton>
            <UiButton variant="ghost" @click="logout">{{ t.auth.logOut }}</UiButton>
        </div>
    </AuthLayout>
</template>
