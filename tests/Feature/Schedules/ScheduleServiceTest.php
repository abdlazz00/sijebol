<?php

use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-jdw');
    $this->admin = makeUserWith(['jadwal.buat', 'jadwal.ubah'], roleName: 'admin-jdw');
    $this->service = app(ScheduleService::class);
});

test('staf membuat jadwal untuk sekolah pilihan dan tercatat di log', function () {
    $s = $this->service->create(['school_id' => $this->school->id, 'date' => '2026-12-01', 'location' => 'Aula SMPN 1'], $this->admin);

    expect($s->school_id)->toBe($this->school->id)
        ->and($s->created_by)->toBe($this->admin->id)
        ->and($s->date->toDateString())->toBe('2026-12-01')
        ->and(Activity::where('description', 'jadwal.dibuat')->count())->toBe(1);
    Notification::assertNothingSent();
});

test('ubah tanggal atau lokasi memberi tahu operator sekolah bila sudah ada siswa', function (array $change) {
    $schedule = makeSchedule($this->school, '2026-12-01');
    makeScheduledEntry(schedule: $schedule);

    $this->service->update($schedule, $change + ['date' => '2026-12-01', 'location' => $schedule->location], $this->admin);

    expect(Activity::where('description', 'jadwal.diubah')->count())->toBe(1);
    Notification::assertSentToTimes($this->op, ScheduleNotification::class, 1);
})->with([[['date' => '2026-12-05']], [['location' => 'Lapangan Utama']]]);

test('ubah tanpa siswa atau tanpa perubahan nyata tidak memberi notifikasi', function () {
    $kosong = makeSchedule($this->school, '2026-12-01');
    $this->service->update($kosong, ['date' => '2026-12-02', 'location' => 'Baru'], $this->admin);

    $berisi = makeSchedule($this->school, '2026-12-01');
    makeScheduledEntry(schedule: $berisi);
    $this->service->update($berisi, ['date' => '2026-12-01', 'location' => $berisi->location], $this->admin);

    Notification::assertNothingSent();
    expect(Activity::where('description', 'jadwal.diubah')->count())->toBe(1);
});

test('isi notifikasi memuat tanggal dan lokasi', function () {
    $assigned = ScheduleNotification::assigned(7, 3, '2026-12-01', 'Aula SMPN 1');
    $changed = ScheduleNotification::changed(7, '2026-12-05', 'Lapangan');

    expect($assigned->title())->toBe('Jadwal ditetapkan')
        ->and($assigned->body())->toBe('3 siswa dijadwalkan pada 1 Desember 2026 di Aula SMPN 1.')
        ->and($assigned->url())->toBe('/jadwal/7')
        ->and($changed->title())->toBe('Jadwal diubah')
        ->and($changed->body())->toBe('Jadwal sekarang pada 5 Desember 2026 di Lapangan.');
});

test('jadwal akan datang hanya milik sekolah pengguna dan tanpa yang lewat', function () {
    $milik = makeSchedule($this->school, now('Asia/Jakarta')->addDays(2)->toDateString());
    makeSchedule($this->school, now('Asia/Jakarta')->subDays(2)->toDateString());
    makeSchedule(null, now('Asia/Jakarta')->addDays(3)->toDateString());

    $this->actingAs($this->op);
    expect($this->service->upcoming()->pluck('id')->all())->toBe([$milik->id]);

    $this->actingAs($this->admin);
    expect($this->service->upcoming()->count())->toBe(2);
});
