import { computed, ref, watch, type ComputedRef } from 'vue';
import { DEFAULT_LOCALE, isLocale, loadLocale, locales, type Locale, type LocaleMessages } from '@/locales';

export type { Locale, LocaleMessages };

const STORAGE_KEY = 'tms-locale';

/**
 * Pilihan yang sama juga ditulis sebagai cookie, karena ada teks yang tidak
 * dirender Vue: pesan validasi Laravel dan flash message. Server membacanya di
 * App\Http\Middleware\SetLocale. Satu tahun sudah cukup -- preferensi ini
 * tidak perlu bertahan lebih lama dari itu, dan SameSite=Lax menutup
 * pengirimannya dari situs lain.
 */
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

function writeCookie(locale: Locale): void {
    try {
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';

        document.cookie = `${STORAGE_KEY}=${locale}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax${secure}`;
    } catch {
        // Cookie diblokir: teks sisi server tetap English, antarmukanya tidak.
    }
}

/**
 * Pilihan bahasa pengguna. Disimpan di localStorage — sama seperti preferensi
 * tema (useTheme), jadi tidak ada kolom di database dan mengganti bahasa tidak
 * memuat ulang halaman maupun menyentuh sesi. Yang ikut ke server hanyalah
 * cookie di atas, dan hanya supaya pesan buatan server memakai bahasa yang
 * sama.
 *
 * Bawaan: English. Pilihan lama yang tidak dikenal (mis. kode bahasa yang
 * sudah dihapus) diabaikan, bukan dipaksakan.
 */
function storedLocale(): Locale {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        if (isLocale(stored)) {
            return stored;
        }
    } catch {
        // localStorage bisa dilarang (mode privat / kebijakan browser).
        // Diamkan — bahasanya tetap jalan untuk sesi ini.
    }

    return DEFAULT_LOCALE;
}

// Satu ref dipakai bersama seluruh aplikasi, jadi semua komponen ikut berubah
// dalam satu kali render saat bahasanya diganti.
const currentLocale = ref<Locale>(storedLocale());

watch(
    currentLocale,
    (locale) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, locale);
        } catch {
            // Sama seperti di atas: gagal menyimpan tidak membatalkan pilihan.
        }

        // Ditulis ulang di setiap boot, bukan hanya saat diganti: kalau
        // cookie-nya kedaluwarsa sementara localStorage masih ingat, keduanya
        // kembali sejalan pada permintaan berikutnya.
        writeCookie(locale);

        // Penting untuk pembaca layar dan pemenggalan kata browser.
        document.documentElement.lang = locale;
    },
    { immediate: true },
);

/**
 * Muat kamus yang tersimpan SEBELUM aplikasi di-mount, supaya render pertama
 * sudah dalam bahasa itu alih-alih berkedip dari English.
 */
export function prepareLocale(): Promise<void> {
    return loadLocale(currentLocale.value).catch(() => {
        // Chunk gagal diunduh (offline, deploy di tengah jalan): turunkan ke
        // English untuk sesi ini daripada merender aplikasi tanpa teks.
        currentLocale.value = DEFAULT_LOCALE;
    });
}

export interface UseLocale {
    locale: typeof currentLocale;
    t: ComputedRef<LocaleMessages>;
    setLocale: (locale: Locale) => Promise<void>;
}

export function useLocale(): UseLocale {
    // `locales.en` selalu ada (ikut bundel utama); fallback-nya menutup celah
    // satu tick saat bahasa yang baru dipilih masih dimuat.
    const t = computed<LocaleMessages>(() => locales[currentLocale.value] ?? locales.en!);

    async function setLocale(locale: Locale): Promise<void> {
        await loadLocale(locale);
        currentLocale.value = locale;
    }

    return { locale: currentLocale, t, setLocale };
}

/**
 * Isi placeholder bergaya `{name}` pada sebuah teks kamus.
 *
 *     tr(t.shell.tasksWaiting, { count: 3 })
 */
export function tr(template: string, values: Record<string, string | number>): string {
    return template.replace(/\{(\w+)\}/g, (match, key: string) =>
        key in values ? String(values[key]) : match,
    );
}
