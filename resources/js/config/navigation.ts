import { PERMISSIONS, type Permission } from '@/lib/permissions';
import { Home, School, ShieldCheck, Users } from '@lucide/vue';
import type { Component } from 'vue';

export interface NavItem {
    group: string;
    label: string;
    href: string;
    icon: Component;
    permission?: Permission;
}

export const navigation: NavItem[] = [
    { group: 'Utama', label: 'Beranda', href: '/dashboard', icon: Home },
    {
        group: 'Manajemen Akses',
        label: 'Pengguna',
        href: '/pengguna',
        icon: Users,
        permission: PERMISSIONS.UserLihat,
    },
    {
        group: 'Manajemen Akses',
        label: 'Role & Permission',
        href: '/role',
        icon: ShieldCheck,
        permission: PERMISSIONS.RoleLihat,
    },
    {
        group: 'Master Data',
        label: 'Sekolah',
        href: '/sekolah',
        icon: School,
        permission: PERMISSIONS.SekolahLihat,
    },
];

export function visibleNavigation(
    items: NavItem[],
    can: (permission: Permission) => boolean,
): { group: string; items: NavItem[] }[] {
    const groups = new Map<string, NavItem[]>();
    for (const item of items) {
        if (item.permission && !can(item.permission)) continue;
        groups.set(item.group, [...(groups.get(item.group) ?? []), item]);
    }
    return [...groups].map(([group, groupItems]) => ({
        group,
        items: groupItems,
    }));
}
