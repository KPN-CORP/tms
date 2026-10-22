<script setup lang="ts">
import { computed } from 'vue';
import { formatDateTime, fullTimestamp, type DateTimeMode } from '@/lib/datetime';

const props = withDefaults(
    defineProps<{
        /** ISO-8601 UTC dari server. */
        value: string | number | Date | null | undefined;
        mode?: DateTimeMode;
        fallback?: string;
    }>(),
    { mode: 'datetime', fallback: '-' },
);

const text = computed(() => formatDateTime(props.value, props.mode, props.fallback));
const title = computed(() => fullTimestamp(props.value));
/** `datetime` tetap UTC supaya mesin pembaca memperoleh nilai yang tidak ambigu. */
const machine = computed(() => (props.value ? new Date(props.value).toISOString() : undefined));
</script>

<template>
    <time v-if="machine" :datetime="machine" :title="title">{{ text }}</time>
    <span v-else>{{ fallback }}</span>
</template>
