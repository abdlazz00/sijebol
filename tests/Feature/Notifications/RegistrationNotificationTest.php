<?php

use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;

test('toSchool hanya menjangkau pengguna sekolah itu yang berizin', function () {
    Notification::fake();
    $a = School::factory()->create();
    $b = School::factory()->create();
    $opA = makeUserWith(['jadwal.lihat'], $a, true, 'op');
    $opB = makeUserWith(['jadwal.lihat'], $b, true, 'op');
    $staf = makeUserWith(['jadwal.lihat'], roleName: 'staf');
    $tanpaIzin = makeUserWith(['siswa.lihat'], $a, true, 'op-lain');
    $admin = makeUserWith([], roleName: 'super-admin');

    $count = app(NotificationService::class)->toSchool('jadwal.lihat', ScheduleNotification::marked(1, 1, 'Sudah rekaman', '2026-12-01'), $a->id);

    expect($count)->toBe(1);
    Notification::assertSentTo($opA, ScheduleNotification::class);
    Notification::assertNotSentTo([$opB, $staf, $tanpaIzin, $admin], ScheduleNotification::class);
});

// ---- Notifikasi pendaftaran sesi untuk siswa (fase 7c)

use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Students\Models\Student;
use Carbon\Carbon;

function nSesi(): array
{
    return ['2026-10-20', '08:00', '12:00', 'Aula SMPN 1'];
}

test('isi pesan setiap pabrik notifikasi pendaftaran', function () {
    $deadline = Carbon::parse('2026-10-21 10:00:00', 'Asia/Jakarta');

    $approved = RegistrationNotification::approved(9, ...nSesi());
    $fix = RegistrationNotification::needsFix('Foto KK buram', $deadline);
    $rejected = RegistrationNotification::rejected('Dokumen tidak sesuai');
    $released = RegistrationNotification::seatReleased('Batas waktu perbaikan terlewati.');
    $byStaff = RegistrationNotification::cancelledByStaff('Sekolah Anda diganti oleh admin.');
    $changed = RegistrationNotification::sessionChanged(...nSesi());
    $cancelled = RegistrationNotification::sessionCancelled('2026-10-20', 'Gedung dipakai ujian.');
    $fixReminder = RegistrationNotification::fixReminder($deadline);
    $sessionReminder = RegistrationNotification::sessionReminder(...nSesi());

    expect($approved->title())->toBe('Pendaftaran disetujui')
        ->and($approved->body())->toContain('Selasa, 20 Oktober 2026')->toContain('08.00–12.00')->toContain('Aula SMPN 1')
        ->toContain('tiket')->toContain('Simpan tiket sebagai gambar')
        ->and($approved->url())->toBe('/me/tickets/9')
        ->and($fix->title())->toBe('Pendaftaran perlu diperbaiki')
        ->and($fix->body())->toContain('Foto KK buram')->toContain('sebelum')->toContain('21 Oktober 2026')
        ->and($fix->url())->toBe('/me')
        ->and($rejected->title())->toBe('Pendaftaran ditolak')
        ->and($rejected->body())->toContain('Dokumen tidak sesuai')->toContain('Kursi Anda dilepas')
        ->and($rejected->url())->toBe('/me/sessions')
        ->and($released->title())->toBe('Kursi Anda dilepas')
        ->and($released->body())->toContain('Batas waktu perbaikan terlewati.')->toContain('mendaftar')
        ->and($byStaff->title())->toBe('Pendaftaran dibatalkan petugas')
        ->and($byStaff->body())->toContain('Sekolah Anda diganti oleh admin.')
        ->and($changed->title())->toBe('Jadwal sesi berubah')
        ->and($changed->body())->toContain('08.00–12.00')->toContain('Aula SMPN 1')
        ->and($cancelled->title())->toBe('Sesi dibatalkan')
        ->and($cancelled->body())->toContain('Gedung dipakai ujian.')->toContain('ikut dibatalkan')
        ->and($fixReminder->title())->toBe('Batas perbaikan segera berakhir')
        ->and($fixReminder->url())->toBe('/me')
        ->and($sessionReminder->title())->toBe('Sesi rekaman besok')
        ->and($sessionReminder->body())->toContain('Aula SMPN 1');
    foreach ([$released, $byStaff, $changed, $cancelled, $sessionReminder] as $n) {
        expect($n->url())->toBe('/me/sessions');
    }
});

test('notifikasi pendaftaran memakai kanal database dan mail dengan tautan absolut', function () {
    $n = RegistrationNotification::approved(9, ...nSesi());
    $user = makeUserWith([], roleName: 'siswa');

    expect($n->via($user))->toBe(['database', 'mail'])
        ->and($n->toMail($user)->actionUrl)->toBe(url('/me/tickets/9'));
});

test('toStudent mengirim hanya ke akun siswa itu dan melewati yang tanpa akun atau nonaktif', function () {
    Notification::fake();
    $sekolah = School::factory()->create();
    [$akun, $siswa] = siswaLengkap($sekolah);
    [$lain] = siswaLengkap($sekolah);
    $operator = makeUserWith(['jadwal.lihat'], $sekolah, true, 'op-tostudent');
    $admin = makeUserWith(['verifikasi.putuskan'], roleName: 'admin-tostudent');
    $service = app(NotificationService::class);
    $n = RegistrationNotification::rejected('Alasan');

    expect($service->toStudent($siswa, $n))->toBeTrue();
    Notification::assertSentTo($akun, RegistrationNotification::class);
    Notification::assertNotSentTo([$lain, $operator, $admin], RegistrationNotification::class);

    $tanpaAkun = Student::factory()->for($sekolah)->create();
    expect($service->toStudent($tanpaAkun, $n))->toBeFalse();

    $akun->update(['is_active' => false]);
    expect($service->toStudent($siswa->fresh(), $n))->toBeFalse();
    Notification::assertSentToTimes($akun, RegistrationNotification::class, 1);
});

test('toStudent tetap menjangkau siswa walau pengirimnya operator sekolah lain (tanpa scope)', function () {
    Notification::fake();
    [$akun, $siswa] = siswaLengkap(School::factory()->create());
    $this->actingAs(makeUserWith(['jadwal.lihat'], School::factory()->create(), true, 'op-lain-sekolah'));

    expect(app(NotificationService::class)->toStudent($siswa, RegistrationNotification::rejected('x')))->toBeTrue();
    Notification::assertSentTo($akun, RegistrationNotification::class);
});

test('isi notifikasi tidak memuat NIK siswa', function () {
    $siswa = eligibleStudent();
    $n = RegistrationNotification::approved(1, ...nSesi());

    expect($n->body().$n->title())->not->toContain($siswa->nik);
});
