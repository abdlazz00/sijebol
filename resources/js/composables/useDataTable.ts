import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

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
        });
    }

    /** Mengosongkan semua filter, bukan mengembalikan ke nilai awal dari server. */
    function reset() {
        for (const key of Object.keys(filters)) filters[key] = '';
        apply();
    }

    return { filters, apply, reset };
}
