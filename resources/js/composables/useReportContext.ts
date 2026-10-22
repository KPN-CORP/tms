import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types/inertia';

/**
 * Konteks Report harus ikut terbawa di setiap form pada halaman yang dibuka
 * dari menu Report, atau kewenangan lintas organisasi hilang pada request
 * berikutnya (lihat App\Support\ReportOverride).
 *
 * Penanda ini menyatakan NIAT, bukan izin: tanpa permission 'override.role'
 * server tidak memberi kewenangan apa pun karenanya.
 */
export function reportFields(): { from?: 'report' } {
    const page = usePage<SharedProps>();

    return page.props.reportContext ? { from: 'report' } : {};
}
