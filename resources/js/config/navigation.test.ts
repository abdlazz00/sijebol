import { PERMISSIONS } from '@/lib/permissions';
import { Home, Users } from '@lucide/vue';
import { describe, expect, it } from 'vitest';
import { visibleNavigation, type NavItem } from './navigation';

const items: NavItem[] = [
    { group: 'Utama', label: 'Beranda', href: '/dashboard', icon: Home },
    {
        group: 'Manajemen Akses',
        label: 'Pengguna',
        href: '/pengguna',
        icon: Users,
        permission: PERMISSIONS.UserLihat,
    },
];

describe('visibleNavigation', () => {
    it('menyembunyikan item tanpa izin dan grup yang kosong', () => {
        const result = visibleNavigation(items, () => false);
        expect(result).toHaveLength(1);
        expect(result[0].group).toBe('Utama');
    });

    it('menampilkan item berizin', () => {
        const result = visibleNavigation(
            items,
            (p) => p === PERMISSIONS.UserLihat,
        );
        expect(result.map((g) => g.group)).toEqual([
            'Utama',
            'Manajemen Akses',
        ]);
    });
});
