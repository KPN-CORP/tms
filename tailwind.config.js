import forms from '@tailwindcss/forms';

/**
 * Warna ditulis sebagai token SEMANTIK (surface, ink, brand, success, …) yang
 * nilainya datang dari CSS custom property di resources/css/app.css. Komponen
 * tidak pernah menyebut `gray-200` / `red-700` langsung, jadi mengganti tema —
 * termasuk mode gelap — cukup di satu berkas.
 *
 * Format `rgb(var(--x) / <alpha-value>)` dipakai supaya sufiks opacity Tailwind
 * (`bg-surface/50`) tetap bekerja.
 */
const token = (name) => `rgb(var(--color-${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,ts,js}',
        // Sebagian kelas ditulis di PHP (mis. Project::STATUS_BADGES, kelas badge
        // di controller). Tanpa baris ini kelas tsb terbuang saat build.
        './app/**/*.php',
    ],

    theme: {
        extend: {
            // Persis --font-sans milik kpn-tmp. Ditulis utuh, bukan ditempel di
            // depan tumpukan bawaan Tailwind, supaya urutan cadangannya sama
            // kalau webfont-nya gagal dimuat.
            fontFamily: {
                sans: [
                    'Albert Sans',
                    'ui-sans-serif',
                    'system-ui',
                    'sans-serif',
                    'Apple Color Emoji',
                    'Segoe UI Emoji',
                    'Segoe UI Symbol',
                    'Noto Color Emoji',
                ],
            },

            colors: {
                brand: {
                    soft: token('brand-soft'),
                    DEFAULT: token('brand'),
                    strong: token('brand-strong'),
                    ink: token('brand-ink'),
                },
                surface: {
                    sunken: token('surface-sunken'),
                    DEFAULT: token('surface'),
                    raised: token('surface-raised'),
                    muted: token('surface-muted'),
                },
                line: {
                    DEFAULT: token('line'),
                    strong: token('line-strong'),
                },
                ink: {
                    DEFAULT: token('ink'),
                    muted: token('ink-muted'),
                    subtle: token('ink-subtle'),
                    label: token('ink-label'),
                    inverted: token('ink-inverted'),
                },
                success: { soft: token('success-soft'), DEFAULT: token('success'), ink: token('success-ink') },
                warning: { soft: token('warning-soft'), DEFAULT: token('warning'), ink: token('warning-ink') },
                danger: { soft: token('danger-soft'), DEFAULT: token('danger'), ink: token('danger-ink') },
                info: { soft: token('info-soft'), DEFAULT: token('info'), ink: token('info-ink') },
                neutral: { soft: token('neutral-soft'), DEFAULT: token('neutral'), ink: token('neutral-ink') },
                avatar: token('avatar'),
            },

            // Kartu rounded-xl, kontrol rounded-md -- mengikuti kpn-tmp.
            borderRadius: {
                card: '0.75rem',
                control: '0.375rem',
            },

            boxShadow: {
                card: '0 1px 2px rgb(0 0 0 / 0.04)',
                raised: '0 4px 12px rgb(0 0 0 / 0.06)',
                overlay: '0 24px 48px -12px rgb(15 23 42 / 0.25)',
            },

            // Tinggi kontrol tunggal — dipakai input, select, dan tombol agar
            // semuanya sebaris di toolbar (dulu ditulis manual `h-[38px]`).
            height: { control: '2.375rem', topbar: 'var(--topbar-height)' },
            minHeight: { control: '2.375rem' },

            // Ukuran cangkang aplikasi; nilainya dari :root di app.css.
            width: { sidebar: 'var(--sidebar-width)' },
            margin: { sidebar: 'var(--sidebar-width)' },

            keyframes: {
                'fade-in': { from: { opacity: '0' }, to: { opacity: '1' } },
                'slide-up': {
                    from: { opacity: '0', transform: 'translateY(0.5rem)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                'slide-in-right': {
                    from: { opacity: '0', transform: 'translateX(1rem)' },
                    to: { opacity: '1', transform: 'translateX(0)' },
                },
            },
            animation: {
                'fade-in': 'fade-in 150ms ease-out',
                'slide-up': 'slide-up 180ms cubic-bezier(0.16, 1, 0.3, 1)',
                'slide-in-right': 'slide-in-right 220ms cubic-bezier(0.16, 1, 0.3, 1)',
            },
        },
    },

    plugins: [forms],
};
