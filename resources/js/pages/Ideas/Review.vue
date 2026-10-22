<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    ReadOnlyField,
    UiBadge,
    UiButton,
    UiCard,
    UiDateTime,
    UiField,
    UiPageHeader,
    UiTextarea,
} from '@/components/ui';
import AttachmentList from '@/components/ideas/AttachmentList.vue';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { ideaTone } from '@/lib/status';
import type { EmployeeInfo, IdeaAttachment } from '@/components/ideas/types';
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
        current_layer: number | null;
    };
    submitter: EmployeeInfo & { employee_id: string | null };
    attachments: IdeaAttachment[];
    approvals: Approval[];
    canReview: boolean;
    onBehalfOf: { id: number; name: string } | null;
    urls: { approve: string; reject: string };
}>();

const page = usePage<SharedProps>();

/**
 * Satu catatan dipakai oleh kedua keputusan, sama seperti versi Blade (di sana
 * dua form berbagi satu <textarea>). `from=report` ikut dikirim agar konteks
 * Report tidak hilang — tanpa izin override.role penanda itu tidak berarti apa
 * pun di server.
 */
const form = useForm<{ note: string; from?: string }>({
    note: '',
    ...(page.props.reportContext ? { from: 'report' } : {}),
});

const { confirm } = useConfirm();
const { t } = useLocale();
const { ideaStatus, decision: decisionLabel } = useLabels();

const deciding = ref<'approve' | 'reject' | null>(null);

/** Pemegang override.role memutus menggantikan committee layer aktif. */
const actingOnBehalf = computed(() => !props.canReview && props.onBehalfOf !== null);
const canDecide = computed(() => props.canReview || actingOnBehalf.value);

// Report masih halaman Blade, jadi tautannya harus navigasi biasa — lihat
// AppLink dan App\Support\Navigation\InertiaRoutes.
const backTo = computed(() =>
    page.props.reportContext
        ? { label: t.value.reports.title, href: route('reports.index', { type: 'ideas' }), external: true }
        : { label: t.value.ideas.taskBoxTitle, href: route('ideas.taskbox'), external: false },
);

const decide = async (decision: 'approve' | 'reject'): Promise<void> => {
    const layer = props.idea.current_layer;

    const ok = await confirm(
        decision === 'approve'
            ? {
                  title: t.value.ideas.approveTitle,
                  message: tr(t.value.ideas.approveMessage, { layer: layer ?? '' }),
                  confirmLabel: t.value.ideas.approveConfirm,
              }
            : {
                  title: t.value.ideas.rejectTitle,
                  message: t.value.ideas.rejectMessage,
                  confirmLabel: t.value.ideas.rejectConfirm,
                  tone: 'danger',
              },
    );

    if (!ok) {
        return;
    }

    deciding.value = decision;
    form.post(decision === 'approve' ? props.urls.approve : props.urls.reject, {
        onFinish: () => (deciding.value = null),
    });
};
</script>

<template>
    <Head :title="tr(t.ideas.reviewHead, { id: idea.idea_id })" />

    <UiPageHeader
        :title="tr(t.ideas.showTitle, { name: idea.idea_name || t.common.untitled })"
        :back-href="backTo.href"
        :back-label="backTo.label"
        :back-external="backTo.external"
    >
        <template #actions>
            <UiBadge tone="neutral" size="md">
                {{ tr(t.ideas.layer, { layer: idea.current_layer ?? '' }) }}
            </UiBadge>
            <UiBadge :tone="ideaTone(idea.status)" size="md" dot>
                {{ ideaStatus(idea.status, idea.status_label) }}
            </UiBadge>
        </template>
    </UiPageHeader>

    <div class="space-y-5">
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
                <div class="flex items-center gap-4">
                    <span class="w-28 shrink-0 text-sm font-semibold text-ink-muted">{{ t.fields.ideaId }}</span>
                    <span class="font-mono text-sm text-brand">{{ idea.idea_id }}</span>
                </div>

                <ReadOnlyField :label="t.ideas.ideaName" :value="idea.idea_name" />
                <ReadOnlyField :label="t.ideas.problem" :value="idea.problem" multiline />
                <ReadOnlyField :label="t.ideas.descriptionLabel" :value="idea.description" multiline />
                <ReadOnlyField :label="t.ideas.expectedOutcome" :value="idea.expected_outcome" multiline />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <ReadOnlyField :label="t.ideas.targetedBusinessUnit" :value="idea.business_unit_name" />
                    <ReadOnlyField :label="t.ideas.targetedUnit" :value="idea.department_name" />
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

        <UiCard :title="t.ideas.approvalHistory">
            <ol v-if="approvals.length" class="divide-y divide-line">
                <li v-for="approval in approvals" :key="approval.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <UiBadge :tone="approval.decision === 'approve' ? 'success' : 'danger'" class="shrink-0">
                        {{ tr(t.ideas.layer, { layer: approval.layer }) }} · {{ decisionLabel(approval.decision) }}
                    </UiBadge>

                    <div class="min-w-0 text-sm">
                        <p class="text-ink">
                            <span class="font-semibold">{{ approval.decided_by ?? '-' }}</span>

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

            <p v-else class="text-sm text-ink-subtle">{{ t.ideas.noDecisionYet }}</p>
        </UiCard>

        <UiCard
            v-if="canDecide"
            :title="
                actingOnBehalf
                    ? tr(t.ideas.decisionOnBehalf, {
                          name: onBehalfOf?.name ?? '',
                          layer: idea.current_layer ?? '',
                      })
                    : tr(t.ideas.yourDecision, { layer: idea.current_layer ?? '' })
            "
        >
            <div
                v-if="actingOnBehalf"
                class="mb-4 rounded-control bg-warning-soft px-4 py-3 text-sm text-warning-ink ring-1 ring-warning/25"
            >
                {{ t.ideas.onBehalfNoticeLead }}
                <span class="font-semibold">
                    {{ tr(t.ideas.onBehalfNoticeStrong, { name: onBehalfOf?.name ?? '' }) }}
                </span>
                {{ tr(t.ideas.onBehalfNoticeTail, { layer: idea.current_layer ?? '' }) }}
            </div>

            <UiField :label="t.fields.noteOptional" :error="form.errors.note">
                <UiTextarea v-model="form.note" :rows="2" />
            </UiField>

            <div class="mt-4 flex flex-wrap gap-3">
                <UiButton
                    variant="success"
                    :loading="deciding === 'approve'"
                    :disabled="form.processing"
                    @click="decide('approve')"
                >
                    {{ t.status.decision.approve }}
                </UiButton>

                <UiButton
                    variant="danger"
                    :loading="deciding === 'reject'"
                    :disabled="form.processing"
                    @click="decide('reject')"
                >
                    {{ t.status.decision.reject }}
                </UiButton>
            </div>
        </UiCard>

        <UiCard v-else>
            <p class="text-sm text-ink-subtle">{{ t.ideas.viewOnly }}</p>
        </UiCard>
    </div>
</template>
