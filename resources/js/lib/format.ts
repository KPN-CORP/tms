/** Pemformatan angka & teks yang dipakai lintas halaman. */

const RUPIAH = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const DECIMAL = new Intl.NumberFormat('id-ID');

const COMPACT = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

export function currency(value: number | string | null | undefined, fallback = '-'): string {
    const n = toNumber(value);

    return n === null ? fallback : RUPIAH.format(n);
}

export function number(value: number | string | null | undefined, fallback = '-'): string {
    const n = toNumber(value);

    return n === null ? fallback : DECIMAL.format(n);
}

/** Angka ringkas untuk kartu dashboard: 12.4K, 3.1M. */
export function compact(value: number | string | null | undefined, fallback = '-'): string {
    const n = toNumber(value);

    return n === null ? fallback : COMPACT.format(n);
}

export function percent(value: number | string | null | undefined, fallback = '-'): string {
    const n = toNumber(value);

    return n === null ? fallback : `${Math.round(n)}%`;
}

function toNumber(value: number | string | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const n = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(n) ? n : null;
}

/** 'revision_required' -> 'Revision Required'. Cadangan bila server tidak kirim label. */
export function humanize(value: string | null | undefined, fallback = '-'): string {
    if (!value) {
        return fallback;
    }

    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function initials(name: string | null | undefined): string {
    if (!name) {
        return '?';
    }

    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1]?.[0] ?? '' : '';

    return (first + last).toUpperCase();
}

export function truncate(value: string | null | undefined, length = 80): string {
    if (!value) {
        return '';
    }

    return value.length > length ? `${value.slice(0, length - 1).trimEnd()}…` : value;
}

/** Penggabung kelas CSS -- menerima string, falsy, dan objek kondisional. */
export type ClassValue = string | false | null | undefined | Record<string, boolean> | ClassValue[];

export function cx(...values: ClassValue[]): string {
    const out: string[] = [];

    const walk = (value: ClassValue): void => {
        if (!value) {
            return;
        }

        if (typeof value === 'string') {
            out.push(value);
            return;
        }

        if (Array.isArray(value)) {
            value.forEach(walk);
            return;
        }

        for (const [key, active] of Object.entries(value)) {
            if (active) {
                out.push(key);
            }
        }
    };

    values.forEach(walk);

    return out.join(' ');
}
