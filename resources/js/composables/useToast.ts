import { readonly, ref } from 'vue';

export type ToastTone = 'success' | 'error' | 'info';

export interface Toast {
    id: number;
    tone: ToastTone;
    message: string;
}

const toasts = ref<Toast[]>([]);
const timers = new Map<number, ReturnType<typeof setTimeout>>();

let nextId = 0;

/**
 * Notifikasi ringkas, sumbernya prop `flash` dari HandleInertiaRequests.
 *
 * Dulu tiap halaman Blade menulis sendiri blok `@if(session('success'))` --
 * tampilannya berbeda-beda dan mudah terlupa di halaman baru. Sekarang cangkang
 * aplikasi yang menampilkannya, sekali untuk semua halaman.
 */
export function useToast() {
    const dismiss = (id: number): void => {
        const timer = timers.get(id);

        if (timer) {
            clearTimeout(timer);
            timers.delete(id);
        }

        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    };

    const push = (tone: ToastTone, message: string, durationMs = 5000): number => {
        const id = ++nextId;

        toasts.value = [...toasts.value, { id, tone, message }];

        // Pesan kesalahan dibiarkan sampai ditutup sendiri: biasanya ada yang
        // perlu dibaca atau disalin.
        if (tone !== 'error') {
            timers.set(id, setTimeout(() => dismiss(id), durationMs));
        }

        return id;
    };

    return {
        toasts: readonly(toasts),
        push,
        dismiss,
        success: (message: string) => push('success', message),
        error: (message: string) => push('error', message),
        info: (message: string) => push('info', message),
    };
}
