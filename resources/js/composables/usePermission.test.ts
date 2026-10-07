import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ usePage: vi.fn() }));

import { PERMISSIONS } from '@/lib/permissions';
import { usePage } from '@inertiajs/vue3';
import { usePermission } from './usePermission';

function setProps(props: Record<string, unknown>) {
    (usePage as Mock).mockReturnValue({ props });
}

describe('usePermission', () => {
    beforeEach(() => vi.clearAllMocks());

    it('mengizinkan permission yang dimiliki', () => {
        setProps({ auth: { user: { id: 1 }, permissions: ['user.lihat'] } });
        const { can, canAny } = usePermission();
        expect(can(PERMISSIONS.UserLihat)).toBe(true);
        expect(can(PERMISSIONS.UserBuat)).toBe(false);
        expect(canAny([PERMISSIONS.UserBuat, PERMISSIONS.UserLihat])).toBe(
            true,
        );
    });

    it('menolak semua saat auth tidak ada atau tamu', () => {
        setProps({});
        expect(usePermission().can(PERMISSIONS.UserLihat)).toBe(false);
        setProps({ auth: { user: null, permissions: [] } });
        expect(usePermission().canAny([PERMISSIONS.UserLihat])).toBe(false);
    });
});
