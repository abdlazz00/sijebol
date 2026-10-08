<?php

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

test('batas umur 16 sampai 17 tahun', function (string $lahir, bool $eligible) {
    $s = lengkap(Student::factory()->create(['birth_date' => $lahir]));

    expect(app(EligibilityService::class)->check($s, hariAcuan())['eligible'])->toBe($eligible);
})->with([
    'sehari sebelum ulang tahun ke-16' => ['2010-10-09', false],
    'tepat ulang tahun ke-16' => ['2010-10-08', true],
    'sehari sebelum ulang tahun ke-18' => ['2008-10-09', true],
    'tepat ulang tahun ke-18' => ['2008-10-08', false],
]);

test('alasan jelas bila dokumen belum lengkap atau umur salah', function () {
    $s = Student::factory()->create(['birth_date' => '2000-01-01']);
    $s->documents()->create(['type' => 'kk', 'path' => 'kk', 'mime' => 'application/pdf', 'size' => 1]);

    $hasil = app(EligibilityService::class)->check($s->load('documents'), hariAcuan());

    expect($hasil['eligible'])->toBeFalse()
        ->and($hasil['reasons'])->toBe([
            'Umur harus 16 atau 17 tahun (umur sekarang 26).',
            'Akta kelahiran belum diunggah.',
        ]);
});

test('birthDateBounds konsisten dengan check', function () {
    [$awal, $akhir] = app(EligibilityService::class)->birthDateBounds(hariAcuan());

    expect($awal)->toBe('2008-10-09')->and($akhir)->toBe('2010-10-08');
});
