import { shallowReactive } from 'vue';
import en from './en';

/** Bentuk acuan kamus; setiap bahasa lain diketik terhadap ini. */
export type LocaleMessages = typeof en;

export type Locale = 'en' | 'id';

export const DEFAULT_LOCALE: Locale = 'en';

/**
 * English ikut bundel utama — ia bentuk acuan sekaligus cadangan selama bahasa
 * lain masih dimuat. Bahasa lain jadi chunk tersendiri dan baru diunduh saat
 * dipilih, jadi sesi yang tidak pernah menggantinya tidak membayar apa pun.
 */
const loaders: Record<Exclude<Locale, 'en'>, () => Promise<LocaleMessages>> = {
    id: () => import('./id').then((module) => module.default),
};

/** Kamus yang sudah dimuat, dikunci per locale. */
export const locales = shallowReactive<Partial<Record<Locale, LocaleMessages>>>({ en });

export const isLocale = (value: unknown): value is Locale => value === 'en' || value === 'id';

/** Muat kamus sebuah bahasa bila belum ada. */
export async function loadLocale(locale: Locale): Promise<void> {
    if (locale === 'en' || locales[locale]) {
        return;
    }

    locales[locale] = await loaders[locale]();
}
