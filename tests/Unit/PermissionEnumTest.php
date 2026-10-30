<?php

use App\Domains\Access\Enums\Permission;

test('permission berformat modul.aksi dan unik', function () {
    $values = Permission::values();

    expect(array_unique($values))->toHaveCount(count($values));

    foreach ($values as $value) {
        expect($value)->toMatch('/^[a-z]+\.[a-z-]+$/');
    }
});

test('setiap modul pada spec punya permission lihat', function () {
    foreach (['user', 'role', 'sekolah', 'siswa', 'dokumen', 'pendaftaran', 'verifikasi', 'jadwal', 'rekaman', 'laporan', 'log', 'profil'] as $module) {
        expect(Permission::values())->toContain("$module.lihat");
    }
});
