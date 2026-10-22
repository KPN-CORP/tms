import { reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { cleanQuery, type QueryPayload } from '@/lib/query';
import type { ListQuery } from '@/types/models';

interface Options {
    /** URL endpoint daftar; biasanya `route('ideas.index')`. */
    url: string;
    /** Nilai filter awal dari server (request()->query di controller). */
    initial: ListQuery;
    /**
     * Prop yang perlu dimuat ulang. Dengan ini Inertia melakukan partial reload:
     * hanya prop daftar yang dihitung ulang di server, sidebar dan peta izin
     * dilewati karena di-share sebagai closure.
     */
    only: string[];
    /** Jeda sebelum ketikan pencarian dikirim. */
    debounceMs?: number;
}

/**
 * Sisi klien dari Concerns\HasListQuery.
 *
 * Dulu setiap filter memanggil `form.requestSubmit()` sehingga seluruh halaman
 * dimuat ulang. Sekarang perubahan filter jadi partial reload yang menjaga
 * posisi scroll dan fokus input, dan riwayat browser ditimpa (`replace`) supaya
 * tombol Back tidak menelusuri setiap ketikan.
 */
export function useListQuery({ url, initial, only, debounceMs = 400 }: Options) {
    const filters = reactive<ListQuery>({ ...initial });
    const loading = ref(false);

    let timer: ReturnType<typeof setTimeout> | undefined;

    const payload = (): QueryPayload => cleanQuery(filters);

    const visit = (): void => {
        router.get(url, payload(), {
            only,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        });
    };

    const apply = (immediate = true): void => {
        clearTimeout(timer);

        if (immediate) {
            visit();
            return;
        }

        timer = setTimeout(visit, debounceMs);
    };

    /** Pindah halaman selalu mengulang dari atas. */
    const setPage = (page: number): void => {
        filters.page = page;
        clearTimeout(timer);

        router.get(url, payload(), {
            only,
            preserveState: true,
            preserveScroll: false,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        });
    };

    /**
     * Klik kolom: kolom yang sama membalik arah, kolom lain mulai dari 'asc'.
     * Kunci sort tetap divalidasi server (whitelist di HasListQuery).
     */
    const sortBy = (key: string): void => {
        if (filters.sort === key) {
            filters.dir = filters.dir === 'asc' ? 'desc' : 'asc';
        } else {
            filters.sort = key;
            filters.dir = 'asc';
        }

        filters.page = undefined;
        apply();
    };

    /**
     * Kunci yang BUKAN filter: tidak boleh ikut memicu pemuatan ulang sendiri
     * (sort & page punya jalurnya masing-masing) atau bukan milik daftar.
     */
    const NON_FILTER_KEYS = ['q', 'page', 'sort', 'dir', 'type', 'metric', 'tab'];

    /**
     * Semua filter selain pencarian, sebagai satu sidik jari.
     *
     * Dibuat generik dengan sengaja: halaman memakai dua keluarga nama filter
     * (`bu`/`unit` berbasis nama, `business_unit_id`/`department_id` berbasis
     * FK). Menyebut nama-nama itu satu per satu di sini membuat filter yang
     * tidak terdaftar diam-diam tidak bereaksi.
     */
    const filterFingerprint = (): string =>
        JSON.stringify(
            Object.entries(filters)
                .filter(([key]) => !NON_FILTER_KEYS.includes(key))
                .sort(([a], [b]) => a.localeCompare(b)),
        );

    const reset = (): void => {
        for (const key of Object.keys(filters)) {
            // Jenis laporan & konteks metrik bukan filter — Reset tidak
            // memindahkan pengguna ke laporan lain.
            if (!['type', 'metric'].includes(key)) {
                filters[key] = undefined;
            }
        }

        apply();
    };

    /** Perubahan filter selain pencarian mengembalikan daftar ke halaman 1. */
    watch(filterFingerprint, () => {
        filters.page = undefined;
        apply();
    });

    watch(
        () => filters.q,
        () => {
            filters.page = undefined;
            apply(false);
        },
    );

    const hasActiveFilters = (): boolean =>
        Boolean(filters.q) ||
        Object.entries(filters).some(
            ([key, value]) =>
                !NON_FILTER_KEYS.includes(key) &&
                key !== 'per_page' &&
                value !== undefined &&
                value !== null &&
                value !== '',
        );

    return { filters, loading, apply, reset, sortBy, setPage, hasActiveFilters };
}
