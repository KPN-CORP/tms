import { ref, watch, type Ref } from 'vue';
import type { SelectOption } from '@/types/models';

/**
 * Isi dropdown dari endpoint JSON OrgController, mengikuti pilihan induknya.
 *
 * Endpoint org mengembalikan dua bentuk: daftar string (`unitNames`) atau
 * `{value, text}` (`businessUnitsLocal`). Keduanya dinormalkan di sini supaya
 * komponen cukup tahu satu bentuk saja.
 *
 * Hasil per induk disimpan sehingga bolak-balik antar Business Unit tidak
 * memanggil server lagi, dan permintaan lama dibatalkan agar jawaban yang
 * datang terlambat tidak menimpa yang baru.
 */
export function useRemoteOptions(url: string, parent: Ref<string | number | null | undefined>) {
    const options = ref<SelectOption[]>([]);
    const loading = ref(false);

    const cache = new Map<string, SelectOption[]>();
    let controller: AbortController | null = null;

    const normalise = (raw: unknown): SelectOption[] => {
        if (!Array.isArray(raw)) {
            return [];
        }

        return raw
            .map((entry): SelectOption | null => {
                if (typeof entry === 'string' || typeof entry === 'number') {
                    return { value: entry, label: String(entry) };
                }

                if (entry && typeof entry === 'object') {
                    const record = entry as Record<string, unknown>;
                    const value = record.value ?? record.id;
                    const label = record.text ?? record.name ?? record.label ?? value;

                    if (value !== undefined && value !== null) {
                        return { value: value as string | number, label: String(label) };
                    }
                }

                return null;
            })
            .filter((option): option is SelectOption => option !== null);
    };

    const load = async (): Promise<void> => {
        const key = String(parent.value ?? '');
        const cached = cache.get(key);

        if (cached) {
            options.value = cached;
            return;
        }

        controller?.abort();
        controller = new AbortController();
        loading.value = true;

        try {
            const query = key ? `?bu=${encodeURIComponent(key)}` : '';
            const response = await fetch(`${url}${query}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(`Request failed with ${response.status}`);
            }

            const result = normalise(await response.json());

            cache.set(key, result);
            options.value = result;
        } catch (error) {
            // Abort adalah pembatalan yang disengaja, bukan kegagalan.
            if ((error as Error).name !== 'AbortError') {
                options.value = [];
            }
        } finally {
            loading.value = false;
        }
    };

    watch(parent, () => void load(), { immediate: true });

    return { options, loading, reload: load };
}
