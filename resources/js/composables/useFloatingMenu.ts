import { computed, onBeforeUnmount, onMounted, ref, watch, type CSSProperties } from 'vue';

/**
 * Menu melayang untuk dropdown buatan sendiri (select, combobox, multi-select).
 *
 * Menunya di-teleport ke <body> dan diposisikan `fixed` terhadap pemicunya,
 * supaya tidak terpotong oleh leluhur yang ber-overflow — dialog, badan kartu,
 * atau tabel yang menggulir mendatar. Posisi itu dihitung ulang saat halaman
 * menggulir atau jendela berubah ukuran, dan menu dibalik ke atas bila ruang di
 * bawah pemicu tidak cukup.
 */
export function useFloatingMenu(options: { minHeight?: number; maxHeight?: number } = {}) {
    const minHeight = options.minHeight ?? 160;
    const maxHeight = options.maxHeight ?? 320;

    const trigger = ref<HTMLElement | null>(null);
    const menu = ref<HTMLElement | null>(null);
    const open = ref(false);

    const box = ref({ left: 0, top: 0, width: 0, height: maxHeight, openUp: false });

    const updatePosition = (): void => {
        const el = trigger.value;

        if (!el) {
            return;
        }

        const rect = el.getBoundingClientRect();
        const gap = 4;
        const below = window.innerHeight - rect.bottom - gap;
        const above = rect.top - gap;

        // Dibalik ke atas hanya bila ruang di bawah sempit DAN di atas lebih lega.
        const openUp = below < 240 && above > below;

        box.value = {
            left: rect.left,
            top: openUp ? rect.top - gap : rect.bottom + gap,
            width: rect.width,
            height: Math.max(minHeight, Math.min(maxHeight, openUp ? above : below)),
            openUp,
        };
    };

    const menuStyle = computed<CSSProperties>(() => ({
        position: 'fixed',
        left: `${box.value.left}px`,
        top: `${box.value.top}px`,
        width: `${box.value.width}px`,
        maxHeight: `${box.value.height}px`,
        // Dialog memakai z-50, jadi menu harus di atasnya.
        zIndex: 60,
        ...(box.value.openUp ? { transform: 'translateY(-100%)' } : {}),
    }));

    const close = (): void => {
        open.value = false;
    };

    const show = (): void => {
        updatePosition();
        open.value = true;
    };

    const toggle = (): void => {
        if (open.value) {
            close();
        } else {
            show();
        }
    };

    /** mousedown, bukan click: menu harus tertutup sebelum fokus berpindah. */
    const onDocumentMouseDown = (event: MouseEvent): void => {
        const target = event.target as Node;

        if (!trigger.value?.contains(target) && !menu.value?.contains(target)) {
            close();
        }
    };

    const onEscape = (event: KeyboardEvent): void => {
        if (event.key === 'Escape' && open.value) {
            close();
        }
    };

    // Pendengar reposisi hanya dipasang selama menu terbuka; `true` pada scroll
    // supaya ikut menangkap gulir dari kontainer di dalam halaman, bukan hanya
    // jendela.
    watch(open, (isOpen) => {
        if (isOpen) {
            window.addEventListener('scroll', updatePosition, true);
            window.addEventListener('resize', updatePosition);
        } else {
            window.removeEventListener('scroll', updatePosition, true);
            window.removeEventListener('resize', updatePosition);
        }
    });

    onMounted(() => {
        document.addEventListener('mousedown', onDocumentMouseDown);
        document.addEventListener('keydown', onEscape);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('mousedown', onDocumentMouseDown);
        document.removeEventListener('keydown', onEscape);
        window.removeEventListener('scroll', updatePosition, true);
        window.removeEventListener('resize', updatePosition);
    });

    return { trigger, menu, open, menuStyle, toggle, show, close, updatePosition };
}
