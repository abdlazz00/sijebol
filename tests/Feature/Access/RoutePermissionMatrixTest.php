<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;

dataset('rute', [
    ['GET', '/sekolah', 'sekolah.lihat'],
    ['GET', '/sekolah/buat', 'sekolah.buat'],
    ['POST', '/sekolah', 'sekolah.buat'],
    ['GET', '/sekolah/{school}/ubah', 'sekolah.ubah'],
    ['PUT', '/sekolah/{school}', 'sekolah.ubah'],
    ['GET', '/pengguna', 'user.lihat'],
    ['GET', '/pengguna/buat', 'user.buat'],
    ['POST', '/pengguna', 'user.buat'],
    ['GET', '/pengguna/{user}', 'user.lihat'],
    ['GET', '/pengguna/{user}/berhasil', 'user.lihat'],
    ['GET', '/pengguna/{user}/ubah', 'user.ubah'],
    ['PUT', '/pengguna/{user}', 'user.ubah'],
    ['POST', '/pengguna/{user}/nonaktifkan', 'user.nonaktifkan'],
    ['POST', '/pengguna/{user}/aktifkan', 'user.nonaktifkan'],
    ['POST', '/pengguna/{user}/kirim-ulang-aktivasi', 'user.kirim-ulang-aktivasi'],
    ['GET', '/role', 'role.lihat'],
    ['GET', '/role/buat', 'role.buat'],
    ['POST', '/role', 'role.buat'],
    ['GET', '/role/{role}/ubah', 'role.ubah'],
    ['PUT', '/role/{role}', 'role.ubah'],
    ['DELETE', '/role/{role}', 'role.hapus'],
    ['GET', '/log', 'log.lihat'],
    ['GET', '/siswa', 'siswa.lihat'],
    ['GET', '/siswa/buat', 'siswa.buat'],
    ['POST', '/siswa', 'siswa.buat'],
    ['GET', '/siswa/{student}', 'siswa.lihat'],
    ['GET', '/siswa/{student}/ubah', 'siswa.ubah'],
    ['PUT', '/siswa/{student}', 'siswa.ubah'],
    ['DELETE', '/siswa/{student}', 'siswa.hapus'],
    ['POST', '/siswa/{student}/dokumen/kk', 'dokumen.unggah'],
    ['GET', '/siswa/{student}/dokumen/kk', 'dokumen.lihat'],
    ['DELETE', '/siswa/{student}/dokumen/kk', 'dokumen.hapus'],
]);

function resolveUri(string $uri): string
{
    $target = User::factory()->create();
    $school = School::factory()->create();
    $role = Role::create(['name' => 'sasaran-'.uniqid()]);

    $student = Student::factory()->create();

    return str_replace(['{user}', '{school}', '{role}', '{student}'], [$target->id, $school->id, $role->id, $student->id], $uri);
}

it('menolak tanpa izin dan tidak menolak dengan izin', function (string $method, string $uri, string $permission) {
    $uri = resolveUri($uri);
    $others = array_values(array_diff(Permission::values(), [$permission]));

    $this->actingAs(makeUserWith($others, roleName: 'tanpa'))->call($method, $uri)->assertForbidden();

    $status = $this->actingAs(makeUserWith([$permission], roleName: 'dengan'))->call($method, $uri)->getStatusCode();
    expect($status)->not->toBe(403);
})->with('rute');
