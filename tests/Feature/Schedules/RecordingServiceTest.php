<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Schedules\Services\RecordingService;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

afterEach(fn () => Carbon::setTestNow());

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['rekaman.tandai'], roleName: 'admin-rek');
    $this->service = app(RecordingService::class);
    $this->hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
});

test('penandaan massal mengubah status, mencatat riwayat, catatan, dan satu log', function (S $status) {
    $a = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $b = makeScheduledEntry(S::Terjadwal, $this->hariIni);

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
    $e = makeScheduledEntry(S::Terjadwal, $besok);

    expect(fn () => $this->service->mark($besok, [$e->id], S::SudahRekaman, null, $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->status)->toBe(S::Terjadwal);
});

test('pukul 03.00 WIB sudah dihitung hari kegiatan walau tanggal UTC masih kemarin', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 20:00:00', 'UTC'));
    $jadwal = makeSchedule($this->school, '2026-10-09');
    $e = makeScheduledEntry(S::Terjadwal, $jadwal);

    $this->service->mark($jadwal, [$e->id], S::SudahRekaman, null, $this->admin);

    expect($e->fresh()->status)->toBe(S::SudahRekaman);
});

test('satu entri tidak sah menggagalkan seluruh penandaan', function (string $kasus) {
    $ok = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $buruk = match ($kasus) {
        'sudah berstatus akhir' => makeScheduledEntry(S::SudahRekaman, $this->hariIni),
        'milik jadwal lain' => makeScheduledEntry(S::Terjadwal, makeSchedule($this->school, now('Asia/Jakarta')->toDateString())),
    };

    expect(fn () => $this->service->mark($this->hariIni, [$ok->id, $buruk->id], S::SudahRekaman, null, $this->admin))
        ->toThrow(ValidationException::class);

    expect($ok->fresh()->status)->toBe(S::Terjadwal)->and($ok->histories()->count())->toBe(0);
})->with(['sudah berstatus akhir', 'milik jadwal lain']);

test('status penandaan harus salah satu dari tiga status akhir', function () {
    $e = makeScheduledEntry(S::Terjadwal, $this->hariIni);

    expect(fn () => $this->service->mark($this->hariIni, [$e->id], S::Diajukan, null, $this->admin))->toThrow(AccessRuleException::class);
    expect(fn () => $this->service->mark($this->hariIni, [$e->id], S::Terverifikasi, null, $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->schedule_id)->toBe($this->hariIni->id);
});

test('Tidak hadir dapat didaftarkan lagi, Sudah rekaman tidak; kunci data ikut terbuka', function () {
    makeUserWith(['verifikasi.putuskan'], roleName: 'petugas-rek');
    $op = makeUserWith(['pendaftaran.buat', 'pendaftaran.ajukan'], $this->school, true, 'op-rek');
    $absen = eligibleStudent($this->school);
    $hadir = eligibleStudent($this->school);
    $e1 = makeEntry(S::Terjadwal, $this->school, $absen);
    $e2 = makeEntry(S::Terjadwal, $this->school, $hadir);
    $e1->update(['schedule_id' => $this->hariIni->id]);
    $e2->update(['schedule_id' => $this->hariIni->id]);
    expect($absen->isLocked())->toBeTrue();

    $this->service->mark($this->hariIni, [$e1->id], S::TidakHadir, null, $this->admin);
    $this->service->mark($this->hariIni, [$e2->id], S::SudahRekaman, null, $this->admin);
    expect($absen->isLocked())->toBeFalse();

    $registrations = app(RegistrationService::class);
    $baru = $registrations->createDraft(['title' => 'Gelombang 2', 'student_ids' => [$absen->id]], $op);
    $registrations->submit($baru, $op);
    expect($baru->entries()->first()->status)->toBe(S::Diajukan);

    $ditolak = $registrations->createDraft(['title' => 'Gelombang 3', 'student_ids' => [$hadir->id]], $op);
    expect(fn () => $registrations->submit($ditolak, $op))->toThrow(ValidationException::class);
});
