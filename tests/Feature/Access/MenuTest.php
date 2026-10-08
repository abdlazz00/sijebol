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

    expect(collect($menuOperator)->pluck('group')->all())->toBe(['Utama', 'Data Siswa', 'Jadwal'])
        ->and(collect($menuAdmin)->pluck('group')->all())->toBe(['Utama', 'Manajemen Akses', 'Master Data', 'Data Siswa', 'Verifikasi', 'Jadwal', 'Sistem'])
        ->and(collect($menuAdmin)->firstWhere('group', 'Manajemen Akses')['items'][0])
        ->toBe(['label' => 'Pengguna', 'href' => '/pengguna', 'icon' => 'users']);
});

test('tamu tidak menerima menu dan menu dibagikan sebagai shared prop', function () {
    expect(app(MenuService::class)->forUser(null))->toBe([]);

    $user = makeUserWith(['sekolah.lihat']);
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('menu.1.group', 'Master Data')
        ->where('menu.1.items.0.href', '/sekolah'));
});

test('menu Pendaftaran dan Antrean verifikasi mengikuti izin', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));
    $admin = app(MenuService::class)->forUser(User::factory()->create()->assignRole('admin-kecamatan'));

    expect(collect($operator)->firstWhere('group', 'Data Siswa')['items'])->toContain(['label' => 'Pendaftaran', 'href' => '/pendaftaran', 'icon' => 'clipboard-list'])
        ->and(collect($operator)->pluck('group')->all())->not->toContain('Verifikasi')
        ->and(collect($admin)->firstWhere('group', 'Verifikasi')['items'][0])->toBe(['label' => 'Antrean verifikasi', 'href' => '/verifikasi', 'icon' => 'badge-check']);
});

test('menu Jadwal tampil untuk pemegang jadwal.lihat', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));

    expect(collect($operator)->firstWhere('group', 'Jadwal')['items'][0])->toBe(['label' => 'Jadwal', 'href' => '/jadwal', 'icon' => 'calendar-days']);
});
