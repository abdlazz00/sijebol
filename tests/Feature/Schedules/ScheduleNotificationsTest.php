<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-notif-sesi');
    $this->admin = makeUserWith(['jadwal.buat', 'jadwal.ubah'], roleName: 'admin-notif-sesi');
    $this->service = app(ScheduleService::class);
});

function dataSesiBaru(Schedule $s, array $override = []): array
{
    return [...[
        'date' => $s->date->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00',
        'location' => $s->location, 'quota' => $s->quota,
    ], ...$override];
}

test('ubah jam atau lokasi memberi tahu hanya pendaftar yang masih relevan', function () {
    $schedule = makeSchedule($this->school, '2026-12-01');
    [$akunTerdaftar, $siswaTerdaftar] = siswaLengkap($this->school);
    [$akunMenunggu, $siswaMenunggu] = siswaLengkap($this->school);
    [$akunTolak, $siswaTolak] = siswaLengkap($this->school);
    [$akunLain, $siswaLain] = siswaLengkap($this->school);
    makeEntry(S::Terdaftar, $this->school, $siswaTerdaftar, $schedule);
    makeEntry(S::MenungguVerifikasi, $this->school, $siswaMenunggu, $schedule);
    makeEntry(S::Ditolak, $this->school, $siswaTolak, $schedule);
    makeEntry(S::Terdaftar, $this->school, $siswaLain, makeSchedule($this->school, '2026-12-02'));

    $this->service->update($schedule, dataSesiBaru($schedule, ['start_time' => '09:00', 'location' => 'Lapangan Utama']), $this->admin);

    foreach ([$akunTerdaftar, $akunMenunggu] as $akun) {
        Notification::assertSentToTimes($akun, RegistrationNotification::class, 1);
        Notification::assertSentTo($akun, RegistrationNotification::class, fn (RegistrationNotification $n) => $n->title() === 'Jadwal sesi berubah' && str_contains($n->body(), 'Lapangan Utama') && str_contains($n->body(), '09.00–12.00'));
    }
    Notification::assertNotSentTo([$akunTolak, $akunLain, $this->op, $this->admin], RegistrationNotification::class);
});

test('mengubah kuota saja tidak memberi tahu siapa pun', function () {
    $schedule = makeSchedule($this->school, '2026-12-01');
    [, $siswa] = siswaLengkap($this->school);
    makeEntry(S::Terdaftar, $this->school, $siswa, $schedule);

    $this->service->update($schedule, dataSesiBaru($schedule, ['quota' => 40]), $this->admin);

    Notification::assertNothingSent();
});

test('sesi yang sudah lewat tidak dapat diubah', function () {
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString());

    expect(fn () => $this->service->update($lewat, dataSesiBaru($lewat, ['location' => 'Baru']), $this->admin))
        ->toThrow(AccessRuleException::class, 'Sesi yang sudah lewat tidak dapat diubah.');
    expect($lewat->fresh()->location)->not->toBe('Baru');
});

test('batalkan sesi memberi tahu siswa yang pendaftarannya dibatalkan, bukan yang sudah ditandai', function () {
    $schedule = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
    [$akunA, $siswaA] = siswaLengkap($this->school);
    [$akunB, $siswaB] = siswaLengkap($this->school);
    [$akunC, $siswaC] = siswaLengkap($this->school);
    makeEntry(S::Terdaftar, $this->school, $siswaA, $schedule);
    makeEntry(S::PerluPerbaikan, $this->school, $siswaB, $schedule);
    makeEntry(S::SudahRekaman, $this->school, $siswaC, $schedule);

    $this->service->cancel($schedule, 'Gedung dipakai ujian.', $this->admin);

    foreach ([$akunA, $akunB] as $akun) {
        Notification::assertSentToTimes($akun, RegistrationNotification::class, 1);
        Notification::assertSentTo($akun, RegistrationNotification::class, fn (RegistrationNotification $n) => $n->title() === 'Sesi dibatalkan' && str_contains($n->body(), 'Gedung dipakai ujian.'));
    }
    Notification::assertNotSentTo([$akunC, $this->op], RegistrationNotification::class);
});

test('hapus sesi memeriksa pendaftaran di bawah kunci baris sesi', function () {
    $schedule = makeSchedule($this->school);
    DB::enableQueryLog();

    $this->service->delete($schedule, $this->admin);

    $kunci = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $q) => str_contains($q, 'from `schedules`') && str_contains($q, 'for update'));
    expect($kunci)->not->toBeEmpty();
});
