<script setup lang="ts">
import { computed } from 'vue';
import { UiDateTime } from '@/components/ui';
import DecisionPanel, { type DecisionAction } from './DecisionPanel.vue';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';
import type { ChangeReview, DiffField } from './types';

/**
 * Permintaan perubahan yang menunggu keputusan user ini.
 *
 * Tiap field ditampilkan dua baris — BEFORE (pudar, dicoret) lalu AFTER — dan
 * dikelompokkan per BARIS data, bukan per field, sehingga satu item perubahan
 * terbaca sebagai satu tabel kecil.
 */
const props = defineProps<{ review: ChangeReview }>();

const { t } = useLocale();
const { field, changeType } = useLabels();

const fmt = (value: unknown): string => {
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (typeof value === 'boolean') {
        return value ? t.value.common.yes : t.value.common.no;
    }

    return value === null || value === undefined || value === '' ? '—' : String(value);
};

/** Kelompokkan diff per baris data, mempertahankan urutan kemunculannya. */
const groups = computed<DiffField[][]>(() => {
    const byRow = new Map<string, DiffField[]>();

    for (const field of props.review.diff) {
        const bucket = byRow.get(field.row);

        if (bucket) {
            bucket.push(field);
        } else {
            byRow.set(field.row, [field]);
        }
    }

    return [...byRow.values()];
});

const layerLabel = computed(() =>
    props.review.approver_role === 'committee'
        ? tr(t.value.projects.history.layer, { layer: props.review.current_layer ?? '' })
        : t.value.projects.changeRequest.projectSponsor,
);

const actions = computed<DecisionAction[]>(() => {
    const cr = t.value.projects.changeRequest;

    return [
        {
            key: 'approve',
            label: t.value.status.decision.approve,
            url: props.review.urls.approve,
            variant: 'success',
            requiresNote: false,
            confirmTitle: cr.approveTitle,
            confirmMessage: tr(cr.approveMessage, { layer: props.review.current_layer ?? '' }),
            confirmLabel: t.value.ideas.approveConfirm,
        },
        {
            key: 'revision',
            label: t.value.status.decision.revision_required,
            url: props.review.urls.revision,
            variant: 'warning',
            requiresNote: true,
            confirmTitle: cr.revisionTitle,
            confirmMessage: cr.revisionMessage,
            confirmLabel: cr.revisionConfirm,
        },
        {
            key: 'reject',
            label: t.value.status.decision.reject,
            url: props.review.urls.reject,
            variant: 'danger',
            requiresNote: true,
            confirmTitle: cr.rejectTitle,
            confirmMessage: cr.rejectMessage,
            confirmLabel: t.value.ideas.rejectConfirm,
        },
    ];
});
</script>

<template>
    <section class="overflow-hidden rounded-card border border-warning/40 bg-surface shadow-card">
        <header class="border-b border-line bg-warning-soft px-5 py-4">
            <h3 class="text-base font-semibold text-ink">
                {{
                    tr(t.projects.changeRequest.title, {
                        label: changeType(review.change_type, review.label),
                    })
                }}
            </h3>

            <p class="mt-0.5 text-sm text-ink-muted">
                {{ tr(t.projects.changeRequest.requestedBy, { name: review.requester ?? '—' }) }} ·
                <UiDateTime :value="review.requested_at" /> · {{ layerLabel }}
            </p>

            <p v-if="review.review_note" class="mt-1 text-sm text-warning-ink">
                <span class="font-semibold">{{ t.projects.changeRequest.previousNote }}</span>
                {{ review.review_note }}
            </p>

            <!-- Keputusan akan tercatat atas nama penilai aslinya. -->
            <p v-if="review.on_behalf_of" class="mt-1 text-sm text-warning-ink">
                {{ t.projects.changeRequest.onBehalfLead }}
                <span class="font-semibold">
                    {{ tr(t.projects.changeRequest.onBehalfStrong, { name: review.on_behalf_of }) }}
                </span>{{ t.projects.changeRequest.onBehalfTail }}
            </p>
        </header>

        <div v-for="(fields, index) in groups" :key="index" class="overflow-x-auto scroll-slim border-t border-line">
            <table class="w-full border-collapse text-left text-sm">
                <thead class="bg-surface-muted/70 text-xs uppercase tracking-wider text-ink-subtle">
                    <tr>
                        <th class="px-4 py-2">{{ t.projects.changeRequest.item }}</th>
                        <th class="w-20 px-4 py-2" />
                        <th v-for="column in fields" :key="column.field" class="px-4 py-2">
                            {{ field(column.field) }}
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td class="px-4 py-2 align-middle text-ink-muted" rowspan="2">{{ fields[0]?.label }}</td>
                        <td class="px-4 pb-0.5 pt-2 text-xs uppercase tracking-wide text-ink-subtle">
                            {{ t.projects.changeRequest.before }}
                        </td>
                        <td v-for="column in fields" :key="column.field" class="px-4 pb-0.5 pt-2">
                            <span class="text-ink-subtle line-through opacity-70">{{ fmt(column.before) }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-4 pb-2 pt-0.5 text-xs uppercase tracking-wide text-ink-subtle">
                            {{ t.projects.changeRequest.after }}
                        </td>
                        <td v-for="column in fields" :key="column.field" class="px-4 pb-2 pt-0.5 font-semibold text-ink">
                            {{ fmt(column.after) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="!groups.length" class="border-t border-line px-5 py-3 text-sm text-ink-subtle">
            {{ t.projects.changeRequest.noChanges }}
        </p>

        <div class="border-t border-line p-5">
            <DecisionPanel
                :actions="actions"
                :placeholder="t.projects.changeRequest.notePlaceholder"
            />
        </div>
    </section>
</template>
