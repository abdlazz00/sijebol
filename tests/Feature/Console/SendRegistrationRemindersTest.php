<?php

use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    $this->school = School::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

function pendaftaranPengingat(School $school, S $status, string $tanggal = '2026-10-29'): array
{
    [$akun, $siswa] = siswaLengkap($school);

    return [$akun, makeEntry($status, $school, $siswa, makeSchedule($school, $tanggal))];
}

test('pengingat batas perbaikan dikirim sekali saat tenggat tinggal 24 jam atau kurang', function () {
    [$dekat, $a] = pendaftaranPengingat($this->school, S::PerluPerbaikan);
    [$jauh, $b] = pendaftaranPengingat($this->school, S::PerluPerbaikan);
    [$lewat, $c] = pendaftaranPengingat($this->school, S::PerluPerbaikan);
    $a->update(['fix_deadline_at' => now()->addHours(20)]);
    $b->update(['fix_deadline_at' => now()->addHours(30)]);
    $c->update(['fix_deadline_at' => now()->subMinute()]);

    $this->artisan('sijebol:kirim-pengingat')->assertExitCode(0);
    $this->artisan('sijebol:kirim-pengingat')->assertExitCode(0);

    Notification::assertSentToTimes($dekat, RegistrationNotification::class, 1);
    Notification::assertSentTo($dekat, RegistrationNotification::class, fn (RegistrationNotification $n) => $n->title() === 'Batas perbaikan segera berakhir');
    Notification::assertNotSentTo([$jauh, $lewat], RegistrationNotification::class);
    expect($a->fresh()->fix_reminded_at)->not->toBeNull()->and($b->fresh()->fix_reminded_at)->toBeNull();
});

test('pengingat sesi besok baru dikirim sejak pukul 08.00 dan hanya sekali', function () {
    [$akun, $entry] = pendaftaranPengingat($this->school, S::Terdaftar, '2026-10-20');

    Carbon::setTestNow(Carbon::parse('2026-10-19 07:59:00', 'Asia/Jakarta'));
    $this->artisan('sijebol:kirim-pengingat')->assertExitCode(0);
    Notification::assertNothingSent();

    Carbon::setTestNow(Carbon::parse('2026-10-19 08:00:00', 'Asia/Jakarta'));
    $this->artisan('sijebol:kirim-pengingat')->assertExitCode(0);
    $this->artisan('sijebol:kirim-pengingat')->assertExitCode(0);

    Notification::assertSentToTimes($akun, RegistrationNotification::class, 1);
    Notification::assertSentTo($akun, RegistrationNotification::class, fn (RegistrationNotification $n) => $n->title() === 'Sesi rekaman besok');
    expect($entry->fresh()->session_reminded_at)->not->toBeNull();
});

test('pengingat sesi tidak dikirim untuk sesi lusa, sesi dibatalkan, atau status selain Terdaftar', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00', 'Asia/Jakarta'));
    [$lusa] = pendaftaranPengingat($this->school, S::Terdaftar, '2026-10-21');
    [$batal, $e] = pendaftaranPengingat($this->school, S::Terdaftar, '2026-10-20');
    $e->schedule->update(['cancelled_at' => now()]);
    [$menunggu] = pendaftaranPengingat($this->school, S::MenungguVerifikasi, '2026-10-20');
    [$hadir] = pendaftaranPengingat($this->school, S::SudahRekaman, '2026-10-20');

    $this->artisan('sijebol:kirim-pengingat')->assertExitCode(0);

    Notification::assertNothingSent();
});

test('siswa tanpa akun dilewati tanpa galat', function () {
    $entry = makeEntry(S::Terdaftar, $this->school, eligibleStudent($this->school), makeSchedule($this->school, '2026-10-20'));
    Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00', 'Asia/Jakarta'));

    $this->artisan('sijebol:kirim-pengingat')->assertExitCode(0);

    Notification::assertNothingSent();
});

test('dijadwalkan tiap jam tanpa tumpang tindih', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('sijebol:kirim-pengingat')->assertExitCode(0);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'sijebol:kirim-pengingat'));
    expect($event->expression)->toBe('0 * * * *')->and($event->withoutOverlapping)->toBeTrue();
});
