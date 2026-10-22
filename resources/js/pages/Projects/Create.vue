<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    UiButton,
    UiCard,
    UiCombobox,
    UiField,
    UiInput,
    UiPageHeader,
    UiSelect,
    UiTextarea,
} from '@/components/ui';
import { tr, useLocale } from '@/composables/useLocale';
import type { SelectOption } from '@/types/models';

interface Values {
    idea_id: string;
    project_name: string;
    project_category_id: number | null;
    project_sponsor_id: string | null;
    project_leader_id: string | null;
    project_scope: string;
    expected_outcome: string;
    notes: string;
}

const props = defineProps<{
    idea: { idea_id: string; idea_name: string | null };
    isDraft: boolean;
    values: Values;
    labels: { sponsor: string | null; leader: string | null };
    categories: SelectOption[];
    searchUrl: string;
    backUrl: string;
}>();

/**
 * Satu form, dua aksi. `action=draft` melonggarkan validasi di server — itulah
 * sebabnya "Save as Draft" di versi Blade memakai `formnovalidate`; di sini
 * tidak ada validasi browser sama sekali, jadi cukup kirim nilai action-nya.
 */
const form = useForm<Values & { action: 'draft' | 'create' }>({
    ...props.values,
    action: 'create',
});

const { t } = useLocale();

const submitting = ref<'draft' | 'create' | null>(null);

const submit = (action: 'draft' | 'create'): void => {
    form.action = action;
    submitting.value = action;

    form.post(route('projects.store'), {
        preserveScroll: true,
        onFinish: () => (submitting.value = null),
    });
};

const subtitle = computed(() =>
    tr(t.value.projects.createFromIdea, {
        id: props.idea.idea_id,
        name: props.idea.idea_name ?? t.value.common.untitled,
    }),
);
</script>

<template>
    <Head :title="t.projects.createHead" />

    <UiPageHeader :title="t.projects.createHead" :description="subtitle" :back-href="backUrl" />

    <div class="space-y-5">
        <div
            v-if="isDraft"
            class="rounded-card bg-warning-soft px-4 py-3 text-sm text-warning-ink ring-1 ring-warning/25"
        >
            {{ t.projects.draftNoticeLead }}
            <span class="font-semibold">{{ t.projects.draftNoticeDraft }}</span>
            {{ t.projects.draftNoticeTail }}
            <span class="font-semibold">{{ t.projects.draftNoticeAction }}</span>
            {{ t.projects.draftNoticeEnd }}
        </div>

        <UiCard>
            <form class="space-y-5" @submit.prevent="submit('create')">
                <UiField :label="t.projects.projectName" required :error="form.errors.project_name">
                    <UiInput v-model="form.project_name" />
                </UiField>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <UiField :label="t.projects.projectCategory" required :error="form.errors.project_category_id">
                        <UiSelect
                            v-model="form.project_category_id"
                            :options="categories"
                            :placeholder="t.projects.selectCategory"
                        />
                    </UiField>

                    <UiField :label="t.projects.projectSponsor" required :error="form.errors.project_sponsor_id">
                        <UiCombobox
                            v-model="form.project_sponsor_id"
                            :search-url="searchUrl"
                            :initial-label="labels.sponsor"
                            :placeholder="t.projects.selectSponsor"
                        />
                    </UiField>

                    <UiField :label="t.projects.projectLeader" required :error="form.errors.project_leader_id">
                        <UiCombobox
                            v-model="form.project_leader_id"
                            :search-url="searchUrl"
                            :initial-label="labels.leader"
                            :placeholder="t.projects.selectLeader"
                        />
                    </UiField>
                </div>

                <UiField :label="t.projects.projectScope" required :error="form.errors.project_scope">
                    <UiTextarea
                        v-model="form.project_scope"
                        :rows="3"
                        :placeholder="t.projects.projectScopePlaceholder"
                    />
                </UiField>

                <UiField :label="t.projects.expectedOutcome" required :error="form.errors.expected_outcome">
                    <UiTextarea v-model="form.expected_outcome" :rows="3" />
                </UiField>

                <UiField :label="t.projects.notes" :hint="t.common.optional" :error="form.errors.notes">
                    <UiTextarea v-model="form.notes" :rows="3" :placeholder="t.projects.notesPlaceholder" />
                </UiField>

                <div class="flex flex-wrap justify-end gap-3 pt-1">
                    <UiButton variant="secondary" :href="backUrl">{{ t.common.cancel }}</UiButton>

                    <UiButton
                        variant="secondary"
                        :loading="submitting === 'draft'"
                        :disabled="form.processing"
                        @click="submit('draft')"
                    >
                        {{ t.projects.saveAsDraft }}
                    </UiButton>

                    <UiButton type="submit" :loading="submitting === 'create'" :disabled="form.processing">
                        {{ t.projects.createProjectButton }}
                    </UiButton>
                </div>
            </form>
        </UiCard>
    </div>
</template>
