<?php

use Illuminate\Support\Facades\Schema;

test('tabel RBAC dan activity log tersedia', function () {
    foreach (['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'activity_log'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
});
