<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-jdw');
    $this->admin = makeUserWith(['jadwal.buat', 'jadwal.ubah'], roleName: 'admin-jdw');
    $this->service = app(ScheduleService::class);
    $this->schedule = makeSchedule($this->school);
});

test('penempatan memindahkan entri ke Terjadwal dengan riwayat, log, dan satu notifikasi', function () {
    $a = makeEntry(S::Terverifikasi, $this->school);
    $b = makeEntry(S::Terverifikasi, $this->school);
    $c = makeEntry(S::Terverifikasi, $this->school);

    $this->service->assign($this->schedule, [$a->id, $b->id, $c->id], $this->admin);

    foreach ([$a, $b, $c] as $e) {
        expect($e->fresh()->status)->toBe(S::Terjadwal)->and($e->fresh()->schedule_id)->toBe($this->schedule->id)
            ->and($e->histories()->count())->toBe(1);
    }
    expect(Activity::where('description', 'jadwal.penempatan')->count())->toBe(1);
    Notification::assertSentToTimes($this->op, ScheduleNotification::class, 1);
});

test('satu entri tidak sah menggagalkan seluruh penempatan', function (string $kasus) {
    $ok = makeEntry(S::Terverifikasi, $this->school);
    // Dataset memakai nama kasus (bukan closure): Pest memanggil closure dataset tanpa argumen.
    $buruk = match ($kasus) {
        'bukan Terverifikasi' => makeEntry(S::Diajukan, $this->school)->id,
        'sekolah lain' => makeEntry(S::Terverifikasi)->id,
        'id tidak ada' => 999999,
    };

    expect(fn () => $this->service->assign($this->schedule, [$ok->id, $buruk], $this->admin))->toThrow(ValidationException::class);

    expect($ok->fresh()->status)->toBe(S::Terverifikasi)->and($ok->fresh()->schedule_id)->toBeNull();
    Notification::assertNothingSent();
})->with(['bukan Terverifikasi', 'sekolah lain', 'id tidak ada']);

test('jadwal yang sudah lewat tidak dapat ditempati', function () {
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString());
    $e = makeEntry(S::Terverifikasi, $this->school);

    expect(fn () => $this->service->assign($lewat, [$e->id], $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->status)->toBe(S::Terverifikasi);
});

test('entri yang sudah dijadwalkan tidak dapat masuk jadwal kedua', function () {
    $e = makeEntry(S::Terverifikasi, $this->school);
    $kedua = makeSchedule($this->school);

    $this->service->assign($this->schedule, [$e->id], $this->admin);

    expect(fn () => $this->service->assign($kedua, [$e->id], $this->admin))->toThrow(ValidationException::class);
    expect($e->fresh()->schedule_id)->toBe($this->schedule->id);
});

test('keluarkan dari jadwal mengembalikan ke Terverifikasi dan dapat dijadwalkan ulang', function () {
    $e = makeEntry(S::Terverifikasi, $this->school);
    $lain = makeSchedule($this->school);
    $this->service->assign($this->schedule, [$e->id], $this->admin);

    $this->service->unassign($e->fresh(), $this->admin);
    expect($e->fresh()->status)->toBe(S::Terverifikasi)->and($e->fresh()->schedule_id)->toBeNull();

    $this->service->assign($lain, [$e->id], $this->admin);
    expect($e->fresh()->schedule_id)->toBe($lain->id);
});

test('hanya entri Terjadwal yang dapat dikeluarkan', function () {
    $final = makeScheduledEntry(S::SudahRekaman, $this->schedule);

    expect(fn () => $this->service->unassign($final, $this->admin))->toThrow(AccessRuleException::class);
    expect($final->fresh()->schedule_id)->toBe($this->schedule->id);
});
