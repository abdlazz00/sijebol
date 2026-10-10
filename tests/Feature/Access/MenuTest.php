<?php

use App\Domains\Access\Services\MenuService;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('menu disaring menurut izin dan grup kosong dibuang', function () {
    $this->seed(AccessSeeder::class);
    $operator = User::factory()->create()->assignRole('operator-sekolah');
    $admin = User::factory()->create()->assignRole('admin-kecamatan');

    $menuOperator = app(MenuService::class)->forUser($operator);
    $menuAdmin = app(MenuService::class)->forUser($admin);

    expect(collect($menuOperator)->pluck('group')->all())->toBe(['Utama', 'Pelayanan'])
        ->and(collect($menuAdmin)->pluck('group')->all())->toBe(['Utama', 'Pelayanan', 'Laporan', 'Sistem & Akses'])
        ->and(collect($menuAdmin)->firstWhere('group', 'Sistem & Akses')['items'])
        ->toContain(['label' => 'Pengguna', 'href' => '/pengguna', 'icon' => 'users']);
});

test('tamu tidak menerima menu dan menu dibagikan sebagai shared prop', function () {
    expect(app(MenuService::class)->forUser(null))->toBe([]);

    $user = makeUserWith(['sekolah.lihat']);
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('menu.1.group', 'Sistem & Akses')
        ->where('menu.1.items.0.href', '/sekolah'));
});

test('menu Pendaftaran dan Antrean verifikasi mengikuti izin', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));
    $admin = app(MenuService::class)->forUser(User::factory()->create()->assignRole('admin-kecamatan'));

    expect(collect($operator)->firstWhere('group', 'Pelayanan')['items'])->toContain(['label' => 'Pendaftaran', 'href' => '/pendaftaran', 'icon' => 'clipboard-list'])
        ->and(collect($operator)->firstWhere('group', 'Pelayanan')['items'])->not->toContain(['label' => 'Antrean verifikasi', 'href' => '/verifikasi', 'icon' => 'badge-check'])
        ->and(collect($admin)->firstWhere('group', 'Pelayanan')['items'])->toContain(['label' => 'Antrean verifikasi', 'href' => '/verifikasi', 'icon' => 'badge-check']);
});

test('menu Jadwal tampil untuk pemegang jadwal.lihat', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));

    expect(collect($operator)->firstWhere('group', 'Pelayanan')['items'])->toContain(['label' => 'Jadwal', 'href' => '/jadwal', 'icon' => 'calendar-days']);
});

test('menu Laporan tampil untuk pemegang laporan.lihat', function () {
    $staf = makeUserWith(['laporan.lihat'], roleName: 'pelapor-menu');

    expect(collect(app(MenuService::class)->forUser($staf))->firstWhere('group', 'Laporan')['items'][0])
        ->toBe(['label' => 'Rekap pendaftaran', 'href' => '/laporan', 'icon' => 'file-spreadsheet']);
});
