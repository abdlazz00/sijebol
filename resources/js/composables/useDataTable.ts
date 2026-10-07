import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

type FilterValue = string | number | null;

export function useDataTable(
    url: string,
    initial: Record<string, FilterValue> = {},
) {
    const filters = reactive<Record<string, FilterValue>>({ ...initial });

    function apply() {
        const query = Object.fromEntries(
            Object.entries(filters).filter(
                ([, v]) => v !== '' && v !== null && v !== undefined,
            ),
        );
        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function reset() {
        for (const key of Object.keys(filters))
            filters[key] = initial[key] ?? '';
        apply();
    }

    return { filters, apply, reset };
}
