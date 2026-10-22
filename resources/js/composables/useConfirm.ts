import { readonly, ref } from 'vue';
import type { Tone } from '@/lib/status';

export interface ConfirmOptions {
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** 'danger' untuk tindakan merusak (hapus, cancel project). */
    tone?: Extract<Tone, 'brand' | 'danger' | 'warning'>;
}

/**
 * Judul dan label tombol sengaja TIDAK diisi di sini meski kosong: teks
 * bawaannya bergantung bahasa, dan dialog bisa saja terbuka saat bahasanya
 * diganti. UiConfirmDialog yang menambal dari kamus saat merender, jadi teksnya
 * ikut berubah seketika.
 */
interface PendingConfirm extends ConfirmOptions {
    tone: NonNullable<ConfirmOptions['tone']>;
    resolve: (confirmed: boolean) => void;
}

const pending = ref<PendingConfirm | null>(null);

/**
 * Dialog konfirmasi tunggal untuk seluruh aplikasi.
 *
 * Menggantikan listener `data-confirm` global di layouts/app.blade.php yang
 * memasang handler klik ke seluruh dokumen. Di sini pemanggilnya eksplisit dan
 * menunggu hasil:
 *
 *     if (await confirm({ message: 'Delete this draft?', tone: 'danger' })) {
 *         router.delete(...)
 *     }
 */
export function useConfirm() {
    const confirm = (options: ConfirmOptions): Promise<boolean> =>
        new Promise((resolve) => {
            // Permintaan baru membatalkan yang belum dijawab, supaya promise lama
            // tidak menggantung selamanya.
            pending.value?.resolve(false);

            pending.value = {
                ...options,
                tone: options.tone ?? 'brand',
                resolve,
            };
        });

    const settle = (confirmed: boolean): void => {
        pending.value?.resolve(confirmed);
        pending.value = null;
    };

    return {
        confirm,
        pending: readonly(pending),
        accept: () => settle(true),
        cancel: () => settle(false),
    };
}
