import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';

/** Nilai yang bisa dibandingkan. Sel kosong tidak ikut diurutkan — lihat bawah. */
type SortValue = string | number | boolean | null | undefined;

interface Options<T> {
    /** SELURUH baris, bukan satu halaman — komposable ini yang memotongnya. */
    rows: MaybeRefOrGetter<T[]>;
    /**
     * [kunci publik => pembaca nilai]. Hanya kunci yang terdaftar di sini yang
     * bisa di-sort, sama seperti whitelist `sortable` di Concerns\HasListQuery.
     */
    columns: Record<string, (row: T) => SortValue>;
    defaultSort?: string | null;
    defaultDir?: 'asc' | 'desc';
    /** Baris per halaman awal; ikut whitelist UiListFooter (10|25|50|100). */
    perPage?: number;
}

/**
 * Sort + paginasi di KLIEN untuk tabel yang datanya sudah utuh di payload.
 *
 * Pasangannya `useListQuery` dipakai ketika daftarnya dipaginasi server (My
 * Ideas, Report, Activity Log): di sana setiap klik kolom berarti satu
 * kunjungan baru. Halaman konfigurasi admin dan tabel di dalam Project Detail
 * sebaliknya sudah menerima koleksinya secara penuh, jadi memutarnya kembali ke
 * server hanya menambah ronde jaringan tanpa menambah data.
 *
 *     const table = useClientTable({
 *         rows: () => props.categories,
 *         columns: { code: (row) => row.code, name: (row) => row.name },
 *         defaultSort: 'code',
 *     })
 *
 * `table.rows` adalah irisan halaman aktif; `table.meta` langsung cocok dengan
 * prop `meta` milik UiListFooter.
 */
export function useClientTable<T>({
    rows,
    columns,
    defaultSort = null,
    defaultDir = 'asc',
    perPage = 10,
}: Options<T>) {
    const sort = ref<string | null>(defaultSort);
    const dir = ref<'asc' | 'desc'>(defaultDir);
    const page = ref(1);

    // Bertipe longgar karena terikat ke <select> UiListFooter, yang bisa
    // mengirim balik string.
    const size = ref<number | string | undefined>(perPage);

    const source = computed<T[]>(() => toValue(rows));

    const pageSize = computed(() => {
        const value = Number(size.value);

        return Number.isFinite(value) && value > 0 ? value : perPage;
    });

    const isEmpty = (value: SortValue): boolean =>
        value === null || value === undefined || value === '';

    const rank = (a: SortValue, b: SortValue): number => {
        if (typeof a === 'number' && typeof b === 'number') {
            return a - b;
        }

        if (typeof a === 'boolean' || typeof b === 'boolean') {
            return Number(a) - Number(b);
        }

        // `numeric` membuat "Layer 2" berada sebelum "Layer 10", dan ID
        // berakhiran angka urut seperti manusia membacanya.
        return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
    };

    const sorted = computed<T[]>(() => {
        const key = sort.value;
        const read = key === null ? undefined : columns[key];

        if (!read) {
            return source.value;
        }

        const factor = dir.value === 'desc' ? -1 : 1;

        // Array.prototype.sort stabil, jadi baris dengan nilai sama tetap pada
        // urutan yang dikirim server (mis. SLA: approval_type lalu status).
        return [...source.value].sort((rowA, rowB) => {
            const a = read(rowA);
            const b = read(rowB);

            // Sel kosong selalu di bawah, apa pun arah sortnya: baris tanpa
            // nilai bukan "yang terkecil", ia tidak punya tempat dalam urutan.
            if (isEmpty(a) || isEmpty(b)) {
                return isEmpty(a) && isEmpty(b) ? 0 : isEmpty(a) ? 1 : -1;
            }

            return factor * rank(a, b);
        });
    });

    const total = computed(() => sorted.value.length);
    const lastPage = computed(() => Math.max(1, Math.ceil(total.value / pageSize.value)));

    // Dijepit, bukan di-reset: menghapus baris terakhir di halaman 3 memindahkan
    // pengguna ke halaman 2, bukan melemparnya kembali ke halaman 1.
    const currentPage = computed(() => Math.min(Math.max(1, page.value), lastPage.value));
    const offset = computed(() => (currentPage.value - 1) * pageSize.value);

    const paged = computed<T[]>(() => sorted.value.slice(offset.value, offset.value + pageSize.value));

    const meta = computed(() => ({
        current_page: currentPage.value,
        last_page: lastPage.value,
        from: total.value === 0 ? null : offset.value + 1,
        to: total.value === 0 ? null : offset.value + paged.value.length,
        total: total.value,
    }));

    /** Kolom yang sama membalik arah, kolom lain mulai dari 'asc'. */
    const sortBy = (key: string): void => {
        if (!(key in columns)) {
            return;
        }

        if (sort.value === key) {
            dir.value = dir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sort.value = key;
            dir.value = 'asc';
        }

        page.value = 1;
    };

    const setPage = (value: number): void => {
        page.value = value;
    };

    watch(pageSize, () => {
        page.value = 1;
    });

    return { rows: paged, sort, dir, perPage: size, page: currentPage, meta, offset, sortBy, setPage };
}
