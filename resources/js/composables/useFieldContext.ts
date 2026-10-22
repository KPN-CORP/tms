import { inject } from 'vue';

export interface FieldContext {
    id: string;
    describedBy: () => string | undefined;
    invalid: () => boolean;
}

/**
 * Konteks dari <UiField>. Kontrol boleh dipakai di luar UiField (mis. kotak
 * pencarian di toolbar), karena itu nilainya opsional dan ada cadangan.
 */
export function useFieldContext(): FieldContext {
    return inject<FieldContext>('field', {
        id: '',
        describedBy: () => undefined,
        invalid: () => false,
    });
}

/** Kelas dasar yang sama untuk input, select, dan textarea. */
export const CONTROL_CLASSES =
    'w-full rounded-control border bg-surface px-3 text-sm text-ink transition-colors ' +
    'focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand ' +
    'placeholder:text-ink-subtle disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-ink-subtle';

export function controlBorder(invalid: boolean): string {
    return invalid ? 'border-danger' : 'border-line';
}
