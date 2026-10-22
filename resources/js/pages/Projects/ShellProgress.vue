<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    ReadOnlyField,
    UiBadge,
    UiButton,
    UiCard,
    UiDateTime,
    UiPageHeader,
} from '@/components/ui';
import ActivityTimeline, { type Activity } from '@/components/projects/ActivityTimeline.vue';
import CancelProjectCard from '@/components/projects/CancelProjectCard.vue';
import LeadershipForm, { type LeadershipRights } from '@/components/projects/LeadershipForm.vue';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import { projectTone } from '@/lib/status';
import type { ProjectStatus } from '@/types/models';

const props = defineProps<{
    project: {
        id: number;
        project_id: string;
        project_name: string | null;
        project_category: string | null;
        project_scope: string | null;
        expected_outcome: string | null;
        status: ProjectStatus;
        status_label: string;
        created_at: string | null;
        leader_id: number | null;
        leader_name: string | null;
        sponsor_id: number | null;
        sponsor_name: string | null;
        idea_id: string | null;
        business_unit_name: string | null;
        department_name: string | null;
    };
    summary: {
        planTotal: number;
        planDone: number;
        planPercent: number | null;
        memberCount: number;
        weightTotal: number;
        weightDone: number;
        indicatorPercent: number | null;
        lastActivity: string | null;
    };
    activities: Activity[];
    cancellation: { reason: string | null; by: string | null; at: string | null } | null;
    canCancel: boolean;
    leadershipRights: LeadershipRights;
    ideaApprover: string | null;
    urls: {
        back: string;
        detail: string;
        leadership: string;
        cancel: string;
        userSearch: string;
    };
    detailIsExternal: boolean;
}>();

const { t } = useLocale();
const { projectStatus } = useLabels();

const statusText = computed(() => projectStatus(props.project.status, props.project.status_label));

/** 12.50 -> "12.5", 100.00 -> "100" — sama seperti rtrim ganda di versi Blade. */
const trimWeight = (value: number): string => String(Number(value.toFixed(2)));
</script>

<template>
    <Head :title="project.project_name || project.project_id" />

    <UiPageHeader
        :title="project.project_name || t.common.untitled"
        :back-href="urls.back"
    >
        <template #actions>
            <UiBadge :tone="projectTone(project.status)" size="md" dot>{{ statusText }}</UiBadge>
            <UiButton variant="secondary" :href="urls.detail" :external="detailIsExternal">
                {{ t.projects.openFullDetail }}
            </UiButton>
        </template>
    </UiPageHeader>

    <div class="space-y-5">
        <div
            v-if="cancellation"
            class="rounded-card bg-surface-muted px-4 py-3 ring-1 ring-line-strong"
        >
            <p class="text-sm font-semibold text-ink-muted">{{ t.projects.cancelledTitle }}</p>
            <p class="mt-1 whitespace-pre-line text-sm text-ink-muted">{{ cancellation.reason || '—' }}</p>
            <p class="mt-1 text-xs text-ink-subtle">
                {{ tr(t.projects.cancelledBy, { name: cancellation.by ?? t.projects.unknown }) }} ·
                <UiDateTime :value="cancellation.at" />
            </p>
        </div>

        <UiCard :title="t.projects.progressSummary">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="rounded-control border border-line px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-ink-subtle">{{ t.projects.implementationLabel }}</p>
                        <p class="mt-1 text-2xl font-bold text-ink">
                            {{ summary.planPercent === null ? '—' : `${summary.planPercent}%` }}
                        </p>
                        <p class="text-xs text-ink-subtle">
                            {{ tr(t.projects.activitiesDone, { done: summary.planDone, total: summary.planTotal }) }}
                        </p>
                    </div>

                    <div class="rounded-control border border-line px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-ink-subtle">{{ t.projects.indicatorsLabel }}</p>
                        <p class="mt-1 text-2xl font-bold text-ink">
                            {{ summary.indicatorPercent === null ? '—' : `${summary.indicatorPercent}%` }}
                        </p>
                        <p class="text-xs text-ink-subtle">
                            {{
                                tr(t.projects.weightOf, {
                                    done: trimWeight(summary.weightDone),
                                    total: trimWeight(summary.weightTotal),
                                })
                            }}
                        </p>
                    </div>

                    <div class="rounded-control border border-line px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-ink-subtle">{{ t.projects.teamMembers }}</p>
                        <p class="mt-1 text-2xl font-bold text-ink">{{ summary.memberCount }}</p>
                        <p class="text-xs text-ink-subtle">{{ t.projects.registeredInProject }}</p>
                    </div>

                    <div class="rounded-control border border-line px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-ink-subtle">{{ t.projects.lastStatusChange }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink">
                            <UiDateTime :value="summary.lastActivity" fallback="—" />
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <ReadOnlyField :label="t.fields.category" :value="project.project_category" />
                    <ReadOnlyField :label="t.fields.status" :value="statusText" />
                </div>

                <!-- Leader & Sponsor bisa langsung disunting di sini oleh yang
                     berhak (committee layer terakhir ide). Field lain read-only. -->
                <LeadershipForm
                    :action="urls.leadership"
                    :rights="leadershipRights"
                    :leader-id="project.leader_id"
                    :leader-name="project.leader_name"
                    :sponsor-id="project.sponsor_id"
                    :sponsor-name="project.sponsor_name"
                    :user-search-url="urls.userSearch"
                    :approver-name="ideaApprover"
                    origin="shell"
                />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <ReadOnlyField :label="t.projects.originIdea" :value="project.idea_id" />
                    <div>
                        <p class="mb-1 block text-xs font-medium text-ink-label">{{ t.projects.shellCreated }}</p>
                        <div class="rounded-control border border-line bg-surface-muted px-4 py-2 text-sm text-ink-muted">
                            <UiDateTime :value="project.created_at" />
                        </div>
                    </div>

                    <ReadOnlyField :label="t.projects.targetedBusinessUnit" :value="project.business_unit_name" />
                    <ReadOnlyField :label="t.projects.targetedUnit" :value="project.department_name" />
                </div>

                <ReadOnlyField :label="t.projects.projectScope" :value="project.project_scope" multiline />
                <ReadOnlyField :label="t.projects.expectedOutcome" :value="project.expected_outcome" multiline />
            </div>
        </UiCard>

        <UiCard :title="t.projects.activities">
            <ActivityTimeline :activities="activities" />
        </UiCard>

        <CancelProjectCard v-if="canCancel" :action="urls.cancel" />
    </div>
</template>
