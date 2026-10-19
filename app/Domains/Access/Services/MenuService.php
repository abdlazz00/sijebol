<?php

namespace App\Domains\Access\Services;

use App\Models\User;

class MenuService
{
    /** @var list<array{group: string, label: string, href: string, icon: string, permission: ?string}> */
    private const ITEMS = [
        ['group' => 'Utama', 'label' => 'Beranda', 'href' => '/dashboard', 'icon' => 'home', 'permission' => null],
        ['group' => 'Pelayanan', 'label' => 'Siswa', 'href' => '/siswa', 'icon' => 'graduation-cap', 'permission' => 'siswa.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Data saya', 'href' => '/saya', 'icon' => 'graduation-cap', 'permission' => 'profil.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Link pendaftaran', 'href' => '/tautan-pendaftaran', 'icon' => 'link', 'permission' => 'sekolah.tautan'],
        ['group' => 'Pelayanan', 'label' => 'Sesi rekaman', 'href' => '/saya/sesi', 'icon' => 'calendar-days', 'permission' => 'pendaftaran.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Antrean verifikasi', 'href' => '/verifikasi', 'icon' => 'badge-check', 'permission' => 'verifikasi.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Sesi rekaman', 'href' => '/jadwal', 'icon' => 'calendar-days', 'permission' => 'jadwal.lihat'],
        ['group' => 'Laporan', 'label' => 'Rekap pendaftaran', 'href' => '/laporan', 'icon' => 'file-spreadsheet', 'permission' => 'laporan.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Sekolah', 'href' => '/sekolah', 'icon' => 'school', 'permission' => 'sekolah.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Pengguna', 'href' => '/pengguna', 'icon' => 'users', 'permission' => 'user.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Role & Permission', 'href' => '/role', 'icon' => 'shield-check', 'permission' => 'role.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Log Aktivitas', 'href' => '/log', 'icon' => 'scroll-text', 'permission' => 'log.lihat'],
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
            // Halaman /saya/* hanya untuk akun siswa; admin memegang izin profil.* dan pendaftaran.* (anti eskalasi) tetapi rutenya 404.
            if (str_starts_with($item['href'], '/saya') && ! $user->isStudent()) {
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
