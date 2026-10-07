import type { Permission } from '@/lib/permissions';
import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermission() {
    const page = usePage<PageProps>();
    const granted = computed(
        () => new Set<string>(page.props.auth?.permissions ?? []),
    );

    const can = (permission: Permission): boolean =>
        granted.value.has(permission);
    const canAny = (permissions: Permission[]): boolean =>
        permissions.some(can);

    return { can, canAny };
}
