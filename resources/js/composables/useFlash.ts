import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useFlash() {
    const page = usePage<PageProps>();
    const flash = computed(() => page.props.flash ?? {});
    return { flash };
}
