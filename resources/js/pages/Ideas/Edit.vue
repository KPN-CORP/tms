<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiPageHeader } from '@/components/ui';
import IdeaForm from '@/components/ideas/IdeaForm.vue';
import { tr, useLocale } from '@/composables/useLocale';
import type { EmployeeInfo, IdeaAttachment, IdeaFormValues } from '@/components/ideas/types';

const props = defineProps<{
    idea: { id: number; idea_id: string; update_url: string };
    values: IdeaFormValues;
    employee: EmployeeInfo;
    businessUnits: string[];
    attachments: IdeaAttachment[];
}>();

const { t } = useLocale();

const heading = computed(() => tr(t.value.ideas.editHead, { id: props.idea.idea_id }));
</script>

<template>
    <Head :title="heading" />

    <UiPageHeader
        :title="heading"
        :description="t.ideas.editDescription"
        :back-href="route('ideas.index')"
        :back-label="t.ideas.indexTitle"
    />

    <IdeaForm
        :action="props.idea.update_url"
        method="put"
        :values="values"
        :employee="employee"
        :business-units="businessUnits"
        :attachments="attachments"
        :idea-id="props.idea.id"
    />
</template>
