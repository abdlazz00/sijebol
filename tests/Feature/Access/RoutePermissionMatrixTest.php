<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;

dataset('rute', [
    ['GET', '/schools', 'sekolah.lihat'],
    ['GET', '/schools/create', 'sekolah.buat'],
    ['POST', '/schools', 'sekolah.buat'],
    ['GET', '/schools/{school}/edit', 'sekolah.ubah'],
    ['PUT', '/schools/{school}', 'sekolah.ubah'],
    ['DELETE', '/schools/{school}', 'sekolah.hapus'],
    ['GET', '/me', 'profil.lihat'],
    ['PUT', '/me', 'profil.ubah'],
    ['GET', '/registration-links', 'sekolah.tautan'],
    ['POST', '/registration-links/{school}/reset', 'sekolah.reset-tautan'],
    ['GET', '/users', 'user.lihat'],
    ['GET', '/users/create', 'user.buat'],
    ['POST', '/users', 'user.buat'],
    ['GET', '/users/{user}', 'user.lihat'],
    ['GET', '/users/{user}/done', 'user.lihat'],
    ['GET', '/users/{user}/edit', 'user.ubah'],
    ['PUT', '/users/{user}', 'user.ubah'],
    ['POST', '/users/{user}/deactivate', 'user.nonaktifkan'],
    ['POST', '/users/{user}/activate', 'user.nonaktifkan'],
    ['POST', '/users/{user}/resend-activation', 'user.kirim-ulang-aktivasi'],
    ['GET', '/roles', 'role.lihat'],
    ['GET', '/roles/create', 'role.buat'],
    ['POST', '/roles', 'role.buat'],
    ['GET', '/roles/{role}/edit', 'role.ubah'],
    ['PUT', '/roles/{role}', 'role.ubah'],
    ['DELETE', '/roles/{role}', 'role.hapus'],
    ['GET', '/audit-logs', 'log.lihat'],
    ['GET', '/students', 'siswa.lihat'],
    ['GET', '/students/{student}', 'siswa.lihat'],
    ['GET', '/students/{student}/edit', 'siswa.ubah'],
    ['PUT', '/students/{student}', 'siswa.ubah'],
    ['DELETE', '/students/{student}', 'siswa.hapus'],
    ['POST', '/students/{student}/documents/kk', 'dokumen.unggah'],
    ['GET', '/students/{student}/documents/kk', 'dokumen.lihat'],
    ['DELETE', '/students/{student}/documents/kk', 'dokumen.hapus'],
    ['GET', '/me/sessions', 'pendaftaran.lihat'],
    ['GET', '/me/tickets/{entry}', 'pendaftaran.lihat'],
    ['POST', '/me/sessions/{schedule}/register', 'pendaftaran.buat'],
    ['POST', '/me/registrations/{entry}/cancel', 'pendaftaran.buat'],
    ['POST', '/me/registrations/{entry}/resubmit', 'pendaftaran.buat'],
    ['GET', '/verifications', 'verifikasi.lihat'],
    ['GET', '/verifications/{entry}', 'verifikasi.lihat'],
    ['POST', '/verifications/{entry}/decide', 'verifikasi.putuskan'],
    ['GET', '/schedules', 'jadwal.lihat'],
    ['GET', '/schedules/create', 'jadwal.buat'],
    ['POST', '/schedules', 'jadwal.buat'],
    ['GET', '/schedules/{schedule}', 'jadwal.lihat'],
    ['GET', '/schedules/{schedule}/edit', 'jadwal.ubah'],
    ['PUT', '/schedules/{schedule}', 'jadwal.ubah'],
    ['POST', '/schedules/{schedule}/cancel', 'jadwal.ubah'],
    ['POST', '/schedules/{schedule}/entries/{entry}/cancel', 'verifikasi.putuskan'],
    ['GET', '/schedules/{schedule}/recording', 'rekaman.lihat'],
    ['POST', '/schedules/{schedule}/recording', 'rekaman.tandai'],
    ['GET', '/schedules/{schedule}/scan', 'rekaman.pindai'],
    ['POST', '/schedules/{schedule}/scan', 'rekaman.pindai'],
    ['GET', '/reports', 'laporan.lihat'],
    ['GET', '/reports/export', 'laporan.lihat'],
    ['GET', '/reports/export-sessions', 'laporan.lihat'],
    ['DELETE', '/schedules/{schedule}', 'jadwal.ubah'],
    ['POST', '/schedules/{schedule}/recording/{entry}/cancel', 'rekaman.tandai'],
    ['POST', '/verifications/approve-batch', 'verifikasi.putuskan'],
]);

function resolveUri(string $uri): string
{
    $target = User::factory()->create();
    $school = School::factory()->create();
    $role = Role::create(['name' => 'sasaran-'.uniqid()]);

    $student = Student::factory()->create();
    $entry = makeEntry();

    return str_replace(
        ['{user}', '{school}', '{role}', '{student}', '{entry}', '{schedule}', '{slot}'],
        [$target->id, $school->id, $role->id, $student->id, $entry->id, $entry->schedule_id, $entry->id],
        $uri,
    );
}

it('menolak tanpa izin dan tidak menolak dengan izin', function (string $method, string $uri, string $permission) {
    $uri = resolveUri($uri);
    $others = array_values(array_diff(Permission::values(), [$permission]));

    $this->actingAs(makeUserWith($others, roleName: 'tanpa'))->call($method, $uri)->assertForbidden();

    $status = $this->actingAs(makeUserWith([$permission], roleName: 'dengan'))->call($method, $uri)->getStatusCode();
    expect($status)->not->toBe(403);
})->with('rute');
