<?php

namespace App\Domains\Access\Services;

use App\Models\User;

class MenuService
{
    /** @var list<array{group: string, label: string, href: string, icon: string, permission: ?string}> */
    private const ITEMS = [
        ['group' => 'Utama', 'label' => 'Beranda', 'href' => '/dashboard', 'icon' => 'home', 'permission' => null],
        ['group' => 'Pelayanan', 'label' => 'Siswa', 'href' => '/students', 'icon' => 'graduation-cap', 'permission' => 'siswa.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Data saya', 'href' => '/me', 'icon' => 'graduation-cap', 'permission' => 'profil.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Link pendaftaran', 'href' => '/registration-links', 'icon' => 'link', 'permission' => 'sekolah.tautan'],
        ['group' => 'Pelayanan', 'label' => 'Sesi rekaman', 'href' => '/me/sessions', 'icon' => 'calendar-days', 'permission' => 'pendaftaran.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Antrean verifikasi', 'href' => '/verifications', 'icon' => 'badge-check', 'permission' => 'verifikasi.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Sesi rekaman', 'href' => '/schedules', 'icon' => 'calendar-days', 'permission' => 'jadwal.lihat'],
        ['group' => 'Laporan', 'label' => 'Rekap pendaftaran', 'href' => '/reports', 'icon' => 'file-spreadsheet', 'permission' => 'laporan.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Sekolah', 'href' => '/schools', 'icon' => 'school', 'permission' => 'sekolah.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Pengguna', 'href' => '/users', 'icon' => 'users', 'permission' => 'user.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Role & Permission', 'href' => '/roles', 'icon' => 'shield-check', 'permission' => 'role.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Log Aktivitas', 'href' => '/audit-logs', 'icon' => 'scroll-text', 'permission' => 'log.lihat'],
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
            // Halaman /me/* hanya untuk akun siswa; admin memegang izin profil.* dan pendaftaran.* (anti eskalasi) tetapi rutenya 404.
            if (str_starts_with($item['href'], '/me') && ! $user->isStudent()) {
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
