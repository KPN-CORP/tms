<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useFieldContext } from '@/composables/useFieldContext';
import { useLocale } from '@/composables/useLocale';

/**
 * Editor teks sederhana tanpa pustaka luar.
 *
 * Isinya HTML, disimpan apa adanya ke kolom `message`. Dipakai hanya untuk
 * badan email, yang memang disimpan sebagai HTML.
 *
 * Catatan: contenteditable TIDAK boleh di-bind dua arah ke model — menulis
 * ulang innerHTML saat mengetik akan memindahkan kursor ke awal. Model hanya
 * dibaca sekali saat mount dan saat nilainya diganti dari luar.
 */
const model = defineModel<string>({ default: '' });

const field = useFieldContext();
const { t } = useLocale();
const editor = ref<HTMLElement | null>(null);

const sync = (): void => {
    model.value = editor.value?.innerHTML ?? '';
};

onMounted(() => {
    if (editor.value) {
        editor.value.innerHTML = model.value;
    }
});

watch(model, (value) => {
    if (editor.value && editor.value.innerHTML !== value) {
        editor.value.innerHTML = value;
    }
});

// execCommand sudah usang tapi masih satu-satunya cara ringkas untuk ini di
// semua browser target; penggantinya belum ada yang setara.
const format = (command: string, value?: string): void => {
    editor.value?.focus();
    document.execCommand(command, false, value);
    sync();
};

const addLink = (): void => {
    const url = window.prompt('Link URL');

    if (url) {
        format('createLink', url);
    }
};

const BUTTON = 'h-7 w-7 rounded text-sm transition-colors hover:bg-surface-muted';
</script>

<template>
    <div class="overflow-hidden rounded-control border border-line-strong">
        <div class="flex flex-wrap items-center gap-1 border-b border-line bg-surface-muted px-2 py-1.5">
            <select
                class="h-7 rounded border border-line-strong bg-surface px-1 text-xs text-ink-muted"
                :aria-label="t.controls.richText.style"
                @change="format('formatBlock', ($event.target as HTMLSelectElement).value)"
            >
                <option value="p">{{ t.controls.richText.normal }}</option>
                <option value="h1">{{ t.controls.richText.heading1 }}</option>
                <option value="h2">{{ t.controls.richText.heading2 }}</option>
                <option value="h3">{{ t.controls.richText.heading3 }}</option>
            </select>

            <span class="mx-1 h-5 w-px bg-line-strong" />

            <button type="button" :class="`${BUTTON} font-bold`" :title="t.controls.richText.bold" @click="format('bold')">B</button>
            <button type="button" :class="`${BUTTON} font-serif italic`" :title="t.controls.richText.italic" @click="format('italic')">I</button>
            <button type="button" :class="`${BUTTON} underline`" :title="t.controls.richText.underline" @click="format('underline')">U</button>
            <button type="button" :class="BUTTON" :title="t.controls.richText.insertLink" @click="addLink">🔗</button>

            <span class="mx-1 h-5 w-px bg-line-strong" />

            <button type="button" :class="BUTTON" :title="t.controls.richText.numberedList" @click="format('insertOrderedList')">1.</button>
            <button type="button" :class="BUTTON" :title="t.controls.richText.bulletedList" @click="format('insertUnorderedList')">•</button>

            <span class="mx-1 h-5 w-px bg-line-strong" />

            <button type="button" :class="BUTTON" :title="t.controls.richText.clearFormatting" @click="format('removeFormat')">
                T<sub>x</sub>
            </button>
        </div>

        <div
            :id="field.id || undefined"
            ref="editor"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            class="min-h-[150px] bg-surface px-3 py-2 text-sm text-ink focus:outline-none"
            @input="sync"
            @blur="sync"
        />
    </div>
</template>
