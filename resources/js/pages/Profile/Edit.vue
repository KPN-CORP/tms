<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiCard, UiField, UiInput, UiModal, UiPageHeader } from '@/components/ui';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{
    user: { name: string; email: string };
    status: string | null;
}>();

/**
 * Catatan: User hidup di koneksi kpncorp yang READ-ONLY — model memblokir
 * penulisan dan delete() dibuat tidak melakukan apa pun. Form ini tetap ada
 * karena bawaan Breeze, tapi perubahannya tidak akan tersimpan di hcis.
 */
const { t } = useLocale();

const profile = useForm({ name: props.user.name, email: props.user.email });

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deletion = useForm({ password: '' });

const confirmingDelete = ref(false);

const updateProfile = (): void => {
    profile.patch(route('profile.update'), { preserveScroll: true });
};

const updatePassword = (): void => {
    password.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => password.reset(),
        onError: () => password.reset('password', 'password_confirmation'),
    });
};

const destroy = (): void => {
    deletion.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => (confirmingDelete.value = false),
        onError: () => deletion.reset(),
    });
};
</script>

<template>
    <Head :title="t.profile.head" />

    <UiPageHeader :title="t.profile.head" :description="t.profile.description" />

    <div class="max-w-2xl space-y-5">
        <p
            v-if="status === 'profile-updated'"
            class="rounded-card bg-success-soft px-4 py-3 text-sm font-medium text-success-ink"
        >
            {{ t.profile.updated }}
        </p>

        <UiCard :title="t.profile.infoTitle" :description="t.profile.infoDescription">
            <form class="space-y-4" @submit.prevent="updateProfile">
                <UiField :label="t.fields.name" :error="profile.errors.name">
                    <UiInput v-model="profile.name" autocomplete="name" />
                </UiField>

                <UiField :label="t.auth.email" :error="profile.errors.email">
                    <UiInput v-model="profile.email" type="email" autocomplete="username" />
                </UiField>

                <div class="flex justify-end">
                    <UiButton type="submit" :loading="profile.processing">{{ t.common.save }}</UiButton>
                </div>
            </form>
        </UiCard>

        <UiCard :title="t.profile.passwordTitle" :description="t.profile.passwordDescription">
            <form class="space-y-4" @submit.prevent="updatePassword">
                <UiField :label="t.profile.currentPassword" :error="password.errors.current_password">
                    <UiInput v-model="password.current_password" type="password" autocomplete="current-password" />
                </UiField>

                <UiField :label="t.auth.newPassword" :error="password.errors.password">
                    <UiInput v-model="password.password" type="password" autocomplete="new-password" />
                </UiField>

                <UiField :label="t.auth.confirmPasswordLabel" :error="password.errors.password_confirmation">
                    <UiInput
                        v-model="password.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                    />
                </UiField>

                <div class="flex justify-end">
                    <UiButton type="submit" :loading="password.processing">{{ t.common.save }}</UiButton>
                </div>
            </form>
        </UiCard>

        <UiCard :title="t.profile.deleteTitle" :description="t.profile.deleteDescription">
            <UiButton variant="danger" @click="confirmingDelete = true">
                {{ t.profile.deleteAccount }}
            </UiButton>
        </UiCard>
    </div>

    <UiModal
        v-model="confirmingDelete"
        :title="t.profile.deleteModalTitle"
        :description="t.profile.deleteModalDescription"
        size="sm"
    >
        <UiField :label="t.auth.password" :error="deletion.errors.password">
            <UiInput v-model="deletion.password" type="password" autocomplete="current-password" />
        </UiField>

        <template #footer>
            <UiButton variant="secondary" @click="confirmingDelete = false">{{ t.common.cancel }}</UiButton>
            <UiButton variant="danger" :loading="deletion.processing" @click="destroy">
                {{ t.profile.deleteAccount }}
            </UiButton>
        </template>
    </UiModal>
</template>
