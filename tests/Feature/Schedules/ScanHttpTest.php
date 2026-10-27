<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 08:30:00', 'Asia/Jakarta'));
    $this->school = School::factory()->create();
    $this->sesi = makeSchedule($this->school, '2026-10-20');
    $this->petugas = makeUserWith(['rekaman.pindai'], roleName: 'petugas-pindai-http');
    $this->url = "/schedules/{$this->sesi->id}/scan";
});

afterEach(fn () => Carbon::setTestNow());

function tiketHttp(object $t, S $status = S::Terdaftar, string $kode = 'KODE-HTTP-RAHASIA'): object
{
    $entry = makeEntry($status, $t->school, schedule: $t->sesi);
    $entry->update(['ticket_code' => $kode]);

    return $entry;
}

test('halaman pindai memuat sesi, hitungan, dan jendela scan', function () {
    tiketHttp($this, kode: 'A1');
    tiketHttp($this, S::SudahRekaman, 'A2');
    tiketHttp($this, S::SudahRekaman, 'A3');

    $this->actingAs($this->petugas)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->component('Jadwal/Pindai')
        ->where('schedule.id', $this->sesi->id)->where('schedule.cancelled', false)
        ->where('counts', ['waiting' => 1, 'done' => 2])
        ->where('window.from', '2026-10-20T07:00:00+07:00')->where('window.to', '2026-10-20T13:00:00+07:00'));
});

test('pindai sukses menjawab JSON ok tanpa kode tiket maupun NIK', function () {
    $entry = tiketHttp($this);
    tiketHttp($this, kode: 'LAIN');
    $entry->student->update(['birth_date' => '2009-10-19']);

    $res = $this->actingAs($this->petugas)->postJson($this->url, ['code' => 'KODE-HTTP-RAHASIA']);

    $res->assertOk()->assertJson([
        'status' => 'ok',
        'message' => 'Tercatat sudah rekaman.',
        'student' => ['name' => $entry->student->name, 'nisn' => $entry->student->nisn, 'ktp_langsung' => true],
        'counts' => ['waiting' => 1, 'done' => 1],
    ]);
    expect($res->json('scanned_at'))->toBe('2026-10-20T08:30:00+07:00')
        ->and($res->getContent())->not->toContain('KODE-HTTP-RAHASIA')->not->toContain($entry->student->nik);
});

test('pindai ditolak menjawab 422 rejected dengan pesan', function () {
    $this->actingAs($this->petugas)->postJson($this->url, ['code' => 'TIDAK-ADA'])
        ->assertStatus(422)->assertJson(['status' => 'rejected', 'message' => 'Kode tiket tidak dikenal.']);

    tiketHttp($this, S::MenungguVerifikasi, 'MENUNGGU');
    $this->postJson($this->url, ['code' => 'MENUNGGU'])
        ->assertStatus(422)->assertJson(['status' => 'rejected', 'message' => 'Tiket tidak berlaku (status: Menunggu verifikasi).']);
});

test('kode kosong atau terlalu panjang ditolak validasi', function () {
    $this->actingAs($this->petugas);

    $this->postJson($this->url, ['code' => ''])->assertStatus(422)->assertJsonValidationErrors('code');
    $this->postJson($this->url, [])->assertStatus(422)->assertJsonValidationErrors('code');
    $this->postJson($this->url, ['code' => str_repeat('A', 65)])->assertStatus(422)->assertJsonValidationErrors('code');
});

test('hanya pemegang rekaman.pindai: penanda rekaman, operator sekolah, dan siswa ditolak', function () {
    tiketHttp($this);
    $penanda = makeUserWith(['jadwal.lihat', 'rekaman.lihat', 'rekaman.tandai'], roleName: 'penanda-saja');
    $operator = makeUserWith(['jadwal.lihat', 'siswa.lihat'], $this->school, true, 'operator-pindai');
    [$akunSiswa] = siswaLengkap($this->school);

    foreach ([$penanda, $operator, $akunSiswa] as $user) {
        $this->actingAs($user)->get($this->url)->assertForbidden();
        $this->postJson($this->url, ['code' => 'KODE-HTTP-RAHASIA'])->assertForbidden();
    }
    expect(RegistrationStudent::withoutGlobalScopes()->where('status', S::SudahRekaman)->count())->toBe(0);
});

test('pemindai terikat sekolah tidak dapat memindai sesi sekolah lain', function () {
    $lain = School::factory()->create();
    $sesiLain = makeSchedule($lain, '2026-10-20');
    $terikat = makeUserWith(['rekaman.pindai'], $this->school, true, 'pemindai-terikat');

    $this->actingAs($terikat)->get("/schedules/{$sesiLain->id}/scan")->assertNotFound();
    $this->postJson("/schedules/{$sesiLain->id}/scan", ['code' => 'X'])->assertNotFound();
});

test('kode tiket tidak muncul di halaman peserta maupun penandaan', function () {
    tiketHttp($this);
    $staf = makeUserWith(['jadwal.lihat', 'rekaman.lihat', 'rekaman.tandai', 'rekaman.pindai', 'verifikasi.lihat'], roleName: 'staf-kode');
    $this->actingAs($staf);

    foreach (["/schedules/{$this->sesi->id}", "/schedules/{$this->sesi->id}/recording", $this->url] as $halaman) {
        $this->get($halaman)->assertOk();
        expect($this->get($halaman)->getContent())->not->toContain('KODE-HTTP-RAHASIA');
    }
});

test('rute pindai dibatasi lajunya', function () {
    $rute = app('router')->getRoutes()->getByName('schedules.scan.store');

    expect($rute->gatherMiddleware())->toContain('throttle:120,1');
});
