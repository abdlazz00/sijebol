<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\AccessGuard;
use Database\Seeders\AccessSeeder;

beforeEach(fn () => $this->seed(AccessSeeder::class));

test('super-admin memegang semua izin dan boleh memberi role apa pun', function () {
    $super = makeUserWith([], roleName: 'tmp')->syncRoles('super-admin');

    expect(AccessGuard::permissionsOf($super))->toHaveCount(count(Permission::values()))
        ->and(AccessGuard::canGrantRole($super, Role::findByName('super-admin')))->toBeTrue();
});

test('role hanya boleh diberi bila seluruh izinnya dimiliki pemberi', function () {
    $admin = makeUserWith([], roleName: 'tmp')->syncRoles('admin-kecamatan');
    $operator = makeUserWith([], roleName: 'tmp2')->syncRoles('operator-sekolah');
    $terbatas = makeUserWith(['user.lihat'], roleName: 'terbatas');

    expect(AccessGuard::canGrantRole($admin, Role::findByName('admin-kecamatan')))->toBeTrue()
        ->and(AccessGuard::canGrantRole($admin, Role::findByName('operator-sekolah')))->toBeTrue() // admin memegang semua izin operator
        ->and(AccessGuard::canGrantRole($admin, Role::findByName('super-admin')))->toBeFalse()
        ->and(AccessGuard::canGrantRole($operator, Role::findByName('admin-kecamatan')))->toBeFalse()
        ->and(AccessGuard::canGrantRole($terbatas, Role::findByName('operator-sekolah')))->toBeFalse();
});

test('akun berhak lebih tinggi tidak dapat dikelola non-super-admin', function () {
    $super = makeUserWith([], roleName: 'tmp')->syncRoles('super-admin');
    $admin = makeUserWith([], roleName: 'tmp2')->syncRoles('admin-kecamatan');

    expect(AccessGuard::canManageUser($admin, $super))->toBeFalse()
        ->and(AccessGuard::canManageUser($super, $admin))->toBeTrue()
        ->and(AccessGuard::canManageUser($admin, $admin))->toBeTrue();
});
