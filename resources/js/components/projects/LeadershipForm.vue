<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ReadOnlyField, UiButton, UiCombobox, UiField } from '@/components/ui';
import { useConfirm } from '@/composables/useConfirm';
import { useLocale } from '@/composables/useLocale';

export interface LeadershipRights {
    sponsor: boolean;
    leader: boolean;
    /** True = perubahan langsung berlaku; false = lewat approval. */
    instant: boolean;
}

/**
 * Ubah Project Sponsor / Leader.
 *
 * Siapa yang boleh dan apakah langsung berlaku ditentukan SEPENUHNYA di server
 * (Project::leadershipRights) — komponen ini hanya mengikuti hasilnya. Approval
 * perubahan ini tidak lewat committee_assignments, melainkan committee layer
 * terakhir dari ide asal; `approverName` null berarti permintaannya akan
 * menggantung sampai penyetuju ada.
 */
const props = defineProps<{
    action: string;
    rights: LeadershipRights;
    leaderId: number | null;
    leaderName: string | null;
    sponsorId: number | null;
    sponsorName: string | null;
    userSearchUrl: string;
    approverName: string | null;
    /** Dikirim sebagai `from` agar controller tahu halaman asalnya. */
    origin: 'shell' | 'detail';
}>();

const form = useForm<{ project_leader_id: number | null; project_sponsor_id: number | null; from: string }>({
    project_leader_id: props.leaderId,
    project_sponsor_id: props.sponsorId,
    from: props.origin,
});

const { confirm } = useConfirm();
const { t } = useLocale();

const editable = computed(() => props.rights.leader || props.rights.sponsor);
const saving = ref(false);

const submit = async (): Promise<void> => {
    const ok = await confirm(
        props.rights.instant
            ? {
                  title: t.value.projects.leadership.saveTitle,
                  message: t.value.projects.leadership.saveMessage,
                  confirmLabel: t.value.projects.leadership.saveConfirm,
              }
            : {
                  title: t.value.projects.leadership.approvalTitle,
                  message: t.value.projects.leadership.approvalMessage,
                  confirmLabel: t.value.projects.leadership.approvalConfirm,
              },
    );

    if (!ok) {
        return;
    }

    saving.value = true;
    form.put(props.action, { preserveScroll: true, onFinish: () => (saving.value = false) });
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <UiField v-if="rights.leader" :label="t.fields.leader" :error="form.errors.project_leader_id">
            <UiCombobox
                v-model="form.project_leader_id"
                :search-url="userSearchUrl"
                value-key="id"
                :initial-label="leaderName"
                :placeholder="t.projects.leadership.searchPlaceholder"
            />
        </UiField>
        <ReadOnlyField v-else :label="t.fields.leader" :value="leaderName" />

        <UiField v-if="rights.sponsor" :label="t.fields.sponsor" :error="form.errors.project_sponsor_id">
            <UiCombobox
                v-model="form.project_sponsor_id"
                :search-url="userSearchUrl"
                value-key="id"
                :initial-label="sponsorName"
                :placeholder="t.projects.leadership.searchPlaceholder"
            />
        </UiField>
        <ReadOnlyField v-else :label="t.fields.sponsor" :value="sponsorName" />
    </div>

    <div
        v-if="editable"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4"
    >
        <p class="text-xs text-ink-subtle">
            <template v-if="rights.instant">{{ t.projects.leadership.instant }}</template>
            <template v-else-if="approverName">
                {{ t.projects.leadership.reviewedByLead }}
                <span class="font-semibold text-ink-muted">{{ approverName }}</span
                >{{ t.projects.leadership.reviewedByTail }}
            </template>
            <template v-else>{{ t.projects.leadership.noApprover }}</template>
        </p>

        <UiButton :loading="saving" :disabled="form.processing" @click="submit">
            {{ rights.instant ? t.projects.leadership.submit : t.projects.leadership.submitForApproval }}
        </UiButton>
    </div>
</template>
