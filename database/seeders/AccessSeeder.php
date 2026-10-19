<?php

namespace Database\Seeders;

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::values() as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        $this->role(Role::SUPER_ADMIN, 'Akses penuh sistem', false, Permission::values());

        // Anti eskalasi mensyaratkan pemberi role memegang seluruh izin role itu; agar admin kecamatan
        // dapat membuat operator sekolah, ia memegang semua izin (tetapi tidak dilindungi seperti super-admin).
        $this->role('admin-kecamatan', 'Admin kecamatan', false, Permission::values());

        $this->role('operator-sekolah', 'Operator sekolah (hanya memantau)', true, [
            'siswa.lihat', 'jadwal.lihat', 'sekolah.tautan',
        ]);

        $this->role(Role::SISWA, 'Siswa pendaftar', true, [
            'profil.lihat', 'profil.ubah', 'dokumen.lihat', 'dokumen.unggah', 'dokumen.hapus',
        ]);
    }

    /** @param list<string> $permissions */
    private function role(string $name, string $description, bool $scoped, array $permissions): void
    {
        $role = Role::updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $description, 'is_school_scoped' => $scoped],
        );
        $role->syncPermissions($permissions);
    }
}
