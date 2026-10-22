import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import type { Config as ZiggyConfig } from 'ziggy-js';

/** Satu item menu sidebar, disusun App\Support\Navigation\SidebarBuilder. */
export interface NavItem {
    /** Kunci terjemahan di resources/js/locales/*.ts, bagian `nav`. */
    key: string;
    /** Teks cadangan dari server, dipakai bila kuncinya belum diterjemahkan. */
    label: string;
    href: string | null;
    badge: number;
    active: boolean;
    /** True selama halaman tujuan masih dirender Blade (lihat InertiaRoutes). */
    external: boolean;
    children: NavItem[];
}

export interface NavGroup {
    /** Kunci terjemahan di bagian `nav.groups`; null untuk grup tanpa judul. */
    key: string | null;
    label: string | null;
    items: NavItem[];
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

/** Prop yang dibagikan ke semua halaman oleh HandleInertiaRequests. */
export interface SharedProps extends InertiaPageProps {
    auth: {
        user: AuthUser | null;
        /** Peta izin Spatie; Gate::before membuat Super Admin `true` di semuanya. */
        can: Record<string, boolean>;
    };
    /** True hanya saat halaman dibuka lewat menu Report oleh pemegang override.role. */
    reportContext: boolean;
    nav: { groups: NavGroup[] };
    flash: Partial<Record<'success' | 'error' | 'status', string>>;
    /** Kesalahan validasi Laravel, diisi Inertia dari session. */
    errors: Record<string, string>;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof import('ziggy-js').route;
        Ziggy: ZiggyConfig;
    }
}

declare global {
    // eslint-disable-next-line no-var
    var Ziggy: ZiggyConfig;
}

export {};
