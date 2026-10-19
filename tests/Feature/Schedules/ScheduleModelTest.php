<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
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

test('tenggat perbaikan = yang lebih awal antara 48 jam dan akhir hari sebelum sesi', function (string $sekarang, string $tanggalSesi, string $harapan) {
    $now = Carbon::parse($sekarang, 'Asia/Jakarta');
    Carbon::setTestNow($now);

    expect(makeSchedule(date: $tanggalSesi)->fixDeadlineFrom($now)->setTimezone('Asia/Jakarta')->toDateTimeString())->toBe($harapan);
})->with([
    'sesi jauh: 48 jam' => ['2026-10-19 10:00:00', '2026-10-29', '2026-10-21 10:00:00'],
    'sesi besok: akhir hari ini' => ['2026-10-19 10:00:00', '2026-10-20', '2026-10-19 23:59:59'],
    'sesi lusa: dipotong akhir H-1' => ['2026-10-19 22:00:00', '2026-10-21', '2026-10-20 23:59:59'],
]);

test('jendela scan membentang dari toleransi sebelum jam mulai sampai toleransi sesudah jam selesai (WIB)', function () {
    $schedule = Schedule::factory()->create(['date' => '2026-10-20', 'start_time' => '08:00', 'end_time' => '12:00']);

    [$from, $to] = $schedule->scanWindow();
    expect($from->format('Y-m-d H:i:s'))->toBe('2026-10-20 07:00:00')
        ->and($to->format('Y-m-d H:i:s'))->toBe('2026-10-20 13:00:00')
        ->and($from->getTimezone()->getName())->toBe('Asia/Jakarta');

    config(['sijebol.scan_tolerance_minutes' => 15]);
    [$from, $to] = $schedule->scanWindow();
    expect($from->format('H:i'))->toBe('07:45')->and($to->format('H:i'))->toBe('12:15');
});
