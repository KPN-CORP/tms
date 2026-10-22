<script setup lang="ts">
import { computed, ref } from 'vue';
import { cx } from '@/lib/format';
import { tr, useLocale } from '@/composables/useLocale';

/**
 * Pemilih berkas ganda dengan tarik-lepas.
 *
 * Versi Blade menyinkronkan `<input type="file">` lewat DataTransfer supaya
 * daftar file ikut terkirim. Inertia mengirim File[] apa adanya, jadi di sini
 * daftarnya cukup dipegang sebagai array biasa.
 */
const props = withDefaults(
    defineProps<{
        /** Sama dengan whitelist di Concerns\HandlesFileAttachments. */
        accept?: string;
        /** Batas per berkas, dalam MB -- divalidasi ulang di server. */
        maxSizeMb?: number;
        error?: string;
        disabled?: boolean;
    }>(),
    {
        accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip,.csv,.txt',
        maxSizeMb: 10,
    },
);

const { t } = useLocale();

const files = defineModel<File[]>({ default: () => [] });

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const rejected = ref<string[]>([]);

const add = (incoming: FileList | null): void => {
    if (!incoming) {
        return;
    }

    const tooLarge: string[] = [];
    const next = [...files.value];

    for (const file of Array.from(incoming)) {
        if (file.size > props.maxSizeMb * 1024 * 1024) {
            tooLarge.push(file.name);
            continue;
        }

        // Berkas yang sama (nama + ukuran) tidak ditambahkan dua kali.
        if (!next.some((existing) => existing.name === file.name && existing.size === file.size)) {
            next.push(file);
        }
    }

    files.value = next;
    rejected.value = tooLarge;

    // Kosongkan input agar memilih berkas yang sama lagi tetap memicu change.
    if (input.value) {
        input.value.value = '';
    }
};

const remove = (index: number): void => {
    files.value = files.value.filter((_, i) => i !== index);
};

const onDrop = (event: DragEvent): void => {
    dragging.value = false;
    add(event.dataTransfer?.files ?? null);
};

const humanSize = (bytes: number): string =>
    bytes >= 1_048_576 ? `${(bytes / 1_048_576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;

const hint = computed(() => tr(t.value.upload.hint, { size: props.maxSizeMb }));
</script>

<template>
    <div>
        <input
            ref="input"
            type="file"
            multiple
            class="sr-only"
            :accept="accept"
            :disabled="disabled"
            @change="add(($event.target as HTMLInputElement).files)"
        />

        <button
            type="button"
            :disabled="disabled"
            :class="
                cx(
                    'w-full rounded-card border-2 border-dashed px-4 py-7 text-center text-sm transition-colors',
                    dragging ? 'border-brand bg-brand-soft' : 'border-line hover:bg-surface-muted',
                    error && 'border-danger',
                    disabled && 'cursor-not-allowed opacity-60',
                )
            "
            @click="input?.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <span class="font-semibold text-brand">{{ t.upload.chooseFiles }}</span>
            <span class="text-ink-subtle">{{ t.upload.orDragDrop }}</span>
        </button>

        <ul v-if="files.length" class="mt-3 space-y-2">
            <li
                v-for="(file, index) in files"
                :key="`${file.name}-${file.size}-${index}`"
                class="flex items-center justify-between gap-3 rounded-control border border-line bg-surface px-3 py-2 text-sm"
            >
                <span class="truncate text-ink">{{ file.name }}</span>

                <span class="flex shrink-0 items-center gap-3">
                    <span class="text-xs text-ink-subtle">{{ humanSize(file.size) }}</span>
                    <button
                        type="button"
                        class="rounded p-1 text-danger transition-colors hover:bg-danger-soft"
                        :aria-label="tr(t.upload.remove, { name: file.name })"
                        @click="remove(index)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </span>
            </li>
        </ul>

        <p v-if="rejected.length" class="mt-2 text-xs font-medium text-danger">
            {{ tr(t.upload.skipped, { size: maxSizeMb, files: rejected.join(', ') }) }}
        </p>

        <p v-if="error" class="mt-1 text-xs font-medium text-danger">{{ error }}</p>
        <p v-else class="mt-1 text-xs text-ink-subtle">{{ hint }}</p>
    </div>
</template>
