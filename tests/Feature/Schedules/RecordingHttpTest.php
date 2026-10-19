<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['jadwal.lihat', 'rekaman.lihat', 'rekaman.tandai'], roleName: 'admin-rek-http');
    $this->hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
});

test('layar penandaan memuat peserta Terdaftar dan yang sudah ditandai', function () {
    makeEntry(S::Terdaftar, schedule: $this->hariIni);
    makeEntry(S::SudahRekaman, schedule: $this->hariIni);

    $this->actingAs($this->admin)->get("/jadwal/{$this->hariIni->id}/rekaman")->assertInertia(fn (Assert $p) => $p
        ->component('Jadwal/Rekaman')->where('can_mark', true)
        ->has('entries', 1)->has('marked', 1)->where('marked.0.status', 'sudah_rekaman'));
});

test('penandaan massal lewat HTTP dan validasi', function () {
    $a = makeEntry(S::Terdaftar, schedule: $this->hariIni);
    $this->actingAs($this->admin);
    $url = "/jadwal/{$this->hariIni->id}/rekaman";

    $this->post($url, ['entry_ids' => [$a->id]])->assertSessionHasErrors('status');
    $this->post($url, ['status' => 'diajukan', 'entry_ids' => [$a->id]])->assertSessionHasErrors('status');
    $this->post($url, ['status' => 'tidak_hadir', 'entry_ids' => []])->assertSessionHasErrors('entry_ids');
    expect($a->fresh()->status)->toBe(S::Terdaftar);

    $this->post($url, ['status' => 'tidak_hadir', 'entry_ids' => [$a->id], 'note' => 'Sakit'])->assertRedirect($url)->assertSessionHas('success');
    expect($a->fresh()->status)->toBe(S::TidakHadir)->and($a->fresh()->note)->toBe('Sakit');
});

test('sebelum hari kegiatan: halaman menandai can_mark salah dan POST menghasilkan galat', function () {
    $besok = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
    $e = makeEntry(S::Terdaftar, schedule: $besok);
    $this->actingAs($this->admin);

    $this->get("/jadwal/{$besok->id}/rekaman")->assertInertia(fn (Assert $p) => $p->where('can_mark', false));
    $this->post("/jadwal/{$besok->id}/rekaman", ['status' => 'sudah_rekaman', 'entry_ids' => [$e->id]])->assertSessionHas('error');
    expect($e->fresh()->status)->toBe(S::Terdaftar);
});

test('operator tanpa izin rekaman ditolak', function () {
    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-rek-http');

    $this->actingAs($op)->get("/jadwal/{$this->hariIni->id}/rekaman")->assertForbidden();
});

test('batalkan penandaan lewat HTTP: catatan wajib, sukses, entri sesi lain 404, tanpa izin 403', function () {
    $e = makeEntry(S::SudahRekaman, schedule: $this->hariIni);
    $lain = makeEntry(S::SudahRekaman, schedule: makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString()));
    $url = "/jadwal/{$this->hariIni->id}/rekaman/{$e->id}/batalkan";
    $this->actingAs($this->admin);

    $this->post($url, [])->assertSessionHasErrors('note');
    $this->post($url, ['note' => ''])->assertSessionHasErrors('note');
    $this->post("/jadwal/{$this->hariIni->id}/rekaman/{$lain->id}/batalkan", ['note' => 'x'])->assertNotFound();
    expect($e->fresh()->status)->toBe(S::SudahRekaman);

    $this->post($url, ['note' => 'Salah tandai'])->assertRedirect("/jadwal/{$this->hariIni->id}/rekaman")->assertSessionHas('success');
    expect($e->fresh()->status)->toBe(S::Terdaftar);

    $op = makeUserWith(['jadwal.lihat', 'rekaman.lihat'], $this->school, true, 'op-batal');
    $this->actingAs($op)->post($url, ['note' => 'x'])->assertForbidden();
});
