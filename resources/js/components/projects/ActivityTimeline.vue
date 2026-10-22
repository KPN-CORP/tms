<script setup lang="ts">
import { UiDateTime, UiEmptyState } from '@/components/ui';
import { tr, useLocale } from '@/composables/useLocale';

export interface Activity {
    at: string | null;
    kind: 'status' | 'update' | 'plan' | string;
    title: string;
    note: string | null;
    actor: string | null;
}

defineProps<{ activities: Activity[] }>();

/** Titik berwarna per jenis supaya linimasa mudah dipindai. */
const DOTS: Record<string, string> = {
    status: 'bg-brand',
    update: 'bg-info',
    plan: 'bg-success',
};

const { t } = useLocale();

/** Jenis aktivitas datang sebagai kode; teksnya ikut kamus. */
const kindLabel = (kind: string): string =>
    (t.value.projects.activityKinds as Record<string, string | undefined>)[kind] ?? kind;
</script>

<template>
    <ol v-if="activities.length" class="relative">
        <li
            v-for="(activity, index) in activities"
            :key="`${activity.at}-${index}`"
            class="relative flex gap-3 pb-4 last:pb-0"
        >
            <div class="flex shrink-0 flex-col items-center">
                <span
                    class="mt-1.5 h-2.5 w-2.5 rounded-full"
                    :class="DOTS[activity.kind] ?? 'bg-neutral'"
                    aria-hidden="true"
                />
                <span
                    v-if="index < activities.length - 1"
                    class="mt-1 w-px flex-1 bg-line"
                    aria-hidden="true"
                />
            </div>

            <div class="min-w-0 flex-1 border-b border-line pb-3 last:border-b-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-ink-subtle">
                        {{ kindLabel(activity.kind) }}
                    </span>
                    <span class="text-xs text-ink-subtle">
                        <UiDateTime :value="activity.at" />
                    </span>
                </div>

                <p class="mt-0.5 text-sm font-semibold text-ink">{{ activity.title }}</p>

                <p v-if="activity.note" class="mt-0.5 whitespace-pre-line text-sm text-ink-muted">
                    {{ activity.note }}
                </p>

                <p v-if="activity.actor" class="mt-0.5 text-xs text-ink-subtle">
                    {{ tr(t.projects.by, { name: activity.actor }) }}
                </p>
            </div>
        </li>
    </ol>

    <UiEmptyState
        v-else
        :title="t.projects.noActivityTitle"
        :message="t.projects.noActivityMessage"
    />
</template>
