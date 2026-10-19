<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStatusHistory;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

test('kolom sesi ada, tabel batch dan kolom lamanya hilang', function () {
    expect(Schema::hasColumns('schedules', ['start_time', 'end_time', 'quota', 'cancelled_at']))->toBeTrue()
        ->and(Schema::hasTable('registrations'))->toBeFalse()
        ->and(Schema::hasColumn('registration_students', 'registration_id'))->toBeFalse()
        ->and(Schema::hasColumn('registration_students', 'schedule_id'))->toBeTrue();
});

test('satu siswa tidak boleh punya dua pendaftaran aktif', function (S $pertama) {
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->create();
    makeEntry($pertama, $school, $student);

    expect(fn () => makeEntry(S::MenungguVerifikasi, $school, $student))->toThrow(UniqueConstraintViolationException::class);
})->with([[S::MenungguVerifikasi], [S::PerluPerbaikan], [S::Terdaftar], [S::SudahRekaman]]);

test('pendaftaran yang tidak aktif tidak menghalangi pendaftaran baru', function (S $lama) {
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->create();
    makeEntry($lama, $school, $student);

    expect(makeEntry(S::MenungguVerifikasi, $school, $student)->exists)->toBeTrue();
})->with([[S::Ditolak], [S::Dibatalkan], [S::Kedaluwarsa], [S::TidakHadir], [S::BelumRekaman]]);

test('riwayat awal boleh tanpa status asal', function () {
    $entry = makeEntry(S::MenungguVerifikasi);

    $history = $entry->histories()->create([
        'from_status' => null, 'to_status' => S::MenungguVerifikasi, 'by_user_id' => User::factory()->create()->id,
    ]);

    expect(RegistrationStatusHistory::find($history->id)->from_status)->toBeNull();
});
