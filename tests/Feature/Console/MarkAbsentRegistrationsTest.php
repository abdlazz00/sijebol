<?php

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

function pendaftaranHadir(School $school, S $status, string $tanggal = '2026-10-18'): RegistrationStudent
{
    return makeEntry($status, $school, schedule: makeSchedule($school, $tanggal));
}

test('Terdaftar pada sesi kemarin menjadi Tidak hadir oleh Sistem, kursi lepas, siswa dapat mendaftar lagi', function () {
    $entry = pendaftaranHadir($this->school, S::Terdaftar);

    $this->artisan('sijebol:tandai-tidak-hadir')->expectsOutput('1 pendaftaran ditandai tidak hadir.')->assertExitCode(0);

    $fresh = $entry->fresh();
    expect($fresh->status)->toBe(S::TidakHadir)
        ->and($fresh->note)->toBe('Tidak terpindai sampai sesi berakhir.')
        ->and($fresh->histories()->count())->toBe(1)
        ->and($fresh->histories()->first()->by_user_id)->toBeNull()
        ->and($entry->schedule->seatsTaken())->toBe(0)
        ->and(makeEntry(S::MenungguVerifikasi, $this->school, $entry->student, makeSchedule($this->school, '2026-10-29'))->exists)->toBeTrue();
    Notification::assertNothingSent();
});

test('sesi hari ini dan mendatang tidak disentuh', function () {
    $hariIni = pendaftaranHadir($this->school, S::Terdaftar, '2026-10-19');
    $besok = pendaftaranHadir($this->school, S::Terdaftar, '2026-10-20');

    $this->artisan('sijebol:tandai-tidak-hadir')->assertExitCode(0);

    expect($hariIni->fresh()->status)->toBe(S::Terdaftar)->and($besok->fresh()->status)->toBe(S::Terdaftar);
});

test('status selain Terdaftar pada sesi lampau tidak disentuh', function (S $status) {
    $entry = pendaftaranHadir($this->school, $status, '2026-10-10');

    $this->artisan('sijebol:tandai-tidak-hadir')->assertExitCode(0);

    expect($entry->fresh()->status)->toBe($status)->and($entry->histories()->count())->toBe(0);
})->with([
    [S::SudahRekaman], [S::BelumRekaman], [S::TidakHadir], [S::MenungguVerifikasi],
    [S::PerluPerbaikan], [S::Ditolak], [S::Dibatalkan], [S::Kedaluwarsa],
]);

test('dijalankan dua kali: satu perubahan', function () {
    $entry = pendaftaranHadir($this->school, S::Terdaftar);

    $this->artisan('sijebol:tandai-tidak-hadir')->assertExitCode(0);
    $this->artisan('sijebol:tandai-tidak-hadir')->expectsOutput('0 pendaftaran ditandai tidak hadir.')->assertExitCode(0);

    expect($entry->histories()->count())->toBe(1);
});

test('kegagalan pada satu pendaftaran tidak menghentikan yang lain dan tercermin di kode keluar', function () {
    $a = pendaftaranHadir($this->school, S::Terdaftar);
    $b = pendaftaranHadir($this->school, S::Terdaftar);
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

    $this->artisan('sijebol:tandai-tidak-hadir')->assertExitCode(1);

    expect(collect([$a, $b])->filter(fn ($e) => $e->fresh()->status === S::TidakHadir))->toHaveCount(1);
});

test('tiket yang dipindai tepat setelah dipilih perintah tidak ditimpa', function () {
    $entry = pendaftaranHadir($this->school, S::Terdaftar);

    // Menyimulasikan balapan: begitu perintah memuat entri, petugas sudah memindainya.
    Event::listen('eloquent.retrieved: '.RegistrationStudent::class, function () use ($entry) {
        DB::table('registration_students')->where('id', $entry->id)
            ->update(['status' => S::SudahRekaman->value, 'scanned_at' => now()]);
    });

    try {
        $this->artisan('sijebol:tandai-tidak-hadir')->assertExitCode(0);
    } finally {
        Event::forget('eloquent.retrieved: '.RegistrationStudent::class);
    }

    expect($entry->fresh()->status)->toBe(S::SudahRekaman)->and($entry->fresh()->histories()->count())->toBe(0);
});

test('dijadwalkan tiap jam tanpa tumpang tindih', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('sijebol:tandai-tidak-hadir')->assertExitCode(0);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'sijebol:tandai-tidak-hadir'));
    expect($event->expression)->toBe('0 * * * *')->and($event->withoutOverlapping)->toBeTrue();
});
