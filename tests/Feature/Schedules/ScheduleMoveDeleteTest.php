<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Audit\Services\ActivityLogService;
use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-mv');
    $this->admin = makeUserWith(['jadwal.ubah'], roleName: 'admin-mv');
    $this->service = app(ScheduleService::class);
    $this->besok = now('Asia/Jakarta')->addDay()->toDateString();
    $this->lusa = now('Asia/Jakarta')->addDays(2)->toDateString();
});

test('jadwal kosong dihapus dan tercatat di log', function () {
    $jadwal = makeSchedule($this->school, $this->besok);

    $this->service->delete($jadwal, $this->admin);

    expect(Schedule::count())->toBe(0)
        ->and(Activity::where('description', 'jadwal.dihapus')->count())->toBe(1);
});

test('baris log jadwal yang sudah dihapus tetap tampil dengan label #id', function () {
    $jadwal = makeSchedule($this->school, $this->besok);
    $this->service->delete($jadwal, $this->admin);

    $baris = collect(app(ActivityLogService::class)->list()->items())->firstWhere('action', 'Jadwal dihapus');

    expect($baris['subject']['label'])->toBe('#'.$jadwal->id);
});

test('jadwal yang masih memiliki entri, termasuk yang sudah ditandai, tidak dapat dihapus', function (string $kasus) {
    $jadwal = makeSchedule($this->school, $this->besok);
    match ($kasus) {
        'terjadwal' => makeScheduledEntry(S::Terjadwal, $jadwal),
        'sudah ditandai' => makeScheduledEntry(S::SudahRekaman, $jadwal),
    };

    expect(fn () => $this->service->delete($jadwal, $this->admin))->toThrow(AccessRuleException::class);

    expect(Schedule::count())->toBe(1)->and(Activity::where('description', 'jadwal.dihapus')->count())->toBe(0);
})->with(['terjadwal', 'sudah ditandai']);

test('pindah memindahkan entri atomik dengan dua riwayat, log, dan satu notifikasi ke tujuan', function () {
    $asal = makeSchedule($this->school, $this->besok);
    $tujuan = makeSchedule($this->school, $this->lusa);
    $e = makeScheduledEntry(S::Terjadwal, $asal);

    $this->service->move($e, $tujuan, $this->admin);

    expect($e->fresh()->status)->toBe(S::Terjadwal)->and($e->fresh()->schedule_id)->toBe($tujuan->id)
        ->and($e->histories()->count())->toBe(2)
        ->and(Activity::where('description', 'jadwal.dipindahkan')->count())->toBe(1);
    Notification::assertSentToTimes($this->op, ScheduleNotification::class, 1);
    Notification::assertSentTo($this->op, ScheduleNotification::class, fn (ScheduleNotification $n) => $n->url() === "/jadwal/{$tujuan->id}");
});

test('pindah ke jadwal hari ini dan dari jadwal lewat yang belum ditandai diperbolehkan', function () {
    $hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString());
    $e = makeScheduledEntry(S::Terjadwal, $lewat);

    $this->service->move($e, $hariIni, $this->admin);

    expect($e->fresh()->schedule_id)->toBe($hariIni->id);
});

test('pindah yang tidak sah ditolak tanpa mengubah entri', function (string $kasus) {
    $asal = makeSchedule($this->school, $this->besok);
    $e = makeScheduledEntry(S::Terjadwal, $asal);
    [$entri, $tujuan] = match ($kasus) {
        'jadwal sama' => [$e, $asal],
        'sekolah lain' => [$e, makeSchedule(null, $this->lusa)],
        'jadwal lewat' => [$e, makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString())],
        'bukan Terjadwal' => [makeEntry(S::Terverifikasi, $this->school), makeSchedule($this->school, $this->lusa)],
    };

    expect(fn () => $this->service->move($entri, $tujuan, $this->admin))->toThrow(AccessRuleException::class);

    expect($e->fresh()->schedule_id)->toBe($asal->id)->and($e->fresh()->status)->toBe(S::Terjadwal)
        ->and($e->histories()->count())->toBe(0);
    Notification::assertNothingSent();
})->with(['jadwal sama', 'sekolah lain', 'jadwal lewat', 'bukan Terjadwal']);

test('jadwal tujuan: sekolah sama, hari ini atau setelahnya, tanpa jadwal sendiri, tanggal naik', function () {
    $sendiri = makeSchedule($this->school, $this->besok);
    $lusa = makeSchedule($this->school, $this->lusa);
    $hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
    makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString());
    makeSchedule(null, $this->besok);

    $ids = $this->service->targets($sendiri)->pluck('id')->all();

    expect($ids)->toBe([$hariIni->id, $lusa->id]);
});
