<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiBadge, UiCard, UiDateTime, UiPageHeader } from '@/components/ui';
import AttachmentList from '@/components/ideas/AttachmentList.vue';
import ReadOnlyField from '@/components/ui/ReadOnlyField.vue';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { ideaTone } from '@/lib/status';
import type { IdeaAttachment, EmployeeInfo } from '@/components/ideas/types';
import type { IdeaStatus } from '@/types/models';
import type { SharedProps } from '@/types/inertia';

interface Approval {
    id: number;
    layer: number;
    decision: string;
    note: string | null;
    decided_by: string | null;
    on_behalf_of: string | null;
    created_at: string | null;
}

const props = defineProps<{
    idea: {
        id: number;
        idea_id: string;
        idea_name: string | null;
        status: IdeaStatus;
        status_label: string;
        problem: string | null;
        description: string | null;
        expected_outcome: string | null;
        business_unit_name: string | null;
        department_name: string | null;
        company_name: string | null;
        location_name: string | null;
        current_layer: number | null;
        modified_at: string | null;
        created_at: string | null;
    };
    submitter: EmployeeInfo & { employee_id: string | null };
    attachments: IdeaAttachment[];
    approvals: Approval[];
}>();

const page = usePage<SharedProps>();
const { t } = useLocale();
const { ideaStatus, decision } = useLabels();

const tone = computed(() => ideaTone(props.idea.status));

/** Banner per status — menjelaskan apa yang sedang terjadi pada ide ini. */
const message = computed(() => t.value.ideas.messages[props.idea.status] ?? '');

const statusText = computed(() => ideaStatus(props.idea.status, props.idea.status_label));

// Dibuka dari menu Report → tombol kembali mengarah ke sana, bukan My Ideas.
// Report masih halaman Blade, jadi tautannya harus navigasi biasa — lihat
// AppLink dan App\Support\Navigation\InertiaRoutes.
const backTo = computed(() =>
    page.props.reportContext
        ? { label: t.value.reports.title, href: route('reports.index', { type: 'ideas' }), external: true }
        : { label: t.value.ideas.indexTitle, href: route('ideas.index'), external: false },
);

const ACCENTS: Record<string, string> = {
    neutral: 'border-l-neutral',
    info: 'border-l-info',
    success: 'border-l-success',
    warning: 'border-l-warning',
    danger: 'border-l-danger',
    brand: 'border-l-brand',
    accent: 'border-l-purple-500',
};
</script>

<template>
    <Head :title="idea.idea_name || idea.idea_id" />

    <UiPageHeader
        :title="tr(t.ideas.showTitle, { name: idea.idea_name || t.common.untitled })"
        :back-href="backTo.href"
        :back-label="backTo.label"
        :back-external="backTo.external"
    >
        <template #actions>
            <UiBadge :tone="tone" size="md" dot>{{ statusText }}</UiBadge>
        </template>
    </UiPageHeader>

    <div class="space-y-5">
        <div
            class="rounded-card border-l-4 bg-surface px-5 py-4 shadow-card ring-1 ring-line/70"
            :class="ACCENTS[tone]"
        >
            <p class="font-semibold text-ink">{{ statusText }}</p>
            <p class="mt-0.5 text-sm text-ink-subtle">{{ message }}</p>
        </div>

        <UiCard :title="t.ideas.submittedBy">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="entry in [
                        { label: t.fields.employeeId, value: submitter.employee_id },
                        { label: t.fields.fullName, value: submitter.name },
                        { label: t.fields.businessUnit, value: submitter.business_unit },
                        { label: t.fields.department, value: submitter.department },
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
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex items-center gap-4">
                        <span class="w-32 shrink-0 text-sm font-semibold text-ink-muted">{{ t.fields.ideaId }}</span>
                        <span class="font-mono text-sm text-brand">{{ idea.idea_id }}</span>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="w-32 shrink-0 text-sm font-semibold text-ink-muted">{{ t.fields.submissionDate }}</span>
                        <UiDateTime :value="idea.created_at" mode="date" class="text-sm text-ink" />
                    </div>
                </div>

                <ReadOnlyField :label="t.ideas.ideaName" :value="idea.idea_name" />
                <ReadOnlyField :label="t.ideas.problem" :value="idea.problem" multiline />
                <ReadOnlyField :label="t.ideas.descriptionLabel" :value="idea.description" multiline />
                <ReadOnlyField :label="t.ideas.expectedOutcome" :value="idea.expected_outcome" multiline />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <ReadOnlyField :label="t.ideas.targetedBusinessUnit" :value="idea.business_unit_name" />
                    <ReadOnlyField :label="t.ideas.targetedUnit" :value="idea.department_name" />
                    <ReadOnlyField :label="t.ideas.targetedCompany" :value="idea.company_name" />
                    <ReadOnlyField :label="t.ideas.targetedLocation" :value="idea.location_name" />
                </div>

                <div>
                    <p class="mb-1 block text-xs font-medium text-ink-label">{{ t.fields.supportingDocuments }}</p>

                    <AttachmentList
                        v-if="attachments.length"
                        :attachments="attachments"
                        :idea-id="idea.id"
                        readonly
                    />
                    <p v-else class="text-sm text-ink-subtle">{{ t.common.noDocuments }}</p>
                </div>
            </div>
        </UiCard>

        <UiCard v-if="approvals.length" :title="t.ideas.reviewHistory">
            <ol class="divide-y divide-line">
                <li v-for="approval in approvals" :key="approval.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <UiBadge :tone="approval.decision === 'approve' ? 'success' : 'danger'" class="shrink-0">
                        {{ tr(t.ideas.layer, { layer: approval.layer }) }} · {{ decision(approval.decision) }}
                    </UiBadge>

                    <div class="min-w-0 text-sm">
                        <p class="text-ink">
                            <span class="font-semibold">{{ approval.decided_by ?? '-' }}</span>

                            <!-- Keputusan lewat konteks Report dicatat atas nama pemegang layer. -->
                            <template v-if="approval.on_behalf_of">
                                <span class="text-ink-subtle"> {{ t.ideas.onBehalfOf }} </span>
                                <span class="font-semibold">{{ approval.on_behalf_of }}</span>
                                <span class="text-ink-subtle"> ({{ tr(t.ideas.layer, { layer: approval.layer }) }})</span>
                            </template>

                            <span class="text-ink-subtle"> · <UiDateTime :value="approval.created_at" /></span>
                        </p>

                        <p v-if="approval.note" class="mt-0.5 whitespace-pre-line text-ink-muted">{{ approval.note }}</p>
                    </div>
                </li>
            </ol>
        </UiCard>
    </div>
</template>
