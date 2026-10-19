<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;

afterEach(fn () => Carbon::setTestNow());

test('seatsTaken menghitung hanya status penahan kursi', function () {
    $schedule = makeSchedule();
    foreach (S::cases() as $status) {
        makeEntry($status, $schedule->school, schedule: $schedule);
    }

    expect($schedule->seatsTaken())->toBe(count(S::holdingSeat()))
        ->and($schedule->seatsTaken())->toBe(5);
});

test('pendaftaran dibuka sampai H-2 inklusif dan ditutup H-1', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));

    expect(makeSchedule(date: '2026-10-22')->isOpenForRegistration())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-21')->isOpenForRegistration())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-20')->isOpenForRegistration())->toBeFalse()
        ->and(makeSchedule(date: '2026-10-21')->lastRegistrationDay())->toBe('2026-10-19');
});

test('sesi dibatalkan tidak menerima pendaftaran maupun perubahan siswa', function () {
    $schedule = makeSchedule();
    $schedule->update(['cancelled_at' => now()]);

    expect($schedule->isCancelled())->toBeTrue()
        ->and($schedule->isOpenForRegistration())->toBeFalse()
        ->and($schedule->allowsStudentChanges())->toBeFalse();
});

test('siswa boleh mengubah sampai sehari sebelum sesi, bukan pada hari-H', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));

    expect(makeSchedule(date: '2026-10-20')->allowsStudentChanges())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-19')->allowsStudentChanges())->toBeFalse();
});

test('batas hari jadwal memakai zona Asia/Jakarta', function () {
    // 20.00 UTC 8 Okt = 03.00 WIB 9 Okt.
    Carbon::setTestNow(Carbon::parse('2026-10-08 20:00:00', 'UTC'));

    expect(makeSchedule(date: '2026-10-08')->isPast())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-09')->isPast())->toBeFalse()
        ->and(makeSchedule(date: '2026-10-09')->isOpenForMarking())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-10')->isOpenForMarking())->toBeFalse();
});

test('helper menautkan entri ke sesi sekolah yang sama', function () {
    $entry = makeEntry();

    expect($entry->schedule->school_id)->toBe($entry->school_id)
        ->and($entry->student->school_id)->toBe($entry->school_id)
        ->and($entry->schedule->quota)->toBe(30);
});

test('entri sekolah lain tidak terhitung di sesi ini', function () {
    $schedule = makeSchedule();
    makeEntry(S::Terdaftar, School::factory()->create());

    expect($schedule->seatsTaken())->toBe(0);
});
