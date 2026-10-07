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

        $this->role('admin-kecamatan', 'Admin kecamatan', false, [
            ...$this->modules(['user', 'role', 'sekolah', 'verifikasi', 'jadwal', 'rekaman']),
            'laporan.lihat', 'log.lihat', 'siswa.lihat', 'pendaftaran.lihat', 'dokumen.lihat',
        ]);

        $this->role('operator-sekolah', 'Operator sekolah', true, [
            ...$this->modules(['siswa', 'dokumen', 'pendaftaran']),
            'jadwal.lihat',
        ]);
    }

    /**
     * @param  list<string>  $modules
     * @return list<string>
     */
    private function modules(array $modules): array
    {
        return array_values(array_filter(
            Permission::values(),
            fn (string $name) => in_array(explode('.', $name)[0], $modules, true),
        ));
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
