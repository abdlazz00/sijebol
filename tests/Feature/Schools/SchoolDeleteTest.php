<?php

use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('tamu diarahkan ke login saat mencoba menghapus sekolah', function () {
    $school = School::factory()->create();

    $this->delete("/schools/{$school->id}")
        ->assertRedirect('/login');

    expect(School::find($school->id))->not->toBeNull();
});

test('pengguna tanpa izin sekolah.hapus ditolak (403)', function () {
    $school = School::factory()->create();

    $this->actingAs(makeUserWith(['sekolah.lihat', 'sekolah.ubah']))
        ->delete("/schools/{$school->id}")
        ->assertForbidden();

    expect(School::find($school->id))->not->toBeNull();
});

test('sekolah tanpa data terkait dapat dihapus oleh pengguna berizin', function () {
    $school = School::factory()->create(['name' => 'SMKN 99 Batam']);
    $user = makeUserWith(['sekolah.hapus']);

    $this->actingAs($user)
        ->delete("/schools/{$school->id}")
        ->assertRedirect('/schools')
        ->assertSessionHas('success', 'Sekolah dihapus.');

    expect(School::find($school->id))->toBeNull();
    $this->assertDatabaseMissing('schools', ['id' => $school->id]);

    $log = Activity::where('description', 'sekolah.dihapus')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->subject_id)->toBe($school->id)
        ->and($log->causer_id)->toBe($user->id);
});

test('sekolah dengan data siswa tidak dapat dihapus', function () {
    $school = School::factory()->create();
    Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs(makeUserWith(['sekolah.hapus']))
        ->delete("/schools/{$school->id}")
        ->assertSessionHas('error', 'Sekolah masih memiliki data terkait (siswa, sesi jadwal, atau pengguna) dan tidak dapat dihapus.');

    expect(School::find($school->id))->not->toBeNull();
});

test('sekolah dengan pengguna/operator tidak dapat dihapus', function () {
    $school = School::factory()->create();
    User::factory()->create(['school_id' => $school->id]);

    $this->actingAs(makeUserWith(['sekolah.hapus']))
        ->delete("/schools/{$school->id}")
        ->assertSessionHas('error', 'Sekolah masih memiliki data terkait (siswa, sesi jadwal, atau pengguna) dan tidak dapat dihapus.');

    expect(School::find($school->id))->not->toBeNull();
});

test('sekolah dengan sesi jadwal tidak dapat dihapus', function () {
    $school = School::factory()->create();
    Schedule::factory()->create(['school_id' => $school->id]);

    $this->actingAs(makeUserWith(['sekolah.hapus']))
        ->delete("/schools/{$school->id}")
        ->assertSessionHas('error', 'Sekolah masih memiliki data terkait (siswa, sesi jadwal, atau pengguna) dan tidak dapat dihapus.');

    expect(School::find($school->id))->not->toBeNull();
});
