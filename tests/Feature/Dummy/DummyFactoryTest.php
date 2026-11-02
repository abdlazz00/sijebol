<?php

use App\Domains\Schedules\Models\Schedule;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;

it('membuat siswa dummy bertanda dengan umur yang diminta', function () {
    $siswa = Student::factory()->dummy(17)->make();

    expect($siswa->nik)->toMatch('/^9999\d{12}$/')
        ->and($siswa->nisn)->toMatch('/^99\d{8}$/')
        ->and($siswa->gender)->toBeIn(['L', 'P'])
        ->and(app(EligibilityService::class)->age($siswa))->toBe(17);
});

it('membuat siswa dummy dengan NIK dan NISN unik', function () {
    $siswa = Student::factory()->dummy()->count(50)->make();

    expect($siswa->pluck('nik')->unique())->toHaveCount(50)
        ->and($siswa->pluck('nisn')->unique())->toHaveCount(50);
});

it('menandai sesi dibatalkan', function () {
    expect(Schedule::factory()->cancelled()->make()->cancelled_at)->not->toBeNull();
});
