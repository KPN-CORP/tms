<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppSidebar from './partials/AppSidebar.vue';
import AppTopbar from './partials/AppTopbar.vue';
import { UiConfirmDialog, UiToastHost } from '@/components/ui';

/**
 * Cangkang aplikasi -- layout PERSISTEN.
 *
 * app.ts memasangnya sekali lalu menukar isi halamannya saja, jadi sidebar,
 * topbar, dan posisi scroll menu bertahan saat berpindah halaman. Itu pula
 * sebabnya state di sini (mis. sidebar terbuka) tidak hilang antar navigasi.
 */

const SIDEBAR_KEY = 'tms.sidebarOpen';
const MOBILE_BREAKPOINT = 1024;

const sidebarOpen = ref(true);
const isMobile = ref(false);

/** Sudah pernah diselaraskan? Dipakai agar pemanggilan pertama selalu berlaku. */
let synced = false;

const syncViewport = (): void => {
    const mobile = window.innerWidth < MOBILE_BREAKPOINT;

    // Hanya saat pertama kali, atau saat benar-benar melewati breakpoint. Di
    // luar itu mengubah ukuran jendela tidak boleh membatalkan pilihan
    // pengguna yang baru saja menutup atau membuka sidebar.
    if (!synced || mobile !== isMobile.value) {
        isMobile.value = mobile;

        // Layar sempit: sidebar jadi panel melayang dan selalu mulai tertutup.
        // Layar lebar: terbuka, kecuali pengguna memang pernah menutupnya.
        sidebarOpen.value = mobile ? false : readStoredPreference();
        synced = true;
    }
};

function readStoredPreference(): boolean {
    try {
        return localStorage.getItem(SIDEBAR_KEY) !== '0';
    } catch {
        return true;
    }
}

const toggleSidebar = (): void => {
    sidebarOpen.value = !sidebarOpen.value;

    if (isMobile.value) {
        return;
    }

    try {
        localStorage.setItem(SIDEBAR_KEY, sidebarOpen.value ? '1' : '0');
    } catch {
        // Mode privat memblokir localStorage; pilihan berlaku untuk sesi ini saja.
    }
};

/**
 * Kunci gulir jendela hanya selama cangkang aplikasi terpasang; aturannya ada
 * di app.css (html.app-shell). Halaman tamu tidak memakai layout ini, jadi
 * jendelanya tetap bisa digulir.
 */
onMounted(() => {
    document.documentElement.classList.add('app-shell');
    syncViewport();
    window.addEventListener('resize', syncViewport, { passive: true });
});

onBeforeUnmount(() => {
    document.documentElement.classList.remove('app-shell');
    window.removeEventListener('resize', syncViewport);
});
</script>

<template>
    <!-- h-screen, BUKAN h-full: Inertia membungkus halaman dalam <div id="app">
         yang tingginya auto, jadi `h-full` di sini akan ikut setinggi isi dan
         <main> tidak pernah menggulir sendiri. -->
    <div class="flex h-screen overflow-hidden bg-surface-sunken">
        <AppSidebar
            :open="sidebarOpen"
            :floating="isMobile"
            @close="sidebarOpen = false"
        />

        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <AppTopbar :sidebar-open="sidebarOpen" @toggle-sidebar="toggleSidebar" />

            <!-- Hanya elemen ini yang menggulir; <body> dikunci di app.css. -->
            <main class="min-h-0 flex-1 overflow-y-auto scroll-slim p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <UiToastHost />
        <UiConfirmDialog />
    </div>
</template>
