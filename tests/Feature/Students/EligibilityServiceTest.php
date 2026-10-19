<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use Carbon\Carbon;

function lengkap(Student $s): Student
{
    foreach (['kk', 'akta_lahir'] as $type) {
        $s->documents()->create(['type' => $type, 'path' => $type, 'mime' => 'application/pdf', 'size' => 1]);
    }

    return $s->load('documents');
}

function hariAcuan(): Carbon
{
    return Carbon::parse('2026-10-08', 'Asia/Jakarta');
}

test('batas umur minimal 16 tahun tanpa batas atas', function (string $lahir, bool $eligible) {
    $s = lengkap(Student::factory()->create(['birth_date' => $lahir]));

    expect(app(EligibilityService::class)->check($s, hariAcuan())['eligible'])->toBe($eligible);
})->with([
    'sehari sebelum ulang tahun ke-16' => ['2010-10-09', false],
    'tepat ulang tahun ke-16' => ['2010-10-08', true],
    'sehari sebelum ulang tahun ke-18' => ['2008-10-09', true],
    'tepat ulang tahun ke-18' => ['2008-10-08', true],
    'umur 19 tahun' => ['2007-10-08', true],
]);

test('alasan jelas bila dokumen belum lengkap atau umur di bawah 16 tahun', function () {
    $s = Student::factory()->create(['birth_date' => '2015-01-01']);
    $s->documents()->create(['type' => 'kk', 'path' => 'kk', 'mime' => 'application/pdf', 'size' => 1]);

    $hasil = app(EligibilityService::class)->check($s->load('documents'), hariAcuan());

    expect($hasil['eligible'])->toBeFalse()
        ->and($hasil['reasons'])->toBe([
            'Umur minimal 16 tahun (umur 11).',
            'Akta kelahiran belum diunggah.',
        ]);
});

test('latestBirthDate konsisten dengan check', function () {
    $latest = app(EligibilityService::class)->latestBirthDate(hariAcuan());

    expect($latest)->toBe('2010-10-08');
});

test('entri aktif membuat siswa tidak eligible, draf dan ditolak tidak', function (S $status, bool $eligible) {
    $siswa = eligibleStudent();
    makeEntry($status, student: $siswa);

    $check = app(EligibilityService::class)->check($siswa->fresh('documents'));

    expect($check['eligible'])->toBe($eligible);
    if (! $eligible) {
        expect($check['reasons'])->toContain('Sudah memiliki pendaftaran sesi yang aktif.');
    }
})->with([
    [S::Dibatalkan, true], [S::Ditolak, true], [S::MenungguVerifikasi, false],
    [S::PerluPerbaikan, false], [S::Terdaftar, false],
]);

test('entri yang sedang diperiksa tidak dihitung sebagai bentrok', function () {
    $siswa = eligibleStudent();
    $entry = makeEntry(S::PerluPerbaikan, student: $siswa);

    expect(app(EligibilityService::class)->check($siswa->fresh('documents'), null, $entry->id)['eligible'])->toBeTrue();
});
