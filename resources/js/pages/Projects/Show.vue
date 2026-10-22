<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ReadOnlyField, UiBadge, UiDateTime, UiPageHeader } from '@/components/ui';
import CollapsibleSection from '@/components/projects/CollapsibleSection.vue';
import LeadershipForm, { type LeadershipRights } from '@/components/projects/LeadershipForm.vue';
import PendingBadge from '@/components/projects/PendingBadge.vue';
import ChangeRequestPanel from '@/components/projects/ChangeRequestPanel.vue';
import TeamSection from '@/components/projects/sections/TeamSection.vue';
import ImplementationSection from '@/components/projects/sections/ImplementationSection.vue';
import IndicatorSection from '@/components/projects/sections/IndicatorSection.vue';
import BudgetSection from '@/components/projects/sections/BudgetSection.vue';
import AttachmentsSection from '@/components/projects/sections/AttachmentsSection.vue';
import HistorySections from '@/components/projects/sections/HistorySections.vue';
import BottomActions from '@/components/projects/sections/BottomActions.vue';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { projectTone } from '@/lib/status';
import type { SelectOption } from '@/types/models';
import type { SharedProps } from '@/types/inertia';
import type {
    ApprovalRow,
    BudgetRow,
    ChangeReview,
    IdeaSummary,
    ImplementationPlanRow,
    IndicatorRow,
    PendingChange,
    ProjectAbilities,
    ProjectAttachmentRow,
    ProjectDetail,
    RoleSlot,
    StatusLogRow,
    TeamMember,
    ShowUrls,
    UomGroup,
} from '@/components/projects/types';

const props = defineProps<{
    project: ProjectDetail;
    idea: IdeaSummary | null;
    abilities: ProjectAbilities;
    /** Actual tampil begitu proposal disetujui, di mana pun detail dibuka. */
    showActual: boolean;
    members: TeamMember[];
    roleSlots: RoleSlot[];
    plans: ImplementationPlanRow[];
    indicators: IndicatorRow[];
    budgets: BudgetRow[];
    budgetEmptyMessage: string;
    attachments: ProjectAttachmentRow[];
    approvals: ApprovalRow[];
    statusLogs: StatusLogRow[];
    changeReviews: ChangeReview[];
    /** Perubahan Leader yang masih menunggu persetujuan. */
    pendingLeadership: PendingChange | null;
    pendingLeaderName: string | null;
    pendingDraftCount: number;
    leadershipRights: LeadershipRights;
    ideaApprover: string | null;
    onBehalfOf: string | null;
    teamMembers: SelectOption[];
    uomGroups: UomGroup[];
    indicatorTypes: string[];
    memberErrors: string[];
    cancellation: { reason: string | null; by: string | null; at: string | null } | null;
    urls: ShowUrls;
}>();

const page = usePage<SharedProps>();
const { t } = useLocale();
const { projectStatus } = useLabels();

// Dibuka dari menu Report → tombol kembali mengarah ke sana, bukan My Project.
const backTo = computed(() =>
    page.props.reportContext
        ? { label: t.value.reports.title, href: props.urls.report, external: true }
        : { label: t.value.projects.pages.proposal.title, href: props.urls.index, external: false },
);
</script>

<template>
    <Head :title="project.project_name || project.project_id" />

    <UiPageHeader
        :title="project.project_name || t.common.untitled"
        :back-href="backTo.href"
        :back-label="backTo.label"
        :back-external="backTo.external"
    >
        <template #actions>
            <UiBadge :tone="projectTone(project.badge_status)" size="md" dot>
                {{ projectStatus(project.badge_status, project.badge_label) }}
            </UiBadge>
            <UiBadge v-if="abilities.canEdit" tone="success" size="md">
                {{ t.projects.youAreLeader }}
            </UiBadge>
        </template>
    </UiPageHeader>

    <div class="space-y-5">
        <div v-if="cancellation" class="rounded-card bg-surface-muted px-4 py-3 ring-1 ring-line-strong">
            <p class="text-sm font-semibold text-ink-muted">{{ t.projects.cancellationTitle }}</p>
            <p class="mt-1 whitespace-pre-line text-sm text-ink-muted">
                {{ cancellation.reason || t.projects.noReasonRecorded }}
            </p>
            <p class="mt-1 text-xs text-ink-subtle">
                {{ tr(t.projects.cancelledByLine, { name: cancellation.by ?? t.projects.unknown }) }} ·
                <UiDateTime :value="cancellation.at" />
            </p>
        </div>

        <!-- 1. Idea Detail -->
        <CollapsibleSection :title="t.projects.ideaDetail" storage-key="idea">
            <div v-if="idea" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <ReadOnlyField :label="t.fields.ideaId" :value="idea.idea_id" />
                    <ReadOnlyField :label="t.fields.submitter" :value="idea.submitter" />
                    <ReadOnlyField :label="t.projects.targetedBusinessUnit" :value="idea.business_unit_name" />
                    <ReadOnlyField :label="t.projects.targetedUnit" :value="idea.department_name" />
                </div>

                <ReadOnlyField :label="t.ideas.ideaName" :value="idea.idea_name" />
                <ReadOnlyField :label="t.ideas.problem" :value="idea.problem" multiline />
            </div>

            <p v-else class="text-sm text-ink-subtle">{{ t.projects.ideaNotFound }}</p>
        </CollapsibleSection>

        <!-- 2. Project Detail -->
        <CollapsibleSection :title="t.projects.projectDetail" storage-key="detail">
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <ReadOnlyField :label="t.fields.category" :value="project.project_category" />
                    <div>
                        <p class="mb-1 block text-xs font-medium text-ink-label">{{ t.fields.created }}</p>
                        <div class="rounded-control border border-line bg-surface-muted px-4 py-2 text-sm text-ink-muted">
                            <UiDateTime :value="project.created_at" mode="date" />
                        </div>
                    </div>
                </div>

                <ReadOnlyField :label="t.projects.projectScope" :value="project.project_scope" multiline />
                <ReadOnlyField :label="t.projects.expectedOutcome" :value="project.expected_outcome" multiline />

                <ReadOnlyField
                    v-if="project.project_summary"
                    :label="t.projects.projectSummaryCompletion"
                    :value="project.project_summary"
                    multiline
                />

                <!-- Sponsor tetap read-only di sini; field Leader dibuka bagi yang
                     berhak (mis. Project Sponsor). -->
                <LeadershipForm
                    :action="urls.leadership"
                    :rights="{ ...leadershipRights, sponsor: false }"
                    :leader-id="project.leader_id"
                    :leader-name="project.leader_name"
                    :sponsor-id="project.sponsor_id"
                    :sponsor-name="project.sponsor_name"
                    :user-search-url="urls.userSearch"
                    :approver-name="ideaApprover"
                    origin="detail"
                />

                <p v-if="pendingLeadership" class="text-xs">
                    <span
                        class="inline-flex items-center gap-1 rounded-control bg-warning-soft px-2 py-1 text-warning-ink ring-1 ring-warning/25"
                    >
                        {{ t.projects.leaderArrow }}
                        <span class="font-semibold">{{ pendingLeaderName ?? '—' }}</span>
                        <PendingBadge :pending="pendingLeadership" />
                    </span>
                </p>
            </div>
        </CollapsibleSection>

        <TeamSection
            :members="members"
            :can-edit="abilities.canEdit || abilities.canEditTeam"
            :role-slots="roleSlots"
            :category-code="project.category_code"
            :store-url="urls.membersStore"
            :user-search-url="urls.userSearch"
            :member-errors="memberErrors"
        />

        <ImplementationSection
            :plans="plans"
            :show-actual="showActual"
            :can-edit="abilities.canEdit"
            :can-edit-row="abilities.canEditRow"
            :can-delete-row="abilities.canDeleteRow"
            :proposal-read-only="abilities.proposalReadOnly"
            :team-members="teamMembers"
            :store-url="urls.plansStore"
        />

        <IndicatorSection
            :indicators="indicators"
            :show-actual="showActual"
            :can-edit="abilities.canEdit"
            :can-edit-row="abilities.canEditRow"
            :can-delete-row="abilities.canDeleteRow"
            :proposal-read-only="abilities.proposalReadOnly"
            :uom-groups="uomGroups"
            :types="indicatorTypes"
            :store-url="urls.indicatorsStore"
        />

        <BudgetSection
            :budgets="budgets"
            :show-actual="showActual"
            :can-edit="abilities.canEdit"
            :can-edit-row="abilities.canEditRow"
            :can-delete-row="abilities.canDeleteRow"
            :proposal-read-only="abilities.proposalReadOnly"
            :uom-groups="uomGroups"
            :empty-message="budgetEmptyMessage"
            :store-url="urls.budgetsStore"
        />

        <AttachmentsSection
            :attachments="attachments"
            :can-upload="abilities.canUploadAttachment"
            :store-url="urls.attachmentsStore"
        />

        <!-- Change request yang menunggu keputusan user ini. -->
        <ChangeRequestPanel v-for="review in changeReviews" :key="review.id" :review="review" />

        <HistorySections :approvals="approvals" :status-logs="statusLogs" :show-status-log="showActual" />

        <BottomActions
            :project="project"
            :is-leader="abilities.isLeader"
            :is-sponsor="abilities.isSponsor"
            :is-reviewer="abilities.isReviewer"
            :can-edit="abilities.canEdit"
            :can-cancel="abilities.canCancel"
            :can-submit-changes="abilities.canSubmitChanges"
            :pending-draft-count="pendingDraftCount"
            :on-behalf-of="onBehalfOf"
            :urls="{
                draft: urls.draft,
                submit: urls.submit,
                submitChanges: urls.submitChanges,
                cancel: urls.cancel,
                sponsorApprove: urls.sponsorApprove,
                sponsorRevision: urls.sponsorRevision,
                reviewApprove: urls.reviewApprove,
                reviewRevision: urls.reviewRevision,
                reviewReject: urls.reviewReject,
            }"
        />
    </div>
</template>
