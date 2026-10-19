<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
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
});

function dataSesi(array $override = []): array
{
    return [...['date' => '2026-12-01', 'start_time' => '08:00', 'end_time' => '11:00', 'location' => 'Aula SMPN 1', 'quota' => 25], ...$override];
}

test('staf membuat sesi untuk sekolah pilihan dengan jam dan kuota, tercatat di log', function () {
    $s = $this->service->create(dataSesi(['school_id' => $this->school->id]), $this->admin);

    expect($s->school_id)->toBe($this->school->id)
        ->and($s->created_by)->toBe($this->admin->id)
        ->and($s->date->toDateString())->toBe('2026-12-01')
        ->and(substr($s->fresh()->start_time, 0, 5))->toBe('08:00')
        ->and(substr($s->fresh()->end_time, 0, 5))->toBe('11:00')
        ->and($s->quota)->toBe(25)
        ->and(Activity::where('description', 'jadwal.dibuat')->count())->toBe(1);
    Notification::assertNothingSent();
});

test('jam, lokasi, dan kuota (naik) boleh diubah pada sesi berpendaftar tanpa notifikasi', function () {
    $schedule = makeSchedule($this->school, '2026-12-01');
    makeEntry(S::Terdaftar, $this->school, schedule: $schedule);

    $this->service->update($schedule, dataSesi(['start_time' => '09:00', 'end_time' => '12:00', 'location' => 'Lapangan Utama', 'quota' => 40]), $this->admin);

    $fresh = $schedule->fresh();
    expect(substr($fresh->start_time, 0, 5))->toBe('09:00')->and($fresh->location)->toBe('Lapangan Utama')->and($fresh->quota)->toBe(40)
        ->and(Activity::where('description', 'jadwal.diubah')->count())->toBe(1);
    Notification::assertNothingSent();
});

test('tanggal sesi berpendaftar tidak boleh diubah, sesi kosong boleh', function () {
    $berisi = makeSchedule($this->school, '2026-12-01');
    makeEntry(S::MenungguVerifikasi, $this->school, schedule: $berisi);
    $kosong = makeSchedule($this->school, '2026-12-01');

    expect(fn () => $this->service->update($berisi, dataSesi(['date' => '2026-12-05']), $this->admin))->toThrow(ValidationException::class);
    expect($berisi->fresh()->date->toDateString())->toBe('2026-12-01');

    $this->service->update($kosong, dataSesi(['date' => '2026-12-05']), $this->admin);
    expect($kosong->fresh()->date->toDateString())->toBe('2026-12-05');
});

test('kuota tidak boleh di bawah kursi yang sedang ditahan, sama dengan kursi boleh', function () {
    $schedule = makeSchedule($this->school, '2026-12-01');
    foreach ([S::Terdaftar, S::MenungguVerifikasi, S::PerluPerbaikan] as $status) {
        makeEntry($status, $this->school, schedule: $schedule);
    }
    makeEntry(S::Ditolak, $this->school, schedule: $schedule);
    makeEntry(S::Dibatalkan, $this->school, schedule: $schedule);

    try {
        $this->service->update($schedule, dataSesi(['quota' => 2]), $this->admin);
        $this->fail('Seharusnya ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors()['quota'][0])->toContain('(3)');
    }
    expect($schedule->fresh()->quota)->toBe(30);

    $this->service->update($schedule, dataSesi(['quota' => 3]), $this->admin);
    expect($schedule->fresh()->quota)->toBe(3);
});

test('ubah tanpa perubahan nyata tidak menulis log, sesi dibatalkan tidak dapat diubah', function () {
    $schedule = makeSchedule($this->school, '2026-12-01');
    $this->service->update($schedule, dataSesi(['date' => $schedule->date->toDateString(), 'end_time' => '12:00', 'location' => $schedule->location, 'quota' => 30]), $this->admin);
    expect(Activity::where('description', 'jadwal.diubah')->count())->toBe(0);

    $schedule->update(['cancelled_at' => now()]);
    expect(fn () => $this->service->update($schedule, dataSesi(), $this->admin))->toThrow(AccessRuleException::class);
});

test('batalkan sesi: kursi pra-kehadiran dibatalkan, yang sudah ditandai tidak disentuh', function () {
    $schedule = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
    $pra = [S::MenungguVerifikasi, S::PerluPerbaikan, S::Terdaftar];
    $entries = collect($pra)->map(fn (S $s) => makeEntry($s, $this->school, schedule: $schedule));
    $ditandai = makeEntry(S::SudahRekaman, $this->school, schedule: $schedule);
    $ditolak = makeEntry(S::Ditolak, $this->school, schedule: $schedule);

    $jumlah = $this->service->cancel($schedule, 'Gedung tidak tersedia.', $this->admin);

    expect($jumlah)->toBe(3)
        ->and($schedule->fresh()->isCancelled())->toBeTrue()
        ->and($entries->every(fn ($e) => $e->fresh()->status === S::Dibatalkan && $e->fresh()->note === 'Gedung tidak tersedia.'))->toBeTrue()
        ->and($entries->first()->histories()->latest('id')->first()->to_status)->toBe(S::Dibatalkan)
        ->and($ditandai->fresh()->status)->toBe(S::SudahRekaman)
        ->and($ditolak->fresh()->status)->toBe(S::Ditolak)
        ->and(Activity::where('description', 'jadwal.dibatalkan')->count())->toBe(1);
    Notification::assertNothingSent();
});

test('batalkan sesi yang sudah lewat atau sudah dibatalkan ditolak', function () {
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString());
    expect(fn () => $this->service->cancel($lewat, 'Alasan', $this->admin))->toThrow(AccessRuleException::class);

    $batal = makeSchedule($this->school);
    $this->service->cancel($batal, 'Alasan', $this->admin);
    expect(fn () => $this->service->cancel($batal, 'Alasan lagi', $this->admin))->toThrow(AccessRuleException::class);
});

test('hapus sesi tanpa entri apa pun berhasil dan tercatat; yang punya entri ditolak', function (?S $status) {
    $schedule = makeSchedule($this->school);
    if ($status) {
        makeEntry($status, $this->school, schedule: $schedule);
    }

    if ($status === null) {
        $this->service->delete($schedule, $this->admin);
        expect(Schedule::whereKey($schedule->id)->exists())->toBeFalse()
            ->and(Activity::where('description', 'jadwal.dihapus')->count())->toBe(1);

        return;
    }

    expect(fn () => $this->service->delete($schedule, $this->admin))->toThrow(AccessRuleException::class, 'batalkan sesi');
    expect(Schedule::whereKey($schedule->id)->exists())->toBeTrue();
})->with([[null], [S::Ditolak], [S::Terdaftar]]);

test('isi notifikasi penandaan memuat tanggal dan tautan sesi', function () {
    $marked = ScheduleNotification::marked(7, 2, 'Tidak hadir', '2026-12-01');
    $undone = ScheduleNotification::undone(7, 'Sudah rekaman');

    expect($marked->title())->toBe('Rekaman ditandai')
        ->and($marked->body())->toBe('2 siswa ditandai Tidak hadir pada 1 Desember 2026.')
        ->and($marked->url())->toBe('/jadwal/7')
        ->and($undone->title())->toBe('Penandaan dibatalkan')
        ->and($undone->body())->toBe('Penandaan Sudah rekaman dibatalkan; siswa kembali Terdaftar.');
});

test('sesi akan datang hanya milik sekolah pengguna, tanpa yang lewat, dengan hitungan kursi tertahan', function () {
    $milik = makeSchedule($this->school, now('Asia/Jakarta')->addDays(2)->toDateString());
    makeEntry(S::Terdaftar, $this->school, schedule: $milik);
    makeEntry(S::Ditolak, $this->school, schedule: $milik);
    makeSchedule($this->school, now('Asia/Jakarta')->subDays(2)->toDateString());
    makeSchedule(null, now('Asia/Jakarta')->addDays(3)->toDateString());

    $this->actingAs($this->op);
    $upcoming = $this->service->upcoming();
    expect($upcoming->pluck('id')->all())->toBe([$milik->id])->and($upcoming->first()->taken_count)->toBe(1);

    $this->actingAs($this->admin);
    expect($this->service->upcoming()->count())->toBe(2);
});

test('batalkan sesi membaca pendaftaran di bawah kunci baris agar tidak berebut dengan pembatalan siswa', function () {
    $schedule = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
    makeEntry(S::Terdaftar, $this->school, schedule: $schedule);
    DB::enableQueryLog();

    $this->service->cancel($schedule, 'Gedung tidak tersedia.', $this->admin);

    $terkunci = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $q) => str_contains($q, 'from `registration_students`') && str_contains($q, '`schedule_id`') && str_contains($q, 'for update')
    );
    expect($terkunci)->not->toBeEmpty();
});
