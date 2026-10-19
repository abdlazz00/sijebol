<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));
    $this->school = School::factory()->create();
    $this->sesi = makeSchedule($this->school, '2026-10-20');
    [$this->akun, $this->siswa] = siswaLengkap($this->school);
});

afterEach(fn () => Carbon::setTestNow());

test('halaman sesi memuat sesi terbuka sekolah siswa, urut tanggal, dengan kursi dan kelayakan', function () {
    $lebihAwal = makeSchedule($this->school, '2026-10-10');
    $penuh = makeSchedule($this->school, '2026-10-30');
    $penuh->update(['quota' => 1]);
    makeEntry(S::Terdaftar, $this->school, schedule: $penuh);
    makeSchedule($this->school, '2026-10-02');                       // ditutup (H-1)
    makeSchedule($this->school, '2026-10-25')->update(['cancelled_at' => now()]);
    makeSchedule(School::factory()->create(), '2026-10-22');         // sekolah lain

    $this->actingAs($this->akun)->get('/saya/sesi')->assertInertia(fn (Assert $p) => $p
        ->component('Saya/Sesi')
        ->where('ready', true)->where('close_days', 2)->where('entry', null)
        ->has('schedules', 3)
        ->where('schedules.0.id', $lebihAwal->id)->where('schedules.1.id', $this->sesi->id)->where('schedules.2.id', $penuh->id)
        ->where('schedules.1.start_time', '08:00')->where('schedules.1.end_time', '12:00')
        ->where('schedules.1.quota', 30)->where('schedules.1.taken', 0)->where('schedules.1.full', false)
        ->where('schedules.1.eligible', true)->where('schedules.1.reasons', [])
        ->where('schedules.2.full', true)->where('schedules.2.taken', 1));
});

test('kelayakan dihitung per sesi: umur 16 pada tanggal sesi', function () {
    $this->siswa->update(['birth_date' => '2010-10-25']);
    $nanti = makeSchedule($this->school, '2026-10-27');

    $this->actingAs($this->akun)->get('/saya/sesi')->assertInertia(fn (Assert $p) => $p
        ->where('schedules.0.id', $this->sesi->id)->where('schedules.0.eligible', false)
        ->where('schedules.0.reasons.0', fn ($r) => str_contains($r, 'Umur minimal 16 tahun'))
        ->where('schedules.1.id', $nanti->id)->where('schedules.1.eligible', true));
});

test('tanpa baris siswa, halaman belum siap dan tanpa daftar sesi', function () {
    $akun = makeUserWith(['pendaftaran.lihat', 'pendaftaran.buat'], $this->school, true, 'siswa');

    $this->actingAs($akun)->get('/saya/sesi')->assertInertia(fn (Assert $p) => $p
        ->where('ready', false)->where('entry', null)->has('schedules', 0));
});

test('dengan pendaftaran aktif daftar sesi kosong dan entri memuat aksi yang tersedia', function (S $status, bool $batal, bool $ulang) {
    makeEntry($status, $this->school, $this->siswa, $this->sesi);
    RegistrationStudent::query()->latest('id')->first()->update(['note' => 'Foto KK buram']);

    $this->actingAs($this->akun)->get('/saya/sesi')->assertInertia(fn (Assert $p) => $p
        ->has('schedules', 0)
        ->where('entry.status', $status->value)->where('entry.note', 'Foto KK buram')
        ->where('entry.can_cancel', $batal)->where('entry.can_resubmit', $ulang)
        ->where('entry.schedule.id', $this->sesi->id)->where('entry.schedule.date', '2026-10-20')
        ->where('entry.schedule.start_time', '08:00'));
})->with([
    [S::MenungguVerifikasi, true, false], [S::PerluPerbaikan, true, true], [S::Terdaftar, true, false], [S::SudahRekaman, false, false],
]);

test('pendaftaran yang sudah berakhir tetap tampil sebagai ringkasan dan sesi dapat dipilih lagi', function (S $status) {
    makeEntry($status, $this->school, $this->siswa, $this->sesi);

    $this->actingAs($this->akun)->get('/saya/sesi')->assertInertia(fn (Assert $p) => $p
        ->where('entry.status', $status->value)->where('entry.can_cancel', false)->has('schedules', 1));
})->with([[S::Ditolak], [S::Dibatalkan]]);

test('aksi pembatalan dan ajukan ulang hilang pada hari sesi', function () {
    $hariH = makeSchedule($this->school, '2026-10-01');
    makeEntry(S::PerluPerbaikan, $this->school, $this->siswa, $hariH);

    $this->actingAs($this->akun)->get('/saya/sesi')->assertInertia(fn (Assert $p) => $p
        ->where('entry.can_cancel', false)->where('entry.can_resubmit', false));
});

test('mendaftar: sukses mengalihkan dengan pesan, gagal memberi galat tanpa entri', function () {
    $this->actingAs($this->akun)->post("/saya/sesi/{$this->sesi->id}/daftar")
        ->assertRedirect('/saya/sesi')->assertSessionHas('success');
    expect(RegistrationStudent::count())->toBe(1);

    $penuh = makeSchedule($this->school, '2026-10-21');
    $penuh->update(['quota' => 1]);
    makeEntry(S::Terdaftar, $this->school, schedule: $penuh);
    [$b] = siswaLengkap($this->school);

    $this->actingAs($b)->post("/saya/sesi/{$penuh->id}/daftar")->assertSessionHas('error', 'Kuota sesi ini sudah penuh.');
    expect(RegistrationStudent::withoutGlobalScopes()->where('schedule_id', $penuh->id)->count())->toBe(1);
});

test('membatalkan dan mengajukan ulang lewat HTTP', function () {
    $entry = makeEntry(S::PerluPerbaikan, $this->school, $this->siswa, $this->sesi);
    $this->actingAs($this->akun);

    $this->post("/saya/pendaftaran/{$entry->id}/ajukan-ulang")->assertRedirect('/saya/sesi')->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe(S::MenungguVerifikasi);

    $this->post("/saya/pendaftaran/{$entry->id}/batal")->assertRedirect('/saya/sesi')->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe(S::Dibatalkan);

    $this->post("/saya/pendaftaran/{$entry->id}/batal")->assertSessionHas('error');
});

test('siswa lain satu sekolah tidak dapat membatalkan atau mengajukan ulang pendaftaran siswa ini', function () {
    $entry = makeEntry(S::PerluPerbaikan, $this->school, $this->siswa, $this->sesi);
    [$b] = siswaLengkap($this->school);
    $this->actingAs($b);

    $this->post("/saya/pendaftaran/{$entry->id}/batal")->assertNotFound();
    $this->post("/saya/pendaftaran/{$entry->id}/ajukan-ulang")->assertNotFound();
    expect($entry->fresh()->status)->toBe(S::PerluPerbaikan);
});

test('sesi sekolah lain tidak dapat didaftar', function () {
    $asing = makeSchedule(School::factory()->create(), '2026-10-22');

    $this->actingAs($this->akun)->post("/saya/sesi/{$asing->id}/daftar")->assertNotFound();
    expect(RegistrationStudent::withoutGlobalScopes()->count())->toBe(0);
});

test('akun non-siswa mendapat 404 walau memegang izin, tanpa izin 403, tamu diarahkan login', function () {
    $admin = makeUserWith(['pendaftaran.lihat', 'pendaftaran.buat'], roleName: 'admin-sesi');
    $operator = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-sesi');

    $this->actingAs($admin)->get('/saya/sesi')->assertNotFound();
    $this->post("/saya/sesi/{$this->sesi->id}/daftar")->assertNotFound();
    $this->actingAs($operator)->get('/saya/sesi')->assertForbidden();

    auth()->logout();
    $this->get('/saya/sesi')->assertRedirect('/login');
});

test('rute tulis dibatasi laju', function () {
    $daftar = Route::getRoutes()->getByName('saya.sesi.daftar');
    $batal = Route::getRoutes()->getByName('saya.pendaftaran.batal');

    expect($daftar->gatherMiddleware())->toContain('throttle:10,1')
        ->and($batal->gatherMiddleware())->toContain('throttle:30,1');
});
