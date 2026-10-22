<script setup lang="ts">
import { UiBadge, UiDateTime } from '@/components/ui';
import CollapsibleSection from '../CollapsibleSection.vue';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import type { ApprovalRow, StatusLogRow } from '../types';
import type { Tone } from '@/lib/status';

/** Riwayat keputusan approval dan log perubahan status. */
defineProps<{
    approvals: ApprovalRow[];
    statusLogs: StatusLogRow[];
    /** Log status disembunyikan selama project masih berupa proposal. */
    showStatusLog: boolean;
}>();

const { t } = useLocale();
const { decision: decisionLabel, projectStatus } = useLabels();

const DECISION_TONES: Record<string, Tone> = {
    approve: 'success',
    revision: 'warning',
};
</script>

<template>
    <CollapsibleSection v-if="approvals.length" :title="t.projects.history.approvalTitle" storage-key="approval">
        <ol class="divide-y divide-line">
            <li
                v-for="approval in approvals"
                :key="approval.id"
                class="flex items-start gap-3 py-2.5 text-sm first:pt-0 last:pb-0"
            >
                <UiBadge :tone="DECISION_TONES[approval.decision] ?? 'danger'" class="shrink-0">
                    {{ tr(t.projects.history.layer, { layer: approval.layer }) }} ·
                    {{ decisionLabel(approval.decision) }}
                </UiBadge>

                <div class="min-w-0">
                    <p class="text-ink">
                        <span class="font-semibold">{{ approval.decided_by ?? '-' }}</span>

                        <!-- Keputusan lewat konteks Report dicatat atas nama pemegang layer. -->
                        <template v-if="approval.on_behalf_of">
                            <span class="text-ink-subtle"> {{ t.projects.history.onBehalfOf }} </span>
                            <span class="font-semibold">{{ approval.on_behalf_of }}</span>
                            <span class="text-ink-subtle">
                                ({{ tr(t.projects.history.layer, { layer: approval.layer }) }})
                            </span>
                        </template>

                        <span class="text-ink-subtle"> · <UiDateTime :value="approval.created_at" /></span>
                    </p>

                    <p v-if="approval.note" class="mt-0.5 whitespace-pre-line text-ink-muted">{{ approval.note }}</p>
                </div>
            </li>
        </ol>
    </CollapsibleSection>

    <CollapsibleSection v-if="showStatusLog" :title="t.projects.history.progressTitle" storage-key="progress">
        <ol v-if="statusLogs.length" class="divide-y divide-line">
            <li
                v-for="log in statusLogs"
                :key="log.id"
                class="flex items-start gap-3 py-2.5 text-sm first:pt-0 last:pb-0"
            >
                <span class="shrink-0 rounded-full bg-surface-muted px-2 py-1 text-xs text-ink-muted">
                    {{ log.old_status ? projectStatus(log.old_status) : '—' }} →
                    {{ projectStatus(log.new_status) }}
                </span>

                <div class="min-w-0">
                    <p class="text-ink">
                        <span class="font-semibold">{{ log.changed_by ?? '-' }}</span>
                        <span class="text-ink-subtle"> · <UiDateTime :value="log.created_at" /></span>
                    </p>
                    <p v-if="log.remarks" class="mt-0.5 whitespace-pre-line text-ink-muted">{{ log.remarks }}</p>
                </div>
            </li>
        </ol>

        <p v-else class="text-sm text-ink-subtle">{{ t.projects.history.noStatusChanges }}</p>
    </CollapsibleSection>
</template>
