import { useLocale } from '@/composables/useLocale';

/**
 * Terjemahan untuk teks yang DIKIRIM SERVER.
 *
 * Sebagian label tidak ditulis di Vue melainkan datang dari PHP: label status
 * (Project::STATUS_BADGES, Idea::STATUS_LABELS), judul tab daftar, label kartu
 * Dashboard (MetricScopeService::METRICS) dan nama kolom Report. Semuanya
 * dipasangkan dengan sebuah KUNCI yang juga dikirim -- kode status, `key` tab,
 * `metric` kartu -- jadi yang diterjemahkan adalah kuncinya, bukan teksnya.
 *
 * Label dari server selalu jadi cadangan: menambah status atau tab baru di PHP
 * tidak pernah membuat layar kosong, hanya membuatnya tetap berbahasa Inggris
 * sampai kuncinya dimasukkan ke resources/js/locales.
 */
export function useLabels() {
    const { t } = useLocale();

    const pick = (
        section: Record<string, string>,
        key: string | null | undefined,
        fallback?: string | null,
    ): string => section[key ?? ''] ?? fallback ?? String(key ?? '');

    return {
        ideaStatus: (code: string | null | undefined, fallback?: string | null) =>
            pick(t.value.status.idea, code, fallback),

        projectStatus: (code: string | null | undefined, fallback?: string | null) =>
            pick(t.value.status.project, code, fallback),

        shellStatus: (code: string | null | undefined, fallback?: string | null) =>
            pick(t.value.status.shell, code, fallback),

        decision: (code: string | null | undefined, fallback?: string | null) =>
            pick(t.value.status.decision, code, fallback),

        /**
         * Judul tab. Label Inggris dicoba DULU, baru kuncinya: kunci tab tidak
         * unik antar halaman -- 'review' berarti "On Review" di Project
         * Proposal tapi "Completion Review" di Project Completion, sedangkan
         * labelnya selalu membedakan keduanya.
         */
        tab: (key: string, fallback?: string | null) => {
            const table = t.value.tabs as Record<string, string | undefined>;

            return (
                (fallback ? table[slugify(fallback)] : undefined) ??
                table[key] ??
                fallback ??
                key
            );
        },

        metric: (key: string, fallback?: string | null) => pick(t.value.metrics, key, fallback),

        /**
         * Nama kolom Report. ReportController menyimpan labelnya sebagai teks
         * Inggris (dipakai juga untuk header CSV, jadi tidak diubah); di sini
         * teks itu di-slug jadi kunci camelCase lalu dicari di kamus.
         */
        field: (label: string) => pick(t.value.fields, slugify(label), label),

        /** CommitteeAssignment::TYPES — kunci = kode approval_type. */
        approvalType: (code: string | null | undefined, fallback?: string | null) =>
            pick(t.value.approvalTypes, code, fallback),

        /** ProjectUpdate::CHANGE_TYPES, termasuk jenis lama. */
        changeType: (code: string | null | undefined, fallback?: string | null) =>
            pick(t.value.changeTypes, code, fallback),

        /** ReportController::TYPES. */
        reportType: (code: string | null | undefined, fallback?: string | null) =>
            pick(t.value.reportTypes, code, fallback),

        /** Kolom Subject di Activity Log; kuncinya nama model (class_basename). */
        subjectType: (name: string | null | undefined, fallback?: string | null) =>
            pick(t.value.subjectTypes, name, fallback),

        /** Izin Spatie; kuncinya nama permission apa adanya ('idea.create'). */
        permission: (name: string | null | undefined, fallback?: string | null) =>
            pick(t.value.permissions as unknown as Record<string, string>, name, fallback),

        /** Judul tab grup izin; kuncinya nama grup Inggris di-slug. */
        permissionGroup: (label: string) =>
            pick(t.value.permissions.groups, slugify(label), label),
    };
}

/** 'Business Unit' -> 'businessUnit', 'Idea ID' -> 'ideaId'. */
export function slugify(label: string): string {
    const words = label.split(/[^A-Za-z0-9]+/).filter(Boolean);

    return words
        .map((word, index) => {
            const lower = word.toLowerCase();

            return index === 0 ? lower : lower.charAt(0).toUpperCase() + lower.slice(1);
        })
        .join('');
}
