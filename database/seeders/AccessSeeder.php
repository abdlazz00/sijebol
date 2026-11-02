<?php

namespace Database\Seeders;

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aman dijalankan ulang di server yang role-nya sudah diubah lewat menu Role & Permission.
 *
 * - Role dibuat bila belum ada; role yang sudah ada tidak ditimpa (izin, deskripsi, pengaturan terikat sekolah).
 * - Izin yang BARU dibuat pada run ini diberikan ke role bawaan yang memang mendapatkannya; izin yang sudah ada
 *   tidak diberikan lagi, sehingga izin yang sengaja dicabut tidak kembali.
 * - Izin yang sudah tidak ada di kode dihapus.
 * - super-admin dilindungi dan selalu disinkron penuh.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $created = [];
        foreach (Permission::values() as $name) {
            if (PermissionModel::findOrCreate($name, 'web')->wasRecentlyCreated) {
                $created[] = $name;
            }
        }
        PermissionModel::query()->where('guard_name', 'web')->whereNotIn('name', Permission::values())->get()->each->delete();

        $this->role(Role::SUPER_ADMIN, 'Akses penuh sistem', false, Permission::values(), $created, alwaysSync: true);

        // Anti eskalasi mensyaratkan pemberi role memegang seluruh izin role itu; agar admin kecamatan
        // dapat membuat operator sekolah, ia memegang semua izin (tetapi tidak dilindungi seperti super-admin).
        $this->role('admin-kecamatan', 'Admin kecamatan', false, Permission::values(), $created);

        $this->role('operator-sekolah', 'Operator sekolah (hanya memantau)', true, [
            'siswa.lihat', 'jadwal.lihat', 'sekolah.tautan',
        ], $created);

        $this->role(Role::SISWA, 'Siswa pendaftar', true, [
            'profil.lihat', 'profil.ubah', 'dokumen.lihat', 'dokumen.unggah', 'dokumen.hapus', 'pendaftaran.lihat', 'pendaftaran.buat',
        ], $created);

        $registrar->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $defaults  Izin bawaan role.
     * @param  list<string>  $created  Izin yang baru dibuat pada run ini.
     */
    private function role(string $name, string $description, bool $scoped, array $defaults, array $created, bool $alwaysSync = false): void
    {
        $role = Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $description, 'is_school_scoped' => $scoped],
        );

        if ($alwaysSync || $role->wasRecentlyCreated) {
            $role->syncPermissions($defaults);

            return;
        }

        $role->givePermissionTo(array_values(array_intersect($defaults, $created)));
    }
}
