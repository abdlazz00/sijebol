<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission as PermissionModel;

test('seeder membuat seluruh permission dan tiga role, dan aman diulang', function () {
    $this->seed(AccessSeeder::class);
    $this->seed(AccessSeeder::class);

    expect(PermissionModel::count())->toBe(count(Permission::values()))
        ->and(Role::count())->toBe(3)
        ->and(Role::findByName('super-admin')->permissions)->toHaveCount(count(Permission::values()))
        ->and(Role::findByName('operator-sekolah')->is_school_scoped)->toBeTrue()
        ->and(Role::findByName('admin-kecamatan')->is_school_scoped)->toBeFalse()
        ->and(Role::findByName('admin-kecamatan')->hasPermissionTo('user.buat'))->toBeTrue()
        ->and(Role::findByName('operator-sekolah')->hasPermissionTo('user.buat'))->toBeFalse();
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
