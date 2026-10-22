<script setup lang="ts">
import { computed } from 'vue';
import UiBadge from './UiBadge.vue';
import UiDateTime from './UiDateTime.vue';
import { slaTone } from '@/lib/status';
import { tr, useLocale } from '@/composables/useLocale';
import type { SlaState } from '@/types/models';

const props = defineProps<{
    sla: { state: SlaState | 'none'; due: string | null; left: number | null };
}>();

const { t } = useLocale();

/**
 * SLA bersifat INFORMASI saja — SlaService tidak mengirim pengingat apa pun
 * (lihat CLAUDE.md). Badge ini hanya menerangkan posisi tenggat.
 */
const label = computed(() => t.value.status.sla[props.sla.state as SlaState]);

const detail = computed(() => {
    const left = props.sla.left;

    if (left === null) {
        return null;
    }

    if (left < 0) {
        return tr(t.value.status.sla.daysLate, { days: Math.abs(left) });
    }

    return left === 0
        ? t.value.status.sla.dueToday
        : tr(t.value.status.sla.daysLeft, { days: left });
});
</script>

<template>
    <div v-if="sla.state !== 'none'" class="flex flex-col gap-1">
        <UiBadge :tone="slaTone(sla.state)">{{ label }}</UiBadge>

        <span class="text-xs text-ink-subtle">
            <UiDateTime :value="sla.due" mode="date" />
            <template v-if="detail"> · {{ detail }}</template>
        </span>
    </div>

    <span v-else class="text-sm text-ink-subtle">-</span>
</template>
