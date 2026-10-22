<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { UiInput, UiSpinner } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { tr, useLocale } from '@/composables/useLocale';

// Halaman tamu: tanpa sidebar/topbar.
defineOptions({ layout: null });

interface UserResult {
    id: number;
    name: string;
    email: string;
    employee_id: string | null;
    designation_name: string | null;
    group_company: string | null;
}

// Server menolak term yang terlalu pendek, jadi ambangnya datang dari server.
const props = withDefaults(defineProps<{ minSearchLength?: number }>(), { minSearchLength: 2 });

const { t } = useLocale();

const query = ref('');
const results = ref<UserResult[]>([]);
const searching = ref(false);
const submittingId = ref<number | null>(null);

const term = computed(() => query.value.trim());
const longEnough = computed(() => term.value.length >= props.minSearchLength);

let debounce: ReturnType<typeof setTimeout> | undefined;
// Tiap pencarian membawa nomor urut; balasan yang bukan yang terbaru dibuang,
// sehingga permintaan lama yang lambat tidak menimpa hasil yang lebih baru.
let sequence = 0;

watch(query, () => {
    clearTimeout(debounce);

    if (!longEnough.value) {
        sequence += 1;
        results.value = [];
        searching.value = false;

        return;
    }

    searching.value = true;
    debounce = setTimeout(runSearch, 300);
});

async function runSearch(): Promise<void> {
    const current = ++sequence;
    const q = term.value;

    searching.value = true;

    try {
        const res = await fetch(`${route('dev.login.search')}?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
        });
        const found = res.ok ? await res.json() : [];

        if (current === sequence) {
            results.value = found;
        }
    } catch {
        if (current === sequence) {
            results.value = [];
        }
    } finally {
        if (current === sequence) {
            searching.value = false;
        }
    }
}

function loginAs(user: UserResult): void {
    submittingId.value = user.id;
    router.post(
        route('dev.login.impersonate'),
        { user_id: user.id },
        { onFinish: () => (submittingId.value = null) },
    );
}
</script>

<template>
    <Head :title="t.devLogin.userHead" />

    <AuthLayout :title="t.devLogin.title" :subtitle="t.devLogin.userSubtitle">
        <UiInput
            v-model="query"
            type="search"
            autofocus
            :placeholder="t.devLogin.searchPlaceholder"
        />

        <!-- Tidak ada pencarian sampai term cukup panjang. -->
        <p
            v-if="!longEnough"
            class="mt-4 rounded-card border border-dashed border-line py-8 text-center text-sm text-ink-subtle"
        >
            {{ tr(t.devLogin.typeAtLeast, { count: props.minSearchLength }) }}
        </p>

        <ul v-else class="mt-4 max-h-80 space-y-2 overflow-y-auto">
            <li v-for="user in results" :key="user.id">
                <button
                    type="button"
                    :disabled="submittingId !== null"
                    class="flex w-full items-center justify-between gap-3 rounded-control border border-line p-3 text-left transition-colors hover:border-brand hover:bg-surface-muted disabled:pointer-events-none disabled:opacity-50"
                    @click="loginAs(user)"
                >
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-ink">{{ user.name }}</span>
                        <span class="block truncate text-xs text-ink-subtle">
                            {{ user.email }}
                        </span>
                        <span class="block truncate text-xs text-ink-subtle">
                            {{ user.employee_id ?? 'N.A' }} ·
                            {{ user.designation_name ?? t.devLogin.noEmployeeRecord }} ·
                            {{ user.group_company ?? 'N.A' }}
                        </span>
                    </span>

                    <UiSpinner v-if="submittingId === user.id" class="h-4 w-4 shrink-0 text-brand" />
                    <span v-else class="shrink-0 text-xs font-semibold text-brand">
                        {{ t.devLogin.signIn }}
                    </span>
                </button>
            </li>

            <li
                v-if="searching"
                class="rounded-card border border-dashed border-line py-8 text-center text-sm text-ink-subtle"
            >
                {{ t.devLogin.searching }}
            </li>

            <li
                v-else-if="results.length === 0"
                class="rounded-card border border-dashed border-line py-8 text-center text-sm text-ink-subtle"
            >
                {{ t.devLogin.noMatch }}
            </li>
        </ul>

        <div class="mt-6 border-t border-line pt-4 text-center">
            <Link
                :href="route('login')"
                class="text-sm text-ink-subtle underline-offset-2 hover:underline"
            >
                {{ t.devLogin.backToSignIn }}
            </Link>
        </div>
    </AuthLayout>
</template>
