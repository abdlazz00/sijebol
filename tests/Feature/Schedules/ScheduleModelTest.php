<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Models\User;
use Carbon\Carbon;

afterEach(fn () => Carbon::setTestNow());

test('peta transisi penjadwalan dan penandaan', function () {
    expect(S::Terverifikasi->next())->toBe([S::Terjadwal])
        ->and(S::Terjadwal->next())->toBe([S::Terverifikasi, S::SudahRekaman, S::TidakHadir, S::BelumRekaman])
        ->and(S::SudahRekaman->next())->toBe([S::Terjadwal])
        ->and(S::TidakHadir->next())->toBe([S::Terjadwal])
        ->and(S::BelumRekaman->next())->toBe([S::Terjadwal]);
});

test('transisi baru sah dan atribut tambahan ikut diperbarui', function () {
    $actor = User::factory()->create();
    $schedule = makeSchedule();
    $entry = makeEntry(S::Terverifikasi, $schedule->school);

    $moved = app(StatusTransitioner::class)->move($entry, S::Terjadwal, $actor, null, ['schedule_id' => $schedule->id]);
    expect($moved->status)->toBe(S::Terjadwal)->and($moved->schedule_id)->toBe($schedule->id);

    $back = app(StatusTransitioner::class)->move($moved, S::Terverifikasi, $actor, null, ['schedule_id' => null]);
    expect($back->status)->toBe(S::Terverifikasi)->and($back->schedule_id)->toBeNull()
        ->and($back->histories()->count())->toBe(2);
});

test('transisi ilegal menuju dan dari penjadwalan ditolak', function (S $from, S $to) {
    $entry = makeEntry($from);

    expect(fn () => app(StatusTransitioner::class)->move($entry, $to, User::factory()->create()))->toThrow(AccessRuleException::class);
})->with([
    [S::Diajukan, S::Terjadwal], [S::PerluPerbaikan, S::Terjadwal], [S::SudahRekaman, S::TidakHadir],
    [S::TidakHadir, S::SudahRekaman], [S::Terverifikasi, S::SudahRekaman], [S::Terjadwal, S::Diajukan],
]);

test('batas hari jadwal memakai zona Asia/Jakarta', function () {
    // 20.00 UTC 8 Okt = 03.00 WIB 9 Okt.
    Carbon::setTestNow(Carbon::parse('2026-10-08 20:00:00', 'UTC'));

    expect(makeSchedule(date: '2026-10-08')->isPast())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-09')->isPast())->toBeFalse()
        ->and(makeSchedule(date: '2026-10-09')->isOpenForMarking())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-10')->isOpenForMarking())->toBeFalse();
});

test('helper menautkan entri ke jadwal sekolah yang sama', function () {
    $entry = makeScheduledEntry();

    expect($entry->schedule->school_id)->toBe($entry->school_id)
        ->and($entry->schedule->entries()->count())->toBe(1);
});
