<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/** @param list<S> $statuses */
function makeEntries(array $statuses, ?School $school = null): void
{
    foreach ($statuses as $status) {
        makeEntry($status, $school ?? School::factory()->create());
    }
}

test('staf melihat kartu dan aksi se-kecamatan', function () {
    makeEntries([S::MenungguVerifikasi, S::MenungguVerifikasi, S::Terdaftar, S::SudahRekaman, S::PerluPerbaikan, S::Ditolak]);
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.lihat', 'jadwal.buat'], roleName: 'staf-beranda');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('summary', 3)
        ->where('summary.0.label', 'Menunggu verifikasi')->where('summary.0.value', 2)
        ->where('summary.1.label', 'Terdaftar')->where('summary.1.value', 1)
        ->where('summary.2.label', 'Sudah rekaman')->where('summary.2.value', 1)
        ->has('actions', 1)
        ->where('actions.0.title', '2 pendaftaran menunggu verifikasi')->where('actions.0.href', '/verifications'));
});

test('operator hanya melihat angka dan tugas sekolahnya', function () {
    $sekolah = School::factory()->create();
    makeEntries([S::PerluPerbaikan, S::Terdaftar, S::SudahRekaman], $sekolah);
    makeEntries([S::MenungguVerifikasi, S::MenungguVerifikasi, S::PerluPerbaikan, S::Terdaftar]);
    $op = makeUserWith(['jadwal.lihat', 'siswa.lihat'], $sekolah, true, 'op-beranda-2');

    $this->actingAs($op)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('summary', 1)
        ->where('summary.0.label', 'Sudah rekaman')->where('summary.0.value', 1)
        ->has('actions', 1)
        ->where('actions.0.title', '1 siswa terdaftar')->where('actions.0.href', '/schedules'));
});

test('kartu dan aksi hilang bila izin modulnya tidak ada', function () {
    makeEntries([S::MenungguVerifikasi, S::Terdaftar]);

    $this->actingAs(makeUserWith([], roleName: 'tanpa-izin'))->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p->has('summary', 0)->has('actions', 0));

    $this->actingAs(makeUserWith(['jadwal.lihat'], roleName: 'hanya-jadwal'))->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p
            ->has('summary', 2)->where('summary.0.label', 'Terdaftar')->where('summary.1.label', 'Sudah rekaman')
            ->has('actions', 1)->where('actions.0.title', '1 siswa terdaftar'));
});

test('aksi dengan hitungan 0 disembunyikan tetapi kartu tetap tampil dengan angka 0', function () {
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.buat', 'jadwal.lihat'], roleName: 'staf-kosong');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('actions', 0)->where('summary.0.value', 0)->has('summary', 3));
});

test('kpi staf menghitung pendaftaran yang masih relevan dan persentasenya', function () {
    makeEntries([S::MenungguVerifikasi, S::MenungguVerifikasi, S::PerluPerbaikan, S::Terdaftar, S::SudahRekaman, S::Ditolak, S::Dibatalkan]);
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.lihat'], roleName: 'staf-kpi-nilai');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('kpi', 5)
        ->where('kpi.0.key', 'total_students')->where('kpi.0.value', 5)
        ->where('kpi.1.key', 'registered')->where('kpi.1.value', 1)->where('kpi.1.percentage', 20)
        ->where('kpi.2.key', 'recorded')->where('kpi.2.value', 1)
        ->where('kpi.3.key', 'pending')->where('kpi.3.value', 3)->where('kpi.3.percentage', 60)
        ->where('kpi.4.key', 'absent')->where('kpi.4.value', 0));
});

test('dashboard menyediakan payload kpi 5 kartu, quick actions, dan grafik untuk staf', function () {
    makeEntries([S::MenungguVerifikasi, S::Terdaftar, S::SudahRekaman]);
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.lihat', 'jadwal.buat', 'laporan.lihat'], roleName: 'staf-kpi');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('kpi', 5)
        ->has('quick_actions')
        ->where('charts.recording_status.labels', ['Sudah Rekam', 'Terdaftar', 'Menunggu & Perbaikan'])
        ->where('charts.recording_status.series', [1, 1, 1])
        ->has('charts.school_recap')
        ->has('charts.monthly_trend')
        ->has('schedules_table')
        ->has('calendar_events')
    );
});

test('dashboard menyediakan kpi dan grafik khusus sekolah untuk operator', function () {
    $sekolah = School::factory()->create();
    makeEntries([S::Terdaftar, S::SudahRekaman], $sekolah);
    $op = makeUserWith(['jadwal.lihat', 'siswa.lihat'], $sekolah, true, 'op-kpi');

    $this->actingAs($op)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('kpi', 5)
        ->where('kpi.3.key', 'registered')->where('kpi.3.value', 1)
        ->where('kpi.4.key', 'recorded')->where('kpi.4.value', 1)
        ->has('quick_actions')
        ->has('charts.recording_status')
        ->has('charts.school_recap')
        ->has('charts.monthly_trend')
    );
});

test('semua tautan aksi dan aksi cepat beranda mengarah ke rute yang ada', function () {
    makeEntries([S::MenungguVerifikasi, S::Terdaftar]);
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.lihat', 'jadwal.buat', 'rekaman.lihat', 'laporan.lihat', 'siswa.lihat'], roleName: 'staf-tautan');
    $props = $this->actingAs($staf)->get('/dashboard')->viewData('page')['props'];

    $hrefs = collect($props['actions'])->pluck('href')->merge(collect($props['quick_actions'])->pluck('href'));
    expect($hrefs)->not->toBeEmpty();
    foreach ($hrefs as $href) {
        expect(fn () => Route::getRoutes()->match(Request::create($href, 'GET')))->not->toThrow(Throwable::class);
    }
});

test('tren bulanan Sudah Rekam mengikuti bulan tanggal sesi, bukan updated_at', function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    $sekolah = School::factory()->create();
    $okt = makeEntry(S::SudahRekaman, $sekolah, schedule: makeSchedule($sekolah, '2026-10-12'));
    $okt->forceFill(['updated_at' => '2026-03-05 10:00:00'])->saveQuietly();
    makeEntry(S::SudahRekaman, $sekolah, schedule: makeSchedule($sekolah, '2026-02-02'));
    makeEntry(S::SudahRekaman, $sekolah, schedule: makeSchedule($sekolah, '2025-10-12'));
    makeEntry(S::Terdaftar, $sekolah, schedule: makeSchedule($sekolah, '2026-10-13'));
    $staf = makeUserWith(['jadwal.lihat'], roleName: 'staf-tren');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('charts.monthly_trend.series.1.name', 'Sudah Rekam')
        ->where('charts.monthly_trend.series.1.data', [0, 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0])
        ->where('charts.monthly_trend.series.0.name', 'Terdaftar'));

    Carbon\Carbon::setTestNow();
});

test('tren bulanan Terdaftar menghitung pendaftaran dibuat pada tahun berjalan per bulan', function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    $sekolah = School::factory()->create();
    $a = makeEntry(S::Terdaftar, $sekolah);
    $a->forceFill(['created_at' => '2026-01-10 10:00:00'])->saveQuietly();
    $b = makeEntry(S::Ditolak, $sekolah);
    $b->forceFill(['created_at' => '2026-01-20 10:00:00'])->saveQuietly();
    $lama = makeEntry(S::Terdaftar, $sekolah);
    $lama->forceFill(['created_at' => '2025-01-10 10:00:00'])->saveQuietly();
    $staf = makeUserWith(['jadwal.lihat'], roleName: 'staf-tren-daftar');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('charts.monthly_trend.series.0.data.0', 2)
        ->where('charts.monthly_trend.series.0.data.9', 0));

    Carbon\Carbon::setTestNow();
});

test('aksi cepat: Pindai Tiket bagi pemegang rekaman.pindai, tanpa Input Hasil Perekaman', function () {
    $admin = makeUserWith(['jadwal.lihat', 'rekaman.pindai'], roleName: 'admin-aksi');
    $tanpa = makeUserWith(['jadwal.lihat'], roleName: 'staf-tanpa-pindai');

    $dengan = collect($this->actingAs($admin)->get('/dashboard')->viewData('page')['props']['quick_actions']);
    $tanpaAksi = collect($this->actingAs($tanpa)->get('/dashboard')->viewData('page')['props']['quick_actions']);

    expect($dengan->firstWhere('label', 'Pindai Tiket'))->toMatchArray(['href' => '/schedules', 'icon' => 'scan-line'])
        ->and($dengan->pluck('label'))->not->toContain('Input Hasil Perekaman')
        ->and($tanpaAksi->pluck('label'))->not->toContain('Pindai Tiket')->not->toContain('Input Hasil Perekaman');
});
