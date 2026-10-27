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
        ->toContain(['label' => 'Pengguna', 'href' => '/users', 'icon' => 'users']);
});

test('tamu tidak menerima menu dan menu dibagikan sebagai shared prop', function () {
    expect(app(MenuService::class)->forUser(null))->toBe([]);

    $user = makeUserWith(['sekolah.lihat']);
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('menu.1.group', 'Sistem & Akses')
        ->where('menu.1.items.0.href', '/schools'));
});

test('menu Antrean verifikasi mengikuti izin dan menu Pendaftaran batch sudah hilang', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));
    $admin = app(MenuService::class)->forUser(User::factory()->create()->assignRole('admin-kecamatan'));

    expect(collect($admin)->flatMap(fn ($g) => $g['items'])->pluck('href')->all())->not->toContain('/pendaftaran')
        ->and(collect($operator)->flatMap(fn ($g) => $g['items'])->pluck('href')->all())->not->toContain('/pendaftaran')
        ->and(collect($operator)->firstWhere('group', 'Pelayanan')['items'])->not->toContain(['label' => 'Antrean verifikasi', 'href' => '/verifications', 'icon' => 'badge-check'])
        ->and(collect($admin)->firstWhere('group', 'Pelayanan')['items'])->toContain(['label' => 'Antrean verifikasi', 'href' => '/verifications', 'icon' => 'badge-check']);
});

test('menu Sesi rekaman (jadwal) tampil untuk pemegang jadwal.lihat', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));

    expect(collect($operator)->firstWhere('group', 'Pelayanan')['items'])->toContain(['label' => 'Sesi rekaman', 'href' => '/schedules', 'icon' => 'calendar-days']);
});

test('menu Laporan tampil untuk pemegang laporan.lihat', function () {
    $staf = makeUserWith(['laporan.lihat'], roleName: 'pelapor-menu');

    expect(collect(app(MenuService::class)->forUser($staf))->firstWhere('group', 'Laporan')['items'][0])
        ->toBe(['label' => 'Rekap pendaftaran', 'href' => '/reports', 'icon' => 'file-spreadsheet']);
});

test('menu Link pendaftaran tampil untuk operator dan tidak untuk siswa', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));
    $siswa = app(MenuService::class)->forUser(User::factory()->create()->assignRole('siswa'));

    expect(collect($operator)->firstWhere('group', 'Pelayanan')['items'])
        ->toContain(['label' => 'Link pendaftaran', 'href' => '/registration-links', 'icon' => 'link'])
        ->and(collect($siswa)->flatMap(fn ($g) => $g['items'])->pluck('href')->all())
        ->not->toContain('/registration-links');
});

test('menu Data saya tampil untuk siswa dan tidak untuk operator', function () {
    $this->seed(AccessSeeder::class);
    $siswa = app(MenuService::class)->forUser(User::factory()->create()->assignRole('siswa'));
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));

    expect(collect($siswa)->firstWhere('group', 'Pelayanan')['items'])
        ->toContain(['label' => 'Data saya', 'href' => '/me', 'icon' => 'graduation-cap'])
        ->and(collect($operator)->flatMap(fn ($g) => $g['items'])->pluck('href')->all())
        ->not->toContain('/me');
});

test('menu Data saya tidak tampil untuk admin walau memegang izin profil', function () {
    $this->seed(AccessSeeder::class);
    $admin = app(MenuService::class)->forUser(User::factory()->create()->assignRole('admin-kecamatan'));
    $super = app(MenuService::class)->forUser(User::factory()->create()->assignRole('super-admin'));

    foreach ([$admin, $super] as $menu) {
        expect(collect($menu)->flatMap(fn ($g) => $g['items'])->pluck('href')->all())->not->toContain('/me');
    }
});

test('menu Sesi rekaman siswa tampil untuk siswa, tidak untuk operator dan admin', function () {
    $this->seed(AccessSeeder::class);
    $siswa = app(MenuService::class)->forUser(User::factory()->create()->assignRole('siswa'));
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));
    $admin = app(MenuService::class)->forUser(User::factory()->create()->assignRole('admin-kecamatan'));

    expect(collect($siswa)->firstWhere('group', 'Pelayanan')['items'])
        ->toContain(['label' => 'Sesi rekaman', 'href' => '/me/sessions', 'icon' => 'calendar-days']);
    foreach ([$operator, $admin] as $menu) {
        expect(collect($menu)->flatMap(fn ($g) => $g['items'])->pluck('href')->all())->not->toContain('/me/sessions');
    }
});
