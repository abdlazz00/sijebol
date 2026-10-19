<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Schedules\Services\RecordingService;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

afterEach(fn () => Carbon::setTestNow());

beforeEach(function () {
    // Penerima notifikasi penandaan dicari lewat permission ini; di produksi sudah dibuat seeder.
    Permission::findOrCreate('jadwal.lihat', 'web');
    Notification::fake();
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['rekaman.tandai'], roleName: 'admin-rek');
    $this->service = app(RecordingService::class);
    $this->hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
});

test('penandaan massal mengubah status, mencatat riwayat, catatan, dan satu log', function (S $status) {
    $a = makeEntry(S::Terdaftar, schedule: $this->hariIni);
    $b = makeEntry(S::Terdaftar, schedule: $this->hariIni);

    $this->service->mark($this->hariIni, [$a->id, $b->id], $status, 'catatan bersama', $this->admin);

    foreach ([$a, $b] as $e) {
        expect($e->fresh()->status)->toBe($status)->and($e->fresh()->note)->toBe('catatan bersama')
            ->and($e->fresh()->schedule_id)->toBe($this->hariIni->id)->and($e->histories()->count())->toBe(1);
    }
    expect(Activity::where('description', 'rekaman.ditandai')->count())->toBe(1);
    Notification::assertNothingSent();
})->with([[S::SudahRekaman], [S::TidakHadir], [S::BelumRekaman]]);

test('penandaan sebelum hari kegiatan ditolak', function () {
    $besok = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
    $e = makeEntry(S::Terdaftar, schedule: $besok);

    expect(fn () => $this->service->mark($besok, [$e->id], S::SudahRekaman, null, $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->status)->toBe(S::Terdaftar);
});

test('pukul 03.00 WIB sudah dihitung hari kegiatan walau tanggal UTC masih kemarin', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 20:00:00', 'UTC'));
    $jadwal = makeSchedule($this->school, '2026-10-09');
    $e = makeEntry(S::Terdaftar, schedule: $jadwal);

    $this->service->mark($jadwal, [$e->id], S::SudahRekaman, null, $this->admin);

    expect($e->fresh()->status)->toBe(S::SudahRekaman);
});

test('satu entri tidak sah menggagalkan seluruh penandaan', function (string $kasus) {
    $ok = makeEntry(S::Terdaftar, schedule: $this->hariIni);
    $buruk = match ($kasus) {
        'sudah berstatus akhir' => makeEntry(S::SudahRekaman, schedule: $this->hariIni),
        'milik jadwal lain' => makeEntry(S::Terdaftar, schedule: makeSchedule($this->school, now('Asia/Jakarta')->toDateString())),
    };

    expect(fn () => $this->service->mark($this->hariIni, [$ok->id, $buruk->id], S::SudahRekaman, null, $this->admin))
        ->toThrow(ValidationException::class);

    expect($ok->fresh()->status)->toBe(S::Terdaftar)->and($ok->histories()->count())->toBe(0);
})->with(['sudah berstatus akhir', 'milik jadwal lain']);

test('status penandaan harus salah satu dari tiga status akhir', function () {
    $e = makeEntry(S::Terdaftar, schedule: $this->hariIni);

    expect(fn () => $this->service->mark($this->hariIni, [$e->id], S::MenungguVerifikasi, null, $this->admin))->toThrow(AccessRuleException::class);
    expect(fn () => $this->service->mark($this->hariIni, [$e->id], S::Dibatalkan, null, $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->schedule_id)->toBe($this->hariIni->id);
});

test('Tidak hadir dapat mendaftar sesi lain, Sudah rekaman tidak; kunci data ikut terbuka', function () {
    [$akunAbsen, $absen] = siswaLengkap($this->school);
    [$akunHadir, $hadir] = siswaLengkap($this->school);
    $e1 = makeEntry(S::Terdaftar, $this->school, $absen, $this->hariIni);
    $e2 = makeEntry(S::Terdaftar, $this->school, $hadir, $this->hariIni);
    expect($absen->isLocked())->toBeTrue();

    $this->service->mark($this->hariIni, [$e1->id], S::TidakHadir, null, $this->admin);
    $this->service->mark($this->hariIni, [$e2->id], S::SudahRekaman, null, $this->admin);
    expect($absen->isLocked())->toBeFalse()->and($hadir->isLocked())->toBeTrue();

    $berikut = makeSchedule($this->school, now('Asia/Jakarta')->addDays(10)->toDateString());
    $registrations = app(RegistrationService::class);

    $this->actingAs($akunAbsen);
    expect($registrations->register($akunAbsen, $berikut)->status)->toBe(S::MenungguVerifikasi);

    $this->actingAs($akunHadir);
    expect(fn () => $registrations->register($akunHadir, $berikut))->toThrow(AccessRuleException::class);
});

test('penandaan massal memberi tahu operator sekolah itu satu kali', function () {
    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-mark');
    $lain = makeUserWith(['jadwal.lihat'], School::factory()->create(), true, 'op-mark');
    $a = makeEntry(S::Terdaftar, schedule: $this->hariIni);
    $b = makeEntry(S::Terdaftar, schedule: $this->hariIni);

    $this->service->mark($this->hariIni, [$a->id, $b->id], S::TidakHadir, null, $this->admin);

    Notification::assertSentToTimes($op, ScheduleNotification::class, 1);
    Notification::assertSentTo($op, ScheduleNotification::class, fn (ScheduleNotification $n) => $n->title() === 'Rekaman ditandai' && str_starts_with($n->body(), '2 siswa ditandai Tidak hadir'));
    Notification::assertNotSentTo($lain, ScheduleNotification::class);
});

test('batalkan penandaan mengembalikan ke Terdaftar dengan sesi utuh, riwayat, log, dan notifikasi', function (S $status) {
    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-undo');
    $e = makeEntry(S::Terdaftar, schedule: $this->hariIni);
    $this->service->mark($this->hariIni, [$e->id], $status, null, $this->admin);

    $this->service->undo($e->fresh(), 'Salah tandai', $this->admin);

    expect($e->fresh()->status)->toBe(S::Terdaftar)
        ->and($e->fresh()->schedule_id)->toBe($this->hariIni->id)
        ->and($e->fresh()->note)->toBe('Salah tandai')
        ->and($e->histories()->count())->toBe(2)
        ->and(Activity::where('description', 'rekaman.dibatalkan')->count())->toBe(1);
    Notification::assertSentTo($op, ScheduleNotification::class, fn (ScheduleNotification $n) => $n->title() === 'Penandaan dibatalkan');
})->with([[S::SudahRekaman], [S::TidakHadir], [S::BelumRekaman]]);

test('batalkan hanya untuk entri yang sudah ditandai', function () {
    $e = makeEntry(S::Terdaftar, schedule: $this->hariIni);

    expect(fn () => $this->service->undo($e, 'x', $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->status)->toBe(S::Terdaftar)->and($e->histories()->count())->toBe(0);
});

test('batalkan ditolak bila siswa sudah punya pendaftaran aktif lain', function () {
    $student = eligibleStudent($this->school);
    $lama = makeEntry(S::Terdaftar, $this->school, $student);
    $lama->update(['schedule_id' => $this->hariIni->id]);
    $this->service->mark($this->hariIni, [$lama->id], S::TidakHadir, null, $this->admin);
    makeEntry(S::MenungguVerifikasi, $this->school, $student);

    expect(fn () => $this->service->undo($lama->fresh(), 'x', $this->admin))->toThrow(AccessRuleException::class);

    expect($lama->fresh()->status)->toBe(S::TidakHadir)->and(Activity::where('description', 'rekaman.dibatalkan')->count())->toBe(0);
});

test('batalkan Tidak hadir ditolak bila kursinya sudah terpakai sampai kuota penuh', function () {
    $this->hariIni->update(['quota' => 2]);
    $absen = makeEntry(S::TidakHadir, $this->school, schedule: $this->hariIni);
    makeEntry(S::Terdaftar, $this->school, schedule: $this->hariIni);
    makeEntry(S::Terdaftar, $this->school, schedule: $this->hariIni);

    expect(fn () => $this->service->undo($absen, 'Salah tandai', $this->admin))
        ->toThrow(AccessRuleException::class, 'Kuota sesi sudah penuh');

    expect($absen->fresh()->status)->toBe(S::TidakHadir)->and($this->hariIni->seatsTaken())->toBe(2);
});

test('batalkan Tidak hadir diterima selama kuota masih longgar, Sudah rekaman tidak terkena batas kuota', function () {
    $this->hariIni->update(['quota' => 2]);
    $absen = makeEntry(S::TidakHadir, $this->school, schedule: $this->hariIni);
    $hadir = makeEntry(S::SudahRekaman, $this->school, schedule: $this->hariIni);
    makeEntry(S::Terdaftar, $this->school, schedule: $this->hariIni);

    // Kursi penuh (2 dari 2) tetapi Sudah rekaman sudah memegang kursinya sendiri.
    $this->service->undo($hadir, 'Salah tandai', $this->admin);
    expect($hadir->fresh()->status)->toBe(S::Terdaftar);

    expect(fn () => $this->service->undo($absen, 'Salah tandai', $this->admin))->toThrow(AccessRuleException::class);
    $this->hariIni->update(['quota' => 3]);
    $this->service->undo($absen, 'Salah tandai', $this->admin);
    expect($absen->fresh()->status)->toBe(S::Terdaftar);
});
