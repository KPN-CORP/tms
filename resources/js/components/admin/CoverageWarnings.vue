<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { UiButton } from '@/components/ui';
import { tr, useLocale } from '@/composables/useLocale';
import { useLabels } from '@/lib/labels';

interface BaseRow {
    bu: string;
    unit: string;
    assign_url: string;
}

export interface Coverage {
    total: number;
    blocked: Array<
        BaseRow & {
            /** Kode approval_type; type_label adalah teks Inggris cadangan. */
            type: string;
            type_label: string;
            layer: number;
            count: number;
            labels: string[];
            oldest_human: string | null;
        }
    >;
    gaps: Array<
        BaseRow & { type: string; type_label: string; lowest: number; highest: number; missing: number[] }
    >;
    risks: Array<BaseRow & { count: number; labels: string[] }>;
}

/**
 * Peringatan cakupan committee (ApprovalCoverageReport).
 *
 * Diturunkan dari data hidup setiap halaman dibuka, bukan notifikasi tersimpan —
 * jadi peringatannya hilang sendiri begitu assignment-nya dibuat, dan tidak
 * perlu tombol dismiss.
 */
defineProps<{ coverage: Coverage }>();

const { t } = useLocale();
const { approvalType } = useLabels();

const STORAGE_KEY = 'admin.committee.coverage';

const open = ref(true);

onMounted(() => {
    try {
        open.value = localStorage.getItem(STORAGE_KEY) !== '0';
    } catch {
        open.value = true;
    }
});

watch(open, (value) => {
    try {
        localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        // Diabaikan: posisi buka/tutup bukan data penting.
    }
});

const ROW = 'flex flex-wrap items-center justify-between gap-3 rounded-control border border-warning/30 bg-surface/70 px-3 py-2';
</script>

<template>
    <section
        v-if="coverage.total > 0"
        class="overflow-hidden rounded-card bg-warning-soft ring-1 ring-warning/40"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="flex min-w-0 items-center gap-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-warning text-sm font-bold text-white">
                    !
                </span>
                <span class="font-semibold text-warning-ink">
                    {{ t.admin.coverage.title }}
                    <span class="ml-1 font-normal">
                        {{ tr(t.admin.coverage.issues, { count: coverage.total }) }}
                    </span>
                </span>
            </span>

            <svg
                class="h-4 w-4 shrink-0 text-warning-ink transition-transform"
                :class="open ? '' : 'rotate-180'"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m18 15-6-6-6 6" />
            </svg>
        </button>

        <div v-show="open" class="space-y-4 px-4 pb-4">
            <!-- 1. Tertahan: tidak ada penanggung jawab di layer aktif. -->
            <div v-if="coverage.blocked.length" class="space-y-2">
                <p class="text-sm font-semibold text-warning-ink">
                    {{ t.admin.coverage.blockedTitle }}
                    <span class="font-normal">{{ t.admin.coverage.blockedHint }}</span>
                </p>

                <div v-for="(row, index) in coverage.blocked" :key="index" :class="ROW">
                    <div class="min-w-0 text-sm">
                        <span class="font-semibold text-ink">{{ row.bu }}</span>
                        <span class="text-ink-subtle"> / </span>
                        <span class="text-ink-muted">{{ row.unit }}</span>
                        <span class="mx-1 text-ink-subtle">·</span>
                        <span class="text-ink-muted">{{ approvalType(row.type, row.type_label) }}</span>
                        <span class="mx-1 text-ink-subtle">·</span>
                        <span class="font-semibold text-danger">
                            {{ tr(t.admin.coverage.layerUnassigned, { layer: row.layer }) }}
                        </span>

                        <div class="mt-0.5 text-xs text-ink-subtle">
                            {{ tr(t.admin.coverage.itemsWaiting, { count: row.count }) }}
                            {{ row.labels.join(', ') }}{{ row.count > row.labels.length ? ', …' : '' }}
                            <span v-if="row.oldest_human">
                                {{ tr(t.admin.coverage.oldest, { age: row.oldest_human }) }}
                            </span>
                        </div>
                    </div>

                    <UiButton size="sm" class="shrink-0" :href="row.assign_url">
                        {{ t.admin.coverage.assign }}
                    </UiButton>
                </div>
            </div>

            <!-- 2. Chain berlubang: approve() hanya mencari layer+1, jadi layer
                 yang hilang terlewat diam-diam. -->
            <div v-if="coverage.gaps.length" class="space-y-2">
                <p class="text-sm font-semibold text-warning-ink">
                    {{ t.admin.coverage.gapsTitle }}
                    <span class="font-normal">{{ t.admin.coverage.gapsHint }}</span>
                </p>

                <div v-for="(row, index) in coverage.gaps" :key="index" :class="ROW">
                    <div class="min-w-0 text-sm">
                        <span class="font-semibold text-ink">{{ row.bu }}</span>
                        <span class="text-ink-subtle"> / </span>
                        <span class="text-ink-muted">{{ row.unit }}</span>
                        <span class="mx-1 text-ink-subtle">·</span>
                        <span class="text-ink-muted">{{ approvalType(row.type, row.type_label) }}</span>

                        <div class="mt-0.5 text-xs text-ink-subtle">
                            {{
                                tr(
                                    row.missing.length > 1
                                        ? t.admin.coverage.chainRunsPlural
                                        : t.admin.coverage.chainRuns,
                                    {
                                        lowest: row.lowest,
                                        highest: row.highest,
                                        missing: row.missing.join(', '),
                                    },
                                )
                            }}
                        </div>
                    </div>

                    <UiButton size="sm" variant="secondary" class="shrink-0" :href="row.assign_url">
                        {{ t.admin.coverage.fixChain }}
                    </UiButton>
                </div>
            </div>

            <!-- 3. Belum tertahan, tapi completion akan lolos tanpa review. -->
            <div v-if="coverage.risks.length" class="space-y-2">
                <p class="text-sm font-semibold text-warning-ink">
                    {{ t.admin.coverage.risksTitle }}
                    <span class="font-normal">{{ t.admin.coverage.risksHint }}</span>
                </p>

                <div v-for="(row, index) in coverage.risks" :key="index" :class="ROW">
                    <div class="min-w-0 text-sm">
                        <span class="font-semibold text-ink">{{ row.bu }}</span>
                        <span class="text-ink-subtle"> / </span>
                        <span class="text-ink-muted">{{ row.unit }}</span>

                        <div class="mt-0.5 text-xs text-ink-subtle">
                            {{ tr(t.admin.coverage.runningProjects, { count: row.count }) }}
                            {{ row.labels.join(', ') }}{{ row.count > row.labels.length ? ', …' : '' }}
                        </div>
                    </div>

                    <UiButton size="sm" class="shrink-0" :href="row.assign_url">
                        {{ t.admin.coverage.assign }}
                    </UiButton>
                </div>
            </div>
        </div>
    </section>
</template>
