import type { FormDataConvertible } from '@inertiajs/core';

/** Bentuk query yang diterima `router.get` Inertia. */
export type QueryPayload = Record<string, FormDataConvertible>;

/**
 * Buang nilai kosong sebelum dikirim, supaya URL tidak dipenuhi parameter tanpa
 * arti (`?q=&status=&unit=`) dan tombol Reset benar-benar mengembalikan URL
 * bersih.
 */
export function cleanQuery(values: Record<string, unknown>): QueryPayload {
    const out: QueryPayload = {};

    for (const [key, value] of Object.entries(values)) {
        if (value !== '' && value !== null && value !== undefined) {
            out[key] = value as FormDataConvertible;
        }
    }

    return out;
}
