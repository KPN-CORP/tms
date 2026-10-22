<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { UiButton, UiCard, UiModal, UiField, UiTextarea } from '@/components/ui';
import { useForm } from '@inertiajs/vue3';
import DecisionPanel, { type DecisionAction } from '../DecisionPanel.vue';
import { useConfirm } from '@/composables/useConfirm';
import { tr, useLocale } from '@/composables/useLocale';
import { reportFields } from '@/composables/useReportContext';
import type { ProjectDetail } from '../types';

const props = defineProps<{
    project: ProjectDetail;
    isLeader: boolean;
    isSponsor: boolean;
    isReviewer: boolean;
    canEdit: boolean;
    canCancel: boolean;
    canSubmitChanges: boolean;
    /** Perubahan yang ditahan dan belum diajukan approval. */
    pendingDraftCount: number;
    /** Nama committee layer aktif yang diwakili; null bila user penilainya sendiri. */
    onBehalfOf: string | null;
    urls: {
        draft: string;
        submit: string;
        submitChanges: string;
        cancel: string;
        sponsorApprove: string;
        sponsorRevision: string;
        reviewApprove: string;
        reviewRevision: string;
        reviewReject: string;
    };
}>();

const { confirm } = useConfirm();
const { t } = useLocale();

/** Bertindak atas nama committee layer aktif (pemegang override.role). */
const actingOnBehalf = computed(() => !props.isReviewer && props.onBehalfOf !== null);

const sponsorTurn = computed(() => props.isSponsor && props.project.status === 'submitted');
const committeeTurn = computed(() => props.isReviewer || actingOnBehalf.value);

const leaderProposalActions = computed(() => props.isLeader && props.canEdit);

const showDecisionCard = computed(
    () => leaderProposalActions.value || sponsorTurn.value || committeeTurn.value,
);

const isCompletion = computed(() => props.project.status === 'completion_review');

// Dua bentuk: huruf kecil di tengah kalimat, huruf besar di judul.
const noun = computed(() =>
    isCompletion.value ? t.value.projects.actions.nounCompletion : t.value.projects.actions.nounProposal,
);
const Noun = computed(() =>
    isCompletion.value ? t.value.projects.actions.NounCompletion : t.value.projects.actions.NounProposal,
);

const sponsorActions = computed<DecisionAction[]>(() => [
    {
        key: 'approve',
        label: t.value.status.decision.approve,
        url: props.urls.sponsorApprove,
        variant: 'success',
        requiresNote: false,
        confirmTitle: t.value.projects.actions.approveProposalTitle,
        confirmMessage: t.value.projects.actions.approveProposalMessage,
        confirmLabel: t.value.ideas.approveConfirm,
    },
    {
        key: 'revision',
        label: t.value.status.decision.revision_required,
        url: props.urls.sponsorRevision,
        variant: 'warning',
        requiresNote: true,
        confirmTitle: t.value.projects.actions.revisionTitle,
        confirmMessage: t.value.projects.actions.revisionMessage,
        confirmLabel: t.value.projects.actions.revisionConfirm,
    },
]);

const committeeActions = computed<DecisionAction[]>(() => {
    const actions: DecisionAction[] = [
        {
            key: 'approve',
            label: t.value.status.decision.approve,
            url: props.urls.reviewApprove,
            variant: 'success',
            requiresNote: false,
            confirmTitle: tr(t.value.projects.actions.approveTitle, { noun: noun.value }),
            confirmMessage: tr(t.value.projects.actions.approveMessage, {
                noun: noun.value,
                layer: props.project.current_layer ?? '',
            }),
            confirmLabel: t.value.ideas.approveConfirm,
        },
    ];

    // Revision Required hanya ada pada alur proposal, bukan completion.
    if (props.project.status === 'committee_review') {
        actions.push({
            key: 'revision',
            label: t.value.status.decision.revision_required,
            url: props.urls.reviewRevision,
            variant: 'warning',
            requiresNote: true,
            confirmTitle: t.value.projects.actions.revisionTitle,
            confirmMessage: t.value.projects.actions.revisionMessage,
            confirmLabel: t.value.projects.actions.revisionConfirm,
        });
    }

    actions.push({
        key: 'reject',
        label: t.value.status.decision.reject,
        url: props.urls.reviewReject,
        variant: 'danger',
        requiresNote: true,
        confirmTitle: tr(t.value.projects.actions.rejectTitle, { Noun: Noun.value }),
        confirmMessage: tr(t.value.projects.actions.rejectMessage, { noun: noun.value }),
        confirmLabel: t.value.ideas.rejectConfirm,
    });

    return actions;
});

/* ---- Aksi Leader ------------------------------------------------------- */

const busy = ref<string | null>(null);

const post = (key: string, url: string): void => {
    busy.value = key;
    router.post(url, reportFields(), { preserveScroll: true, onFinish: () => (busy.value = null) });
};

const saveDraft = (): void => post('draft', props.urls.draft);

const submitProposal = async (): Promise<void> => {
    const ok = await confirm({
        title: t.value.projects.actions.submitProposalTitle,
        message: t.value.projects.actions.submitProposalMessage,
        confirmLabel: t.value.projects.actions.submitProposalConfirm,
    });

    if (ok) {
        post('submit', props.urls.submit);
    }
};

const submitChanges = async (): Promise<void> => {
    const ok = await confirm({
        title: t.value.projects.actions.updateTitle,
        message: t.value.projects.actions.updateMessage,
        confirmLabel: t.value.projects.actions.updateConfirm,
    });

    if (ok) {
        post('changes', props.urls.submitChanges);
    }
};

/* ---- Pembatalan -------------------------------------------------------- */

const cancelOpen = ref(false);
const cancelForm = useForm({ reason: '' });

const submitCancel = (): void => {
    cancelForm.transform((data) => ({ ...data, ...reportFields() })).post(props.urls.cancel, {
        preserveScroll: true,
        onSuccess: () => {
            cancelOpen.value = false;
            cancelForm.reset();
        },
    });
};

const showActionRow = computed(
    () => leaderProposalActions.value || props.canSubmitChanges || props.canCancel,
);
</script>

<template>
    <component :is="showDecisionCard ? UiCard : 'div'" v-if="showDecisionCard || showActionRow">
        <div class="space-y-4">
            <div v-if="sponsorTurn">
                <h3 class="mb-3 text-base font-semibold text-ink">
                    {{ t.projects.actions.sponsorApproval }}
                </h3>
                <DecisionPanel
                    :actions="sponsorActions"
                    :placeholder="t.projects.actions.sponsorNotePlaceholder"
                />
            </div>

            <div v-else-if="committeeTurn">
                <div
                    v-if="actingOnBehalf"
                    class="mb-4 rounded-control bg-warning-soft px-4 py-3 text-sm text-warning-ink ring-1 ring-warning/25"
                >
                    {{ t.projects.actions.onBehalfLead }}
                    <span class="font-semibold">
                        {{ tr(t.projects.actions.onBehalfStrong, { name: onBehalfOf ?? '' }) }}
                    </span>
                    {{ tr(t.projects.actions.onBehalfTail, { layer: project.current_layer ?? '' }) }}
                </div>

                <h3 class="mb-3 text-base font-semibold text-ink">
                    {{
                        tr(t.projects.actions.committeeApproval, {
                            noun: Noun,
                            layer: project.current_layer ?? '',
                        })
                    }}
                    <span v-if="actingOnBehalf" class="text-sm font-normal text-warning-ink">
                        {{ tr(t.projects.actions.onBehalfSuffix, { name: onBehalfOf ?? '' }) }}
                    </span>
                </h3>

                <DecisionPanel
                    :actions="committeeActions"
                    :placeholder="t.projects.changeRequest.notePlaceholder"
                />
            </div>

            <!-- Satu baris aksi: keterangan di kiri, semua tombol sejajar di kanan. -->
            <div v-if="showActionRow" class="flex flex-wrap items-center justify-between gap-3">
                <p v-if="leaderProposalActions" class="text-sm text-ink-subtle">
                    {{ t.projects.actions.leaderHint }}
                </p>
                <span v-else />

                <div class="flex flex-wrap items-center gap-3">
                    <template v-if="leaderProposalActions">
                        <UiButton variant="secondary" :loading="busy === 'draft'" @click="saveDraft">
                            {{ t.projects.actions.draft }}
                        </UiButton>
                        <UiButton :loading="busy === 'submit'" @click="submitProposal">
                            {{ t.projects.actions.submit }}
                        </UiButton>
                    </template>

                    <template v-if="canSubmitChanges">
                        <UiButton variant="secondary" :loading="busy === 'draft'" @click="saveDraft">
                            {{ t.projects.actions.saveAsDraft }}
                        </UiButton>

                        <UiButton
                            :loading="busy === 'changes'"
                            :disabled="pendingDraftCount === 0"
                            @click="submitChanges"
                        >
                            {{ t.projects.actions.updateProject }}
                            <span
                                v-if="pendingDraftCount > 0"
                                class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white/25 px-1.5 text-xs"
                            >
                                {{ pendingDraftCount }}
                            </span>
                        </UiButton>
                    </template>

                    <UiButton v-if="canCancel" variant="danger" @click="cancelOpen = true">
                        {{ t.projects.actions.cancelProject }}
                    </UiButton>
                </div>
            </div>
        </div>
    </component>

    <UiModal
        v-model="cancelOpen"
        :title="t.projects.cancelCard.modalTitle"
        :description="t.projects.cancelCard.modalDescription"
        size="sm"
        :close-on-backdrop="false"
    >
        <UiField :label="t.fields.reason" required :error="cancelForm.errors.reason">
            <UiTextarea
                v-model="cancelForm.reason"
                :rows="3"
                :maxlength="2000"
                :placeholder="t.projects.cancelCard.reasonRequired"
            />
        </UiField>

        <template #footer>
            <UiButton variant="secondary" @click="cancelOpen = false">{{ t.common.cancel }}</UiButton>
            <UiButton
                variant="danger"
                :loading="cancelForm.processing"
                :disabled="cancelForm.reason.trim() === ''"
                @click="submitCancel"
            >
                {{ t.projects.cancelCard.confirm }}
            </UiButton>
        </template>
    </UiModal>
</template>
