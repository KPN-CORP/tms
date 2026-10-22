<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiPageHeader } from '@/components/ui';
import IdeaForm from '@/components/ideas/IdeaForm.vue';
import { useLocale } from '@/composables/useLocale';
import type { EmployeeInfo, IdeaFormValues } from '@/components/ideas/types';

defineProps<{
    values: IdeaFormValues;
    employee: EmployeeInfo;
    businessUnits: string[];
}>();

const { t } = useLocale();
</script>

<template>
    <Head :title="t.ideas.createHead" />

    <UiPageHeader
        :title="t.ideas.createHead"
        :description="t.ideas.createDescription"
        :back-href="route('ideas.index')"
        :back-label="t.ideas.indexTitle"
    />

    <IdeaForm
        :action="route('ideas.store')"
        method="post"
        :values="values"
        :employee="employee"
        :business-units="businessUnits"
    />
</template>
