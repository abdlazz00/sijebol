import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

type FilterValue = string | number | null;

/**
 * Filter daftar server-side. Dikirim sebagai `filter[...]` (konvensi spatie/query-builder);
 * nilai kosong tidak ikut dikirim dan halaman selalu kembali ke 1.
 */
export function useDataTable(
    url: string,
    initial: Record<string, FilterValue> = {},
) {
    const filters = reactive<Record<string, FilterValue>>({ ...initial });
    const loading = ref(false);

    function apply() {
        const filter = Object.fromEntries(
            Object.entries(filters).filter(
                ([, v]) => v !== '' && v !== null && v !== undefined,
            ),
        );
        router.get(url, Object.keys(filter).length ? { filter } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        });
    }

    /** Mengosongkan semua filter, bukan mengembalikan ke nilai awal dari server. */
    function reset() {
        for (const key of Object.keys(filters)) filters[key] = '';
        apply();
    }

    return { filters, loading, apply, reset };
}
