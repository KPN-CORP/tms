<script setup lang="ts">
import { computed, toRef } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiButton, UiCard, UiField, UiFileUpload, UiInput, UiTextarea } from '@/components/ui';
import OrgCascadeFields from './OrgCascadeFields.vue';
import AttachmentList from './AttachmentList.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useLocale } from '@/composables/useLocale';
import type { EmployeeInfo, IdeaAttachment, IdeaFormValues } from './types';

const props = defineProps<{
    /** URL tujuan submit. */
    action: string;
    method: 'post' | 'put';
    values: IdeaFormValues;
    employee: EmployeeInfo;
    businessUnits: string[];
    attachments?: IdeaAttachment[];
    /** URL hapus lampiran yang sudah tersimpan (mode edit). */
    ideaId?: number;
}>();

/**
 * Satu form, dua aksi: "Save as Draft" dan "Submit Idea". Nilai `action` yang
 * membedakannya — sama seperti tombol bernama di versi Blade, karena server
 * membaca `action` untuk menentukan status dan ketatnya validasi.
 */
const form = useForm<IdeaFormValues & { action: 'draft' | 'submit'; attachments: File[] }>({
    ...props.values,
    action: 'draft',
    attachments: [],
});

const { confirm } = useConfirm();
const { t } = useLocale();

const businessUnit = toRef(form, 'business_unit');

const submit = async (action: 'draft' | 'submit'): Promise<void> => {
    if (action === 'submit') {
        const ok = await confirm({
            title: t.value.ideas.submitTitle,
            message: t.value.ideas.submitMessage,
            confirmLabel: t.value.ideas.submitConfirm,
        });

        if (!ok) {
            return;
        }
    }

    form.action = action;

    // Inertia mengirim multipart saat ada File; method spoofing diurus `_method`.
    form.transform((data) => ({ ...data, _method: props.method }));
    form.post(props.action, { forceFormData: true, preserveScroll: true });
};

const busy = computed(() => form.processing);

/**
 * Validasi lampiran datang per indeks (`attachments.0`, `attachments.1`, …),
 * yang tidak muat di tipe error useForm. Cukup tampilkan yang pertama.
 */
const attachmentError = computed<string | undefined>(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return Object.entries(errors).find(([key]) => key.startsWith('attachments'))?.[1];
});
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit('draft')">
        <UiCard :title="t.ideas.employeeInformation" :description="t.ideas.employeeInformationHint">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-for="entry in [
                        { label: t.fields.employeeId, value: employee.employee_id },
                        { label: t.fields.fullName, value: employee.name },
                        { label: t.fields.businessUnit, value: employee.business_unit },
                        { label: t.fields.department, value: employee.department },
                    ]"
                    :key="entry.label"
                    class="rounded-control border border-line px-4 py-3"
                >
                    <dt class="text-xs uppercase tracking-wide text-ink-subtle">{{ entry.label }}</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ entry.value || '-' }}</dd>
                </div>
            </dl>
        </UiCard>

        <UiCard :title="t.ideas.ideaDetail">
            <div class="space-y-5">
                <UiField :label="t.ideas.ideaName" required :error="form.errors.idea_name">
                    <UiInput v-model="form.idea_name" :placeholder="t.ideas.ideaNamePlaceholder" />
                </UiField>

                <UiField
                    :label="t.ideas.problem"
                    required
                    :error="form.errors.problem"
                    :hint="t.ideas.problemHint"
                >
                    <UiTextarea v-model="form.problem" :rows="3" />
                </UiField>

                <UiField
                    :label="t.ideas.descriptionLabel"
                    required
                    :error="form.errors.description"
                    :hint="t.ideas.descriptionHint"
                >
                    <UiTextarea v-model="form.description" :rows="4" />
                </UiField>

                <UiField
                    :label="t.ideas.expectedOutcome"
                    required
                    :error="form.errors.expected_outcome"
                    :hint="t.ideas.expectedOutcomeHint"
                >
                    <UiTextarea v-model="form.expected_outcome" :rows="3" />
                </UiField>

                <OrgCascadeFields
                    v-model:business-unit="form.business_unit"
                    v-model:department="form.department"
                    v-model:company="form.company"
                    v-model:location="form.location"
                    :business-units="businessUnits"
                    :errors="form.errors"
                />

                <div>
                    <p class="mb-1 block text-xs font-medium text-ink-label">{{ t.fields.supportingDocuments }}</p>

                    <AttachmentList
                        v-if="attachments?.length && ideaId"
                        :attachments="attachments"
                        :idea-id="ideaId"
                        class="mb-3"
                    />

                    <UiFileUpload v-model="form.attachments" :error="attachmentError" />
                </div>
            </div>
        </UiCard>

        <div class="flex flex-wrap justify-end gap-3">
            <UiButton variant="secondary" :href="route('ideas.index')">{{ t.common.cancel }}</UiButton>
            <UiButton variant="secondary" :loading="busy && form.action === 'draft'" @click="submit('draft')">
                {{ t.ideas.saveAsDraft }}
            </UiButton>
            <UiButton :loading="busy && form.action === 'submit'" @click="submit('submit')">
                {{ t.ideas.submitIdea }}
            </UiButton>
        </div>
    </form>
</template>
