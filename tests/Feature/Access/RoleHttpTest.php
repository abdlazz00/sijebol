<?php

use App\Domains\Access\Models\Role;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->actor = makeUserWith(['role.lihat', 'role.buat', 'role.ubah', 'role.hapus', 'jadwal.lihat', 'jadwal.buat']);
});

test('daftar role memuat jumlah dan penanda protected', function () {
    $this->actingAs($this->actor)->get('/role')->assertInertia(fn (Assert $p) => $p
        ->component('Role/Index')
        ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'super-admin')['protected'] === true));
});

test('buat role lewat form', function () {
    $this->actingAs($this->actor)->post('/role', [
        'name' => 'penjadwal', 'description' => 'Atur jadwal', 'is_school_scoped' => false,
        'permissions' => ['jadwal.lihat', 'jadwal.buat'],
    ])->assertRedirect('/role');

    expect(Role::findByName('penjadwal')->permissions)->toHaveCount(2);
});

test('izin yang tidak dimiliki menghasilkan flash error, bukan crash', function () {
    $this->actingAs($this->actor)->from('/role/buat')->post('/role', [
        'name' => 'bocor', 'is_school_scoped' => false, 'permissions' => ['siswa.buat'],
    ])->assertRedirect('/role/buat')->assertSessionHas('error');

    expect(Role::where('name', 'bocor')->exists())->toBeFalse();
});

test('validasi nama unik dan izin harus anggota enum', function () {
    $this->actingAs($this->actor)->post('/role', ['name' => 'operator-sekolah', 'is_school_scoped' => false, 'permissions' => ['x.y']])
        ->assertSessionHasErrors(['name', 'permissions.0']);
});

test('form ubah memuat katalog dan izin saat ini', function () {
    $role = Role::create(['name' => 'r']);
    $role->givePermissionTo('jadwal.lihat');

    $this->actingAs($this->actor)->get("/role/{$role->id}/ubah")->assertInertia(fn (Assert $p) => $p
        ->component('Role/Form')
        ->where('role.permissions', ['jadwal.lihat'])
        ->has('catalog'));
});

test('ubah dan hapus role', function () {
    $role = Role::create(['name' => 'r']);

    $this->actingAs($this->actor)->put("/role/{$role->id}", ['name' => 'r2', 'is_school_scoped' => false, 'permissions' => ['jadwal.lihat']])
        ->assertRedirect('/role');
    expect($role->fresh()->name)->toBe('r2');

    $this->actingAs($this->actor)->delete("/role/{$role->id}")->assertRedirect('/role');
    expect(Role::where('name', 'r2')->exists())->toBeFalse();
});

test('menghapus role super-admin menghasilkan flash error', function () {
    $super = Role::findByName('super-admin');

    $this->actingAs($this->actor)->delete("/role/{$super->id}")->assertSessionHas('error');
    expect(Role::where('name', 'super-admin')->exists())->toBeTrue();
});
