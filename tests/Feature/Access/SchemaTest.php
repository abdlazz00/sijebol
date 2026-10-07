<?php

use App\Domains\Access\Models\Role;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

test('skema akses tersedia', function () {
    expect(Schema::hasColumns('schools', ['name', 'npsn', 'level', 'address']))->toBeTrue()
        ->and(Schema::hasColumns('users', ['phone', 'school_id', 'is_active', 'activated_at', 'last_login_at', 'created_by']))->toBeTrue()
        ->and(Schema::hasColumns('roles', ['description', 'is_school_scoped']))->toBeTrue()
        ->and(Schema::hasColumns('account_activations', ['user_id', 'token_hash', 'expires_at', 'used_at']))->toBeTrue();
});

test('spatie memakai model Role kita dan kolom scope ter-cast', function () {
    expect(app(PermissionRegistrar::class)->getRoleClass())->toBe(Role::class);

    $role = Role::create(['name' => 'x', 'is_school_scoped' => true]);

    expect($role->fresh()->is_school_scoped)->toBeTrue();
});
