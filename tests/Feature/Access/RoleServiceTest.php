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
    // Penyunting terbatas: tidak memegang siswa.hapus dan izin lain di luar daftar ini.
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
        'name' => 'x', 'is_school_scoped' => false, 'permissions' => ['siswa.hapus'],
    ], $this->admin))->toThrow(AccessRuleException::class);
});

test('update menambah dan mencabut hanya izin milik penyunting, sisanya tidak tersentuh', function () {
    $role = Role::create(['name' => 'campuran']);
    $role->givePermissionTo(['siswa.hapus', 'user.lihat', 'user.buat']); // siswa.hapus di luar milik admin

    $this->service->update($role, [
        'name' => 'campuran', 'is_school_scoped' => false,
        'permissions' => ['siswa.hapus', 'user.lihat', 'jadwal.lihat'], // cabut user.buat, tambah jadwal.lihat
    ], $this->admin);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['jadwal.lihat', 'siswa.hapus', 'user.lihat']);
});

test('menambah izin baru di luar milik penyunting ditolak', function () {
    $role = Role::create(['name' => 'r']);

    expect(fn () => $this->service->update($role, [
        'name' => 'r', 'is_school_scoped' => false, 'permissions' => ['siswa.hapus'],
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
    $tinggi->givePermissionTo(['siswa.hapus']); // di luar izin penyunting

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
        ->and(collect($siswa)->firstWhere('value', 'siswa.hapus')['editable'])->toBeFalse();
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

test('role terikat sekolah tidak boleh memegang izin mengelola sekolah (sekolah tidak dibatasi per sekolah)', function (string $izin) {
    expect(fn () => $this->service->create([
        'name' => 'operator-kelola', 'is_school_scoped' => true, 'permissions' => [$izin],
    ], $this->super))->toThrow(AccessRuleException::class);

    $role = $this->service->create([
        'name' => 'operator-biasa-3', 'is_school_scoped' => true, 'permissions' => ['siswa.lihat'],
    ], $this->super);

    expect(fn () => $this->service->update($role, [
        'name' => 'operator-biasa-3', 'is_school_scoped' => true, 'permissions' => ['siswa.lihat', $izin],
    ], $this->super))->toThrow(AccessRuleException::class);

    $global = $this->service->create([
        'name' => 'staf-sekolah', 'is_school_scoped' => false, 'permissions' => [$izin],
    ], $this->super);
    expect($global->permissions->pluck('name')->all())->toBe([$izin]);
})->with(['sekolah.buat', 'sekolah.ubah', 'sekolah.hapus']);

test('role global boleh memegang log.lihat', function () {
    $role = $this->service->create([
        'name' => 'auditor', 'is_school_scoped' => false, 'permissions' => ['log.lihat'],
    ], $this->super);

    expect($role->permissions->pluck('name')->all())->toBe(['log.lihat']);
});

test('role sistem siswa tidak dapat dihapus atau diganti namanya, tetapi izinnya dapat diubah', function () {
    $siswa = Role::findByName('siswa');

    expect(fn () => $this->service->delete($siswa, $this->super))->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->update($siswa, [
            'name' => 'murid', 'is_school_scoped' => true, 'permissions' => ['profil.lihat'],
        ], $this->super))->toThrow(AccessRuleException::class);

    $this->service->update($siswa, [
        'name' => 'siswa', 'is_school_scoped' => true, 'permissions' => ['profil.lihat', 'profil.ubah'],
    ], $this->super);

    expect(Role::findByName('siswa')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['profil.lihat', 'profil.ubah']);
});

describe('role dengan izin di luar milik penyunting (F-05)', function () {
    beforeEach(function () {
        $this->tinggi = Role::create(['name' => 'tinggi', 'description' => 'Asli']);
        $this->tinggi->givePermissionTo(['siswa.hapus', 'user.lihat']); // siswa.hapus di luar milik penyunting
    });

    test('nama, deskripsi, dan jenis terikat sekolah tidak dapat diubah oleh penyunting', function (array $perubahan) {
        expect(fn () => $this->service->update($this->tinggi, [
            'name' => 'tinggi', 'description' => 'Asli', 'is_school_scoped' => false,
            'permissions' => ['siswa.hapus', 'user.lihat'],
            ...$perubahan,
        ], $this->admin))->toThrow(AccessRuleException::class, 'tidak dapat mengubah nama');

        expect($this->tinggi->fresh()->only(['name', 'description', 'is_school_scoped']))
            ->toBe(['name' => 'tinggi', 'description' => 'Asli', 'is_school_scoped' => false]);
    })->with([
        'nama' => [['name' => 'diganti']],
        'deskripsi' => [['description' => 'Menyesatkan']],
        'jenis terikat sekolah' => [['is_school_scoped' => true]],
    ]);

    test('izin milik penyunting tetap dapat diubah selama identitas role sama', function () {
        $this->service->update($this->tinggi, [
            'name' => 'tinggi', 'description' => 'Asli', 'is_school_scoped' => false,
            'permissions' => ['siswa.hapus', 'jadwal.lihat'], // cabut user.lihat, tambah jadwal.lihat
        ], $this->admin);

        expect($this->tinggi->fresh()->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['jadwal.lihat', 'siswa.hapus']);
    });

    test('deskripsi kosong dan null dianggap sama', function () {
        $role = Role::create(['name' => 'tanpa-deskripsi']);
        $role->givePermissionTo(['siswa.hapus']);

        $this->service->update($role, [
            'name' => 'tanpa-deskripsi', 'description' => '', 'is_school_scoped' => false, 'permissions' => ['siswa.hapus'],
        ], $this->admin);

        expect($role->fresh()->name)->toBe('tanpa-deskripsi');
    });

    test('super-admin tetap dapat mengganti nama dan deskripsi', function () {
        $this->service->update($this->tinggi, [
            'name' => 'diganti', 'description' => 'Baru', 'is_school_scoped' => false,
            'permissions' => ['siswa.hapus', 'user.lihat'],
        ], $this->super);

        expect($this->tinggi->fresh()->only(['name', 'description']))->toBe(['name' => 'diganti', 'description' => 'Baru']);
    });

    test('role yang seluruh izinnya dimiliki penyunting tetap dapat diganti namanya olehnya', function () {
        $biasa = Role::create(['name' => 'biasa']);
        $biasa->givePermissionTo(['user.lihat']);

        $this->service->update($biasa, [
            'name' => 'biasa-baru', 'description' => 'x', 'is_school_scoped' => false, 'permissions' => ['user.lihat'],
        ], $this->admin);

        expect($biasa->fresh()->name)->toBe('biasa-baru');
    });
});
