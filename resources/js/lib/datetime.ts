/**
 * Waktu ditampilkan dalam zona PERANGKAT pengguna.
 *
 * Server selalu mengirim ISO-8601 UTC; konversi terjadi di sini. Label zona
 * mengikuti aturan lama: Indonesia dapat WIB/WITA/WIT, selainnya GMT+HH[:MM].
 *
 * Versi Blade dulu memindai `<time>` saat DOMContentLoaded -- cara itu tidak
 * jalan di SPA karena DOM diganti tanpa reload. Di sini nilainya dihitung saat
 * render, jadi selalu benar termasuk sesudah navigasi Inertia.
 */

const ID_ZONES: Record<string, string> = {
    'Asia/Jakarta': 'WIB',
    'Asia/Pontianak': 'WIB',
    'Asia/Makassar': 'WITA',
    'Asia/Ujung_Pandang': 'WITA',
    'Asia/Jayapura': 'WIT',
};

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export type DateTimeMode = 'datetime' | 'date' | 'time';

const pad = (n: number): string => String(n).padStart(2, '0');

/** Nama zona perangkat, dihitung sekali -- tidak berubah selama sesi. */
let cachedZone: string | null = null;

export function zoneName(): string {
    if (cachedZone !== null) {
        return cachedZone;
    }

    let tz = '';

    try {
        tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch {
        tz = '';
    }

    const indonesian = ID_ZONES[tz];

    if (indonesian) {
        return (cachedZone = indonesian);
    }

    // Di luar Indonesia: GMT dari offset perangkat (menit, tandanya terbalik).
    const offset = -new Date().getTimezoneOffset();
    const sign = offset < 0 ? '-' : '+';
    const abs = Math.abs(offset);
    const minutes = abs % 60;

    return (cachedZone = `GMT${sign}${pad(Math.floor(abs / 60))}${minutes ? `:${pad(minutes)}` : ''}`);
}

function parse(value: string | number | Date | null | undefined): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value: string | number | Date | null | undefined, fallback = '-'): string {
    const d = parse(value);

    return d ? `${pad(d.getDate())} ${MONTHS[d.getMonth()]} ${d.getFullYear()}` : fallback;
}

export function formatTime(value: string | number | Date | null | undefined, fallback = '-'): string {
    const d = parse(value);

    return d ? `${pad(d.getHours())}:${pad(d.getMinutes())} ${zoneName()}` : fallback;
}

export function formatDateTime(
    value: string | number | Date | null | undefined,
    mode: DateTimeMode = 'datetime',
    fallback = '-',
): string {
    const d = parse(value);

    if (!d) {
        return fallback;
    }

    if (mode === 'date') {
        return formatDate(d);
    }

    if (mode === 'time') {
        return formatTime(d);
    }

    return `${formatDate(d)} ${formatTime(d)}`;
}

/** Teks lengkap untuk atribut `title` -- berguna saat tabel memendekkan tanggal. */
export function fullTimestamp(value: string | number | Date | null | undefined): string | undefined {
    return parse(value)?.toString();
}

/**
 * Nilai untuk `<input type="date">`, yang selalu minta `YYYY-MM-DD` waktu lokal.
 * Memakai slice ISO di sini akan menggeser tanggal satu hari untuk zona UTC+n.
 */
export function toDateInput(value: string | number | Date | null | undefined): string {
    const d = parse(value);

    return d ? `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` : '';
}
