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

    expect(collect($menuOperator)->pluck('group')->all())->toBe(['Utama'])
        ->and(collect($menuAdmin)->pluck('group')->all())->toBe(['Utama', 'Manajemen Akses', 'Master Data'])
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
