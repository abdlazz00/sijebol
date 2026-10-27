<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Services\RecordingService;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));
    $this->school = School::factory()->create(['name' => 'SMPN 1 Sungai Beduk']);
    $this->sesi = makeSchedule($this->school, '2026-10-20');
    [$this->akun, $this->siswa] = siswaLengkap($this->school);
    $this->siswa->update(['name' => 'Budi Santoso']);
});

afterEach(fn () => Carbon::setTestNow());

function tiketTerdaftar(object $t, S $status = S::Terdaftar): object
{
    $entry = makeEntry($status, $t->school, $t->siswa, $t->sesi);
    $entry->update(['ticket_code' => 'KODE'.str_repeat('A', 28)]);

    return $entry;
}

test('tiket Terdaftar memuat kode, nama, sekolah, dan sesi', function () {
    $entry = tiketTerdaftar($this);

    $this->actingAs($this->akun)->get("/me/tickets/{$entry->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Saya/Tiket')
        ->where('ticket.entry_id', $entry->id)->where('ticket.status', 'terdaftar')
        ->where('ticket.code', 'KODE'.str_repeat('A', 28))
        ->where('ticket.student_name', 'Budi Santoso')->where('ticket.school', 'SMPN 1 Sungai Beduk')
        ->where('ticket.schedule.id', $this->sesi->id)->where('ticket.schedule.date', '2026-10-20')
        ->where('ticket.schedule.start_time', '08:00')->where('ticket.schedule.end_time', '12:00')
        ->where('ticket.schedule.location', $this->sesi->location));
});

test('tiket Sudah rekaman tampil tanpa kode', function () {
    $entry = tiketTerdaftar($this, S::SudahRekaman);

    $this->actingAs($this->akun)->get("/me/tickets/{$entry->id}")->assertInertia(fn (Assert $p) => $p
        ->where('ticket.status', 'sudah_rekaman')->where('ticket.code', null));
});

test('tiket 404 untuk status yang tidak memberi tiket', function (S $status) {
    $entry = tiketTerdaftar($this, $status);

    $this->actingAs($this->akun)->get("/me/tickets/{$entry->id}")->assertNotFound();
})->with([
    [S::MenungguVerifikasi], [S::PerluPerbaikan], [S::Ditolak], [S::Dibatalkan], [S::Kedaluwarsa], [S::TidakHadir], [S::BelumRekaman],
]);

test('tiket siswa lain, admin, dan tamu tidak dapat membukanya', function () {
    $entry = tiketTerdaftar($this);
    [$b] = siswaLengkap($this->school);
    $admin = makeUserWith(['pendaftaran.lihat'], roleName: 'admin-tiket');

    $this->actingAs($b)->get("/me/tickets/{$entry->id}")->assertNotFound();
    $this->actingAs($admin)->get("/me/tickets/{$entry->id}")->assertNotFound();

    auth()->logout();
    $this->get("/me/tickets/{$entry->id}")->assertRedirect('/login');
});

test('kode tiket tidak bocor ke halaman lain', function () {
    $entry = tiketTerdaftar($this);
    $this->actingAs($this->akun);

    foreach (['/me/sessions', '/me'] as $url) {
        expect($this->get($url)->getContent())->not->toContain('KODE'.str_repeat('A', 28));
    }

    [$lain] = siswaLengkap($this->school);
    $this->actingAs($lain);
    expect($this->get('/dashboard')->getContent())->not->toContain('KODE'.str_repeat('A', 28));

    expect($entry->exists)->toBeTrue();
});

test('kode tetap sama setelah penandaan dibatalkan', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 09:00:00', 'Asia/Jakarta'));
    $entry = tiketTerdaftar($this);
    $admin = makeUserWith(['rekaman.tandai', 'jadwal.lihat'], roleName: 'admin-tiket-2');
    $service = app(RecordingService::class);

    $service->mark($this->sesi, [$entry->id], S::SudahRekaman, null, $admin);
    $service->undo($entry->fresh(), 'Salah tandai', $admin);

    expect($entry->fresh()->status)->toBe(S::Terdaftar)->and($entry->fresh()->ticket_code)->toBe('KODE'.str_repeat('A', 28));
});

test('halaman sesi memuat tenggat perbaikan dan tidak mengizinkan ajukan ulang setelahnya', function () {
    $entry = makeEntry(S::PerluPerbaikan, $this->school, $this->siswa, $this->sesi);
    $entry->update(['fix_deadline_at' => '2026-10-02 10:00:00']);
    $this->actingAs($this->akun);

    $this->get('/me/sessions')->assertInertia(fn (Assert $p) => $p
        ->where('entry.fix_deadline', fn ($v) => str_starts_with($v, '2026-10-02'))
        ->where('entry.can_resubmit', true));

    Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00', 'Asia/Jakarta'));
    $this->get('/me/sessions')->assertInertia(fn (Assert $p) => $p->where('entry.can_resubmit', false));
});

test('tiket menandai KTP-el langsung menurut tanggal sesi tiket', function (string $lahir, bool $langsung) {
    $this->siswa->update(['birth_date' => $lahir]);
    $entry = tiketTerdaftar($this);

    $this->actingAs($this->akun)->get("/me/tickets/{$entry->id}")->assertInertia(fn (Assert $p) => $p->where('ticket.ktp_langsung', $langsung));
})->with([
    'genap 17 tahun pada tanggal sesi' => ['2009-10-20', false],
    'lewat sehari dari ulang tahun ke-17' => ['2009-10-19', true],
    'umur 16 tahun' => ['2010-10-19', false],
]);
