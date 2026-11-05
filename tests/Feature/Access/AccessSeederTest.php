<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

test('seeder membuat seluruh permission dan empat role, dan aman diulang', function () {
    $this->seed(AccessSeeder::class);
    $this->seed(AccessSeeder::class);

    expect(PermissionModel::count())->toBe(count(Permission::values()))
        ->and(Role::count())->toBe(4)
        ->and(Role::findByName('super-admin')->permissions)->toHaveCount(count(Permission::values()))
        ->and(Role::findByName('operator-sekolah')->is_school_scoped)->toBeTrue()
        ->and(Role::findByName('admin-kecamatan')->is_school_scoped)->toBeFalse()
        ->and(Role::findByName('admin-kecamatan')->hasPermissionTo('user.buat'))->toBeTrue()
        ->and(Role::findByName('operator-sekolah')->hasPermissionTo('user.buat'))->toBeFalse();
});

test('role siswa terikat sekolah dan hanya memegang izin data diri, dokumen, dan pendaftaran sesi', function () {
    $this->seed(AccessSeeder::class);
    $role = Role::findByName('siswa');

    expect($role->is_school_scoped)->toBeTrue()
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['dokumen.hapus', 'dokumen.lihat', 'dokumen.unggah', 'pendaftaran.buat', 'pendaftaran.lihat', 'profil.lihat', 'profil.ubah']);
});

test('operator sekolah hanya memantau: lihat siswa, lihat jadwal, dan salin link pendaftaran', function () {
    $this->seed(AccessSeeder::class);
    $operator = Role::findByName('operator-sekolah');

    expect($operator->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['jadwal.lihat', 'sekolah.tautan', 'siswa.lihat'])
        ->and($operator->hasPermissionTo('dokumen.lihat'))->toBeFalse()
        ->and($operator->hasPermissionTo('siswa.ubah'))->toBeFalse()
        ->and($operator->hasPermissionTo('pendaftaran.buat'))->toBeFalse();
});

test('admin kecamatan memegang semua permission, termasuk yang baru', function () {
    $this->seed(AccessSeeder::class);

    expect(Role::findByName('admin-kecamatan')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::values())->sort()->values()->all())
        ->and(Permission::values())->toContain('profil.lihat', 'profil.ubah', 'sekolah.tautan', 'sekolah.reset-tautan');
});

test('super-admin lolos gate apa pun, user biasa tidak', function () {
    $this->seed(AccessSeeder::class);
    $super = User::factory()->create()->assignRole('super-admin');
    $plain = User::factory()->create();

    expect(Gate::forUser($super)->allows('apa-saja'))->toBeTrue()
        ->and(Gate::forUser($plain)->allows('user.lihat'))->toBeFalse();
});

test('aturan password: minimal 8, huruf, dan angka', function () {
    $rule = fn (string $value) => validator(['p' => $value], ['p' => Password::defaults()])->passes();

    expect($rule('abcdefgh'))->toBeFalse()
        ->and($rule('12345678'))->toBeFalse()
        ->and($rule('abc1234'))->toBeFalse()
        ->and($rule('abcd1234'))->toBeTrue();
});

test('izin rekaman.pindai hanya dimiliki admin kecamatan dan super admin', function () {
    $this->seed(AccessSeeder::class);

    expect(Permission::values())->toContain('rekaman.pindai')
        ->and(Role::findByName('super-admin')->hasPermissionTo('rekaman.pindai'))->toBeTrue()
        ->and(Role::findByName('admin-kecamatan')->hasPermissionTo('rekaman.pindai'))->toBeTrue()
        ->and(Role::findByName('operator-sekolah')->hasPermissionTo('rekaman.pindai'))->toBeFalse()
        ->and(Role::findByName('siswa')->hasPermissionTo('rekaman.pindai'))->toBeFalse();
});

describe('dijalankan ulang di server yang role-nya sudah diubah', function () {
    beforeEach(fn () => $this->seed(AccessSeeder::class));

    test('izin yang ditambah atau dicabut lewat menu Role tidak dikembalikan', function () {
        $operator = Role::findByName('operator-sekolah');
        $operator->givePermissionTo('rekaman.pindai');
        $operator->revokePermissionTo('sekolah.tautan');
        Role::findByName('admin-kecamatan')->revokePermissionTo('siswa.hapus');

        $this->seed(AccessSeeder::class);

        expect(Role::findByName('operator-sekolah')->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['jadwal.lihat', 'rekaman.pindai', 'siswa.lihat'])
            ->and(Role::findByName('admin-kecamatan')->hasPermissionTo('siswa.hapus'))->toBeFalse();
    });

    test('deskripsi dan pengaturan terikat sekolah milik role yang sudah ada tidak ditimpa', function () {
        Role::findByName('operator-sekolah')->update(['description' => 'Diubah client']);
        Role::findByName('admin-kecamatan')->update(['description' => 'Admin lokal']);

        $this->seed(AccessSeeder::class);

        expect(Role::findByName('operator-sekolah')->description)->toBe('Diubah client')
            ->and(Role::findByName('admin-kecamatan')->description)->toBe('Admin lokal');
    });

    test('izin baru dari rilis diberikan hanya ke role bawaan yang memang mendapatkannya', function () {
        PermissionModel::findByName('laporan.rekaman')->delete();
        PermissionModel::findByName('laporan.unduh-nik')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(AccessSeeder::class);

        foreach (['super-admin', 'admin-kecamatan'] as $name) {
            expect(Role::findByName($name)->hasPermissionTo('laporan.rekaman'))->toBeTrue()
                ->and(Role::findByName($name)->hasPermissionTo('laporan.unduh-nik'))->toBeTrue();
        }
        expect(Role::findByName('operator-sekolah')->hasPermissionTo('laporan.rekaman'))->toBeFalse()
            ->and(Role::findByName('siswa')->hasPermissionTo('laporan.rekaman'))->toBeFalse();
    });

    test('izin yang sudah ada tetapi sengaja dicabut dari role tidak ikut diberikan lagi saat run berikutnya', function () {
        Role::findByName('admin-kecamatan')->revokePermissionTo('laporan.unduh-nik');

        $this->seed(AccessSeeder::class);

        expect(Role::findByName('admin-kecamatan')->hasPermissionTo('laporan.unduh-nik'))->toBeFalse();
    });

    test('super-admin selalu kembali memegang semua izin', function () {
        Role::findByName('super-admin')->revokePermissionTo('log.lihat');

        $this->seed(AccessSeeder::class);

        expect(Role::findByName('super-admin')->permissions)->toHaveCount(count(Permission::values()));
    });

    test('izin yang sudah dihapus dari kode dibersihkan dari semua role', function () {
        $lama = PermissionModel::findOrCreate('pendaftaran.ajukan', 'web');
        Role::findByName('siswa')->givePermissionTo($lama);

        $this->seed(AccessSeeder::class);

        expect(PermissionModel::where('name', 'pendaftaran.ajukan')->exists())->toBeFalse()
            ->and(Role::findByName('siswa')->permissions->pluck('name')->contains('pendaftaran.ajukan'))->toBeFalse()
            ->and(PermissionModel::count())->toBe(count(Permission::values()));
    });

    test('role bawaan yang sudah dihapus dibuat kembali dengan izin bawaannya', function () {
        Role::findByName('operator-sekolah')->delete();

        $this->seed(AccessSeeder::class);

        expect(Role::findByName('operator-sekolah')->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['jadwal.lihat', 'sekolah.tautan', 'siswa.lihat']);
    });
});
