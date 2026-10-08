<?php

namespace App\Domains\Access\Services;

use App\Models\User;

class MenuService
{
    /** @var list<array{group: string, label: string, href: string, icon: string, permission: ?string}> */
    private const ITEMS = [
        ['group' => 'Utama', 'label' => 'Beranda', 'href' => '/dashboard', 'icon' => 'home', 'permission' => null],
        ['group' => 'Manajemen Akses', 'label' => 'Pengguna', 'href' => '/pengguna', 'icon' => 'users', 'permission' => 'user.lihat'],
        ['group' => 'Manajemen Akses', 'label' => 'Role & Permission', 'href' => '/role', 'icon' => 'shield-check', 'permission' => 'role.lihat'],
        ['group' => 'Master Data', 'label' => 'Sekolah', 'href' => '/sekolah', 'icon' => 'school', 'permission' => 'sekolah.lihat'],
        ['group' => 'Data Siswa', 'label' => 'Siswa', 'href' => '/siswa', 'icon' => 'graduation-cap', 'permission' => 'siswa.lihat'],
        ['group' => 'Data Siswa', 'label' => 'Pendaftaran', 'href' => '/pendaftaran', 'icon' => 'clipboard-list', 'permission' => 'pendaftaran.lihat'],
        ['group' => 'Verifikasi', 'label' => 'Antrean verifikasi', 'href' => '/verifikasi', 'icon' => 'badge-check', 'permission' => 'verifikasi.lihat'],
        ['group' => 'Sistem', 'label' => 'Log Aktivitas', 'href' => '/log', 'icon' => 'scroll-text', 'permission' => 'log.lihat'],
    ];

    /** @return list<array{group: string, items: list<array{label: string, href: string, icon: string}>}> */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $owned = AccessGuard::permissionsOf($user);
        $groups = [];

        foreach (self::ITEMS as $item) {
            if ($item['permission'] !== null && ! in_array($item['permission'], $owned, true)) {
                continue;
            }
            $groups[$item['group']][] = ['label' => $item['label'], 'href' => $item['href'], 'icon' => $item['icon']];
        }

        return array_map(
            fn (string $group, array $items) => ['group' => $group, 'items' => $items],
            array_keys($groups),
            $groups,
        );
    }
}
