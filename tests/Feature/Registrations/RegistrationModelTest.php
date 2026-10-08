<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Students\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;

test('indeks unik menolak dua entri aktif untuk siswa yang sama', function () {
    $student = Student::factory()->create();
    makeEntry(S::Diajukan, student: $student);

    expect(fn () => makeEntry(S::Terverifikasi, student: $student))->toThrow(UniqueConstraintViolationException::class);
});

test('entri draf dan ditolak tidak dihitung aktif', function () {
    $student = Student::factory()->create();
    makeEntry(S::Draf, student: $student);
    makeEntry(S::Draf, student: $student);
    makeEntry(S::Ditolak, student: $student);
    makeEntry(S::Diajukan, student: $student);

    expect(RegistrationStudent::where('student_id', $student->id)->count())->toBe(4);
});

test('peta status dan label enum', function () {
    expect(S::Draf->next())->toBe([S::Diajukan])
        ->and(S::Diajukan->next())->toBe([S::Terverifikasi, S::PerluPerbaikan, S::Ditolak])
        ->and(S::PerluPerbaikan->next())->toBe([S::Diajukan])
        ->and(S::Ditolak->next())->toBe([])
        ->and(S::PerluPerbaikan->isActive())->toBeTrue()
        ->and(S::PerluPerbaikan->locksStudent())->toBeFalse()
        ->and(S::Ditolak->isActive())->toBeFalse()
        ->and(S::PerluPerbaikan->label())->toBe('Perlu perbaikan');
});

test('siswa terkunci hanya saat entri sedang diproses', function (S $status, bool $locked) {
    $student = Student::factory()->create();
    makeEntry($status, student: $student);

    expect($student->isLocked())->toBe($locked);
})->with([
    [S::Draf, false], [S::Diajukan, true], [S::PerluPerbaikan, false],
    [S::Terverifikasi, true], [S::Ditolak, false],
]);
