<?php

use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    $this->school = School::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

/** Pendaftaran siswa berakun pada sesi bertanggal tertentu. */
function pendaftaranKedaluwarsa(School $school, S $status, string $tanggal = '2026-10-29'): array
{
    [$akun, $siswa] = siswaLengkap($school);

    return [$akun, makeEntry($status, $school, $siswa, makeSchedule($school, $tanggal))];
}

test('perbaikan yang lewat tenggat menjadi Kedaluwarsa oleh sistem, kursi lepas, siswa diberi tahu sekali', function () {
    [$akun, $entry] = pendaftaranKedaluwarsa($this->school, S::PerluPerbaikan);
    $entry->update(['fix_deadline_at' => now()->subMinute()]);
    [$lain, $belum] = pendaftaranKedaluwarsa($this->school, S::PerluPerbaikan);
    $belum->update(['fix_deadline_at' => now()->addHour()]);

    $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(0);

    $fresh = $entry->fresh();
    expect($fresh->status)->toBe(S::Kedaluwarsa)->and($fresh->note)->toBe('Batas waktu perbaikan terlewati.')
        ->and($fresh->decided_by)->toBeNull()
        ->and($fresh->histories()->latest('id')->first()->by_user_id)->toBeNull()
        ->and($entry->schedule->seatsTaken())->toBe(0)
        ->and($belum->fresh()->status)->toBe(S::PerluPerbaikan);
    Notification::assertSentToTimes($akun, RegistrationNotification::class, 1);
    Notification::assertSentTo($akun, RegistrationNotification::class, fn (RegistrationNotification $n) => $n->title() === 'Kursi Anda dilepas');
    Notification::assertNotSentTo($lain, RegistrationNotification::class);
});

test('pendaftaran belum diputuskan pada sesi yang sudah lewat menjadi Kedaluwarsa, sesi hari ini dan besok tidak', function () {
    [$akunA, $menunggu] = pendaftaranKedaluwarsa($this->school, S::MenungguVerifikasi, '2026-10-18');
    [, $perbaikan] = pendaftaranKedaluwarsa($this->school, S::PerluPerbaikan, '2026-10-17');
    [, $hariIni] = pendaftaranKedaluwarsa($this->school, S::MenungguVerifikasi, '2026-10-19');
    [, $besok] = pendaftaranKedaluwarsa($this->school, S::MenungguVerifikasi, '2026-10-20');

    $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(0);

    expect($menunggu->fresh()->status)->toBe(S::Kedaluwarsa)->and($menunggu->fresh()->note)->toBe('Sesi berakhir sebelum pendaftaran diputuskan.')
        ->and($perbaikan->fresh()->status)->toBe(S::Kedaluwarsa)
        ->and($hariIni->fresh()->status)->toBe(S::MenungguVerifikasi)
        ->and($besok->fresh()->status)->toBe(S::MenungguVerifikasi);
    Notification::assertSentToTimes($akunA, RegistrationNotification::class, 1);
});

test('status lain tidak disentuh', function (S $status) {
    [$akun, $entry] = pendaftaranKedaluwarsa($this->school, $status, '2026-10-10');

    $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(0);

    expect($entry->fresh()->status)->toBe($status);
    Notification::assertNothingSent();
})->with([[S::Terdaftar], [S::SudahRekaman], [S::TidakHadir], [S::Ditolak], [S::Dibatalkan], [S::Kedaluwarsa]]);

test('dijalankan dua kali: satu perubahan dan satu notifikasi', function () {
    [$akun, $entry] = pendaftaranKedaluwarsa($this->school, S::PerluPerbaikan);
    $entry->update(['fix_deadline_at' => now()->subMinute()]);

    $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(0);
    $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(0);

    expect($entry->histories()->count())->toBe(1);
    Notification::assertSentToTimes($akun, RegistrationNotification::class, 1);
});

test('tenggat lewat sekaligus sesi lewat tetap satu perubahan dan satu notifikasi', function () {
    [$akun, $entry] = pendaftaranKedaluwarsa($this->school, S::PerluPerbaikan, '2026-10-18');
    $entry->update(['fix_deadline_at' => now()->subHour()]);

    $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(0);

    expect($entry->histories()->count())->toBe(1);
    Notification::assertSentToTimes($akun, RegistrationNotification::class, 1);
});

test('siswa tanpa akun tidak membuat galat dan dapat mendaftar sesi lain setelahnya', function () {
    $siswa = eligibleStudent($this->school);
    $entry = makeEntry(S::PerluPerbaikan, $this->school, $siswa, makeSchedule($this->school, '2026-10-29'));
    $entry->update(['fix_deadline_at' => now()->subMinute()]);

    $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(0);

    expect($entry->fresh()->status)->toBe(S::Kedaluwarsa)
        ->and(makeEntry(S::MenungguVerifikasi, $this->school, $siswa, makeSchedule($this->school, '2026-10-30'))->exists)->toBeTrue();
    Notification::assertNothingSent();
});

test('kegagalan pada satu pendaftaran tidak menghentikan yang lain dan tercermin di kode keluar', function () {
    [, $a] = pendaftaranKedaluwarsa($this->school, S::PerluPerbaikan);
    [, $b] = pendaftaranKedaluwarsa($this->school, S::PerluPerbaikan);
    foreach ([$a, $b] as $e) {
        $e->update(['fix_deadline_at' => now()->subMinute()]);
    }
    $nyata = new StatusTransitioner;
    $panggilan = 0;
    $this->instance(StatusTransitioner::class, Mockery::mock(StatusTransitioner::class, function ($m) use (&$panggilan, $nyata) {
        $m->shouldReceive('move')->andReturnUsing(function (...$args) use (&$panggilan, $nyata) {
            if (++$panggilan === 1) {
                throw new RuntimeException('gagal tiruan');
            }

            return $nyata->move(...$args);
        });
    }));

    $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(1);

    expect(collect([$a, $b])->map(fn ($e) => $e->fresh()->status)->filter(fn ($s) => $s === S::Kedaluwarsa))->toHaveCount(1);
});

test('pendaftaran yang diajukan ulang tepat setelah dipilih perintah tidak ikut dikedaluwarsakan', function () {
    [$akun, $entry] = pendaftaranKedaluwarsa($this->school, S::PerluPerbaikan);
    $entry->update(['fix_deadline_at' => now()->subMinute()]);

    // Menyimulasikan balapan: begitu perintah memuat entri, siswa sudah mengajukan ulang (tenggat kosong, status Menunggu).
    Event::listen('eloquent.retrieved: '.RegistrationStudent::class, function () use ($entry) {
        DB::table('registration_students')->where('id', $entry->id)
            ->update(['status' => S::MenungguVerifikasi->value, 'fix_deadline_at' => null]);
    });

    try {
        $this->artisan('sijebol:kedaluwarsakan')->assertExitCode(0);
    } finally {
        Event::forget('eloquent.retrieved: '.RegistrationStudent::class);
    }

    expect($entry->fresh()->status)->toBe(S::MenungguVerifikasi)->and($entry->fresh()->histories()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('dijadwalkan tiap jam tanpa tumpang tindih', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('sijebol:kedaluwarsakan')
        ->assertExitCode(0);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'sijebol:kedaluwarsakan'));
    expect($event->expression)->toBe('0 * * * *')->and($event->withoutOverlapping)->toBeTrue();
});
