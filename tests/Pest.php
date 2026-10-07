<?php

use App\Domains\Access\Models\Role;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Test BE tidak boleh bergantung pada build FE: halaman Inertia boleh belum ada di manifest Vite.
        test()->withoutVite();
        config(['inertia.testing.ensure_pages_exist' => false]);
    })
    ->in('Feature');

/**
 * Buat user aktif dengan role `$roleName` yang memegang `$permissions`.
 * Role dengan nama sama dipakai ulang antar-panggilan, izinnya bertambah.
 *
 * @param  list<string>  $permissions
 */
function makeUserWith(array $permissions = [], ?School $school = null, bool $scoped = false, string $roleName = 'uji'): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $name) {
        PermissionModel::findOrCreate($name, 'web');
    }

    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['is_school_scoped' => $scoped]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create(['school_id' => $school?->id]);
    $user->assignRole($role);

    return $user;
}
