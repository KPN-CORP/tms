import { onBeforeUnmount, onMounted, readonly, ref } from 'vue';

export type ThemePreference = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'tms.theme';

/** Pilihan pengguna: terang, gelap, atau ikut sistem. */
const preference = ref<ThemePreference>('system');

/** Hasil akhirnya — yang benar-benar terpasang di <html>. */
const isDark = ref(false);

const systemQuery = (): MediaQueryList | null =>
    typeof window === 'undefined' ? null : window.matchMedia('(prefers-color-scheme: dark)');

function resolve(value: ThemePreference): boolean {
    return value === 'dark' || (value === 'system' && (systemQuery()?.matches ?? false));
}

function applyToDocument(value: ThemePreference): void {
    const dark = resolve(value);

    document.documentElement.classList.toggle('dark', dark);
    isDark.value = dark;
}

/**
 * Mode terang/gelap. Nilainya berupa token CSS (resources/css/app.css), jadi
 * tidak ada komponen yang perlu tahu tema sedang aktif yang mana.
 *
 * Kelas `dark` sudah dipasang skrip kecil di <head> sebelum paint pertama, jadi
 * composable ini hanya menyelaraskan state Vue dengan yang sudah ada.
 */
export function useTheme() {
    const onSystemChange = (): void => {
        // Hanya berpengaruh saat pengguna memilih "System"; kalau ia sudah
        // menetapkan terang/gelap, perubahan tema OS tidak boleh menimpanya.
        if (preference.value === 'system') {
            applyToDocument('system');
        }
    };

    onMounted(() => {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);

            preference.value = stored === 'dark' || stored === 'light' ? stored : 'system';
        } catch {
            preference.value = 'system';
        }

        applyToDocument(preference.value);
        systemQuery()?.addEventListener('change', onSystemChange);
    });

    onBeforeUnmount(() => systemQuery()?.removeEventListener('change', onSystemChange));

    const setPreference = (value: ThemePreference): void => {
        preference.value = value;
        applyToDocument(value);

        try {
            if (value === 'system') {
                localStorage.removeItem(STORAGE_KEY);
            } else {
                localStorage.setItem(STORAGE_KEY, value);
            }
        } catch {
            // Mode privat memblokir localStorage — tema tetap berlaku untuk sesi ini.
        }
    };

    /** Balik ke kebalikan dari yang SEDANG tampil, bukan dari preferensinya. */
    const toggle = (): void => setPreference(isDark.value ? 'light' : 'dark');

    return { preference: readonly(preference), isDark: readonly(isDark), setPreference, toggle };
}
