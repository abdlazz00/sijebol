<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\PermissionCatalog;
use App\Domains\Access\Services\RoleService;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->service = app(RoleService::class);
    $this->super = User::factory()->create()->assignRole('super-admin');
    // Penyunting terbatas: tidak memegang siswa.buat dan izin lain di luar daftar ini.
    $this->admin = makeUserWith([
        'role.lihat', 'role.buat', 'role.ubah', 'user.lihat', 'user.buat',
        'verifikasi.lihat', 'verifikasi.putuskan', 'jadwal.lihat', 'siswa.lihat',
    ], roleName: 'penyunting');
});

test('membuat role dengan izin yang valid', function () {
    $role = $this->service->create([
        'name' => 'verifikator', 'description' => 'Memverifikasi', 'is_school_scoped' => false,
        'permissions' => ['verifikasi.lihat', 'verifikasi.putuskan'],
    ], $this->admin);

    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['verifikasi.lihat', 'verifikasi.putuskan']);
});

test('tidak boleh memberi izin yang tidak dimiliki pembuat', function () {
    expect(fn () => $this->service->create([
        'name' => 'x', 'is_school_scoped' => false, 'permissions' => ['siswa.buat'],
    ], $this->admin))->toThrow(AccessRuleException::class);
});

test('update menambah dan mencabut hanya izin milik penyunting, sisanya tidak tersentuh', function () {
    $role = Role::create(['name' => 'campuran']);
    $role->givePermissionTo(['siswa.buat', 'user.lihat', 'user.buat']); // siswa.buat di luar milik admin

    $this->service->update($role, [
        'name' => 'campuran', 'is_school_scoped' => false,
        'permissions' => ['siswa.buat', 'user.lihat', 'jadwal.lihat'], // cabut user.buat, tambah jadwal.lihat
    ], $this->admin);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['jadwal.lihat', 'siswa.buat', 'user.lihat']);
});

test('menambah izin baru di luar milik penyunting ditolak', function () {
    $role = Role::create(['name' => 'r']);

    expect(fn () => $this->service->update($role, [
        'name' => 'r', 'is_school_scoped' => false, 'permissions' => ['siswa.buat'],
    ], $this->admin))->toThrow(AccessRuleException::class);
});

test('izin di luar enum ditolak', function () {
    expect(fn () => $this->service->create([
        'name' => 'x', 'is_school_scoped' => false, 'permissions' => ['bukan.izin'],
    ], $this->super))->toThrow(AccessRuleException::class);
});

test('super-admin tidak dapat diubah atau dihapus', function () {
    $role = Role::findByName('super-admin');

    expect(fn () => $this->service->update($role, ['name' => 'lain', 'is_school_scoped' => false, 'permissions' => []], $this->super))
        ->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->delete($role, $this->super))->toThrow(AccessRuleException::class);
});

test('role dengan izin di luar milik penyunting tidak dapat dihapus olehnya', function () {
    $tinggi = Role::create(['name' => 'tinggi']);
    $tinggi->givePermissionTo(['siswa.buat']); // di luar izin penyunting

    expect(fn () => $this->service->delete($tinggi, $this->admin))->toThrow(AccessRuleException::class);

    $this->service->delete($tinggi, $this->super); // super-admin boleh
    expect(Role::where('name', 'tinggi')->exists())->toBeFalse();
});

test('hapus ditolak bila role masih dipakai', function () {
    $role = Role::create(['name' => 'dipakai']);
    User::factory()->create()->assignRole($role);

    expect(fn () => $this->service->delete($role, $this->super))->toThrow(AccessRuleException::class);

    $role->users()->detach();
    $this->service->delete($role, $this->super);
    expect(Role::where('name', 'dipakai')->exists())->toBeFalse();
});

test('is_school_scoped terkunci saat role dipakai', function () {
    $role = Role::create(['name' => 'dipakai']);
    User::factory()->create()->assignRole($role);

    expect(fn () => $this->service->update($role, ['name' => 'dipakai', 'is_school_scoped' => true, 'permissions' => []], $this->super))
        ->toThrow(AccessRuleException::class);

    $this->service->update($role, ['name' => 'dipakai', 'is_school_scoped' => false, 'description' => 'ok', 'permissions' => []], $this->super);
    expect($role->fresh()->description)->toBe('ok');
});

test('perubahan izin mengosongkan cache izin', function () {
    $role = Role::create(['name' => 'r']);
    $user = User::factory()->create()->assignRole($role);
    expect($user->can('jadwal.lihat'))->toBeFalse();

    $this->service->update($role, ['name' => 'r', 'is_school_scoped' => false, 'permissions' => ['jadwal.lihat']], $this->admin);

    expect(User::find($user->id)->can('jadwal.lihat'))->toBeTrue();
});

test('perubahan izin tercatat di log aktivitas', function () {
    $role = Role::create(['name' => 'r']);

    $this->service->update($role, ['name' => 'r', 'is_school_scoped' => false, 'permissions' => ['jadwal.lihat']], $this->admin);

    $log = Activity::where('description', 'role.izin_diubah')->firstOrFail();
    expect($log->properties['ditambah'])->toBe(['jadwal.lihat'])->and($log->causer_id)->toBe($this->admin->id);
});

test('katalog menandai izin yang tidak dapat diedit oleh penyunting', function () {
    $catalog = collect(PermissionCatalog::forActor($this->admin));
    $siswa = $catalog->firstWhere('module', 'siswa')['actions'];

    expect(collect($siswa)->firstWhere('value', 'siswa.lihat')['editable'])->toBeTrue()
        ->and(collect($siswa)->firstWhere('value', 'siswa.buat')['editable'])->toBeFalse();
});

test('role terikat sekolah tidak boleh memegang log.lihat (log mencakup seluruh kecamatan)', function () {
    expect(fn () => $this->service->create([
        'name' => 'operator-log', 'is_school_scoped' => true, 'permissions' => ['log.lihat'],
    ], $this->super))->toThrow(AccessRuleException::class);

    $role = $this->service->create([
        'name' => 'operator-biasa', 'is_school_scoped' => true, 'permissions' => ['siswa.lihat'],
    ], $this->super);

    expect(fn () => $this->service->update($role, [
        'name' => 'operator-biasa', 'is_school_scoped' => true, 'permissions' => ['siswa.lihat', 'log.lihat'],
    ], $this->super))->toThrow(AccessRuleException::class);
});

test('role terikat sekolah tidak boleh memegang laporan.lihat (rekap mencakup seluruh kecamatan)', function () {
    expect(fn () => $this->service->create([
        'name' => 'operator-laporan', 'is_school_scoped' => true, 'permissions' => ['laporan.lihat'],
    ], $this->super))->toThrow(AccessRuleException::class);

    $role = $this->service->create([
        'name' => 'operator-biasa-2', 'is_school_scoped' => true, 'permissions' => ['siswa.lihat'],
    ], $this->super);

    expect(fn () => $this->service->update($role, [
        'name' => 'operator-biasa-2', 'is_school_scoped' => true, 'permissions' => ['siswa.lihat', 'laporan.lihat'],
    ], $this->super))->toThrow(AccessRuleException::class);
});

test('role global boleh memegang log.lihat', function () {
    $role = $this->service->create([
        'name' => 'auditor', 'is_school_scoped' => false, 'permissions' => ['log.lihat'],
    ], $this->super);

    expect($role->permissions->pluck('name')->all())->toBe(['log.lihat']);
});
