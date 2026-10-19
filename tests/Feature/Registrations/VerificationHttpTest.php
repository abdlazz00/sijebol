<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->petugas = makeUserWith(['verifikasi.lihat', 'verifikasi.putuskan', 'dokumen.lihat'], roleName: 'petugas-http');
});

test('petugas melihat antrean lintas sekolah dengan sesi, dan halaman keputusan', function () {
    $a = makeEntry(S::MenungguVerifikasi, student: eligibleStudent());
    makeEntry(S::MenungguVerifikasi, student: eligibleStudent());
    makeEntry(S::PerluPerbaikan);
    $this->actingAs($this->petugas);

    $this->get('/verifikasi')->assertInertia(fn (Assert $p) => $p
        ->component('Verifikasi/Index')->has('queue.data', 2)
        ->where('queue.data.0.session.id', $a->schedule_id)
        ->where('queue.data.0.session.start_time', '08:00')->where('queue.data.0.session.end_time', '12:00')
        ->where('queue.data.0.session.location', $a->schedule->location));
    $this->get("/verifikasi/{$a->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Verifikasi/Show')
        ->where('entry.id', $a->id)->where('entry.status', 'menunggu_verifikasi')
        ->where('entry.session.id', $a->schedule_id)->where('entry.session.date', $a->schedule->date->toDateString())
        ->where('entry.history', [])
        ->where('student.nik', fn ($nik) => str_contains($nik, '*'))
        ->has('documents', 2)->where('eligibility.eligible', true));
});

test('antrean dapat disaring per sesi', function () {
    $sesi = makeSchedule();
    makeEntry(S::MenungguVerifikasi, $sesi->school, schedule: $sesi);
    makeEntry(S::MenungguVerifikasi);
    $this->actingAs($this->petugas);

    $this->get("/verifikasi?filter[schedule_id]={$sesi->id}")->assertInertia(fn (Assert $p) => $p->has('queue.data', 1));
});

test('riwayat awal tanpa status asal tidak merusak halaman keputusan', function () {
    $entry = makeEntry(S::MenungguVerifikasi, student: eligibleStudent());
    $entry->histories()->create(['from_status' => null, 'to_status' => S::MenungguVerifikasi, 'by_user_id' => $this->petugas->id]);

    $this->actingAs($this->petugas)->get("/verifikasi/{$entry->id}")->assertInertia(fn (Assert $p) => $p
        ->where('entry.history.0.from', null)->where('entry.history.0.to', 'menunggu_verifikasi'));
});

test('keputusan: catatan wajib untuk minta perbaikan dan tolak', function () {
    $entry = makeEntry(S::MenungguVerifikasi, student: eligibleStudent());
    $this->actingAs($this->petugas);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'fix'])->assertSessionHasErrors('note');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'reject', 'note' => ''])->assertSessionHasErrors('note');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'lain'])->assertSessionHasErrors('decision');
    expect($entry->fresh()->status)->toBe(S::MenungguVerifikasi);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'fix', 'note' => 'KK buram'])
        ->assertRedirect('/verifikasi')->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe(S::PerluPerbaikan)->and($entry->fresh()->note)->toBe('KK buram');
});

test('setujui tanpa catatan; keputusan kedua menghasilkan galat', function () {
    $entry = makeEntry(S::MenungguVerifikasi, student: eligibleStudent());
    $this->actingAs($this->petugas);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'approve'])->assertSessionHas('success');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'reject', 'note' => 'x'])->assertSessionHas('error');
    expect($entry->fresh()->status)->toBe(S::Terdaftar);
});

test('pengguna terikat sekolah lain mendapat 404 untuk entri sekolah berbeda', function () {
    $entry = makeEntry(S::MenungguVerifikasi, student: eligibleStudent());
    $terikat = makeUserWith(['verifikasi.lihat', 'verifikasi.putuskan'], School::factory()->create(), true, 'terikat');
    $this->actingAs($terikat);

    $this->get("/verifikasi/{$entry->id}")->assertNotFound();
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'approve'])->assertNotFound();
    $this->get('/verifikasi')->assertInertia(fn (Assert $p) => $p->has('queue.data', 0));
});

test('setujui massal lewat HTTP: sukses, validasi jumlah dan id ganda, galat entri', function () {
    $a = makeEntry(S::MenungguVerifikasi, student: eligibleStudent());
    $b = makeEntry(S::MenungguVerifikasi, student: eligibleStudent());
    $this->actingAs($this->petugas);

    $this->post('/verifikasi/setujui', [])->assertSessionHasErrors('entry_ids');
    $this->post('/verifikasi/setujui', ['entry_ids' => []])->assertSessionHasErrors('entry_ids');
    $this->post('/verifikasi/setujui', ['entry_ids' => [$a->id, $a->id]])->assertSessionHasErrors('entry_ids.1');
    $this->post('/verifikasi/setujui', ['entry_ids' => range(1, 51)])->assertSessionHasErrors('entry_ids');
    expect($a->fresh()->status)->toBe(S::MenungguVerifikasi);

    $this->post('/verifikasi/setujui', ['entry_ids' => [$a->id, $b->id]])->assertRedirect('/verifikasi')->assertSessionHas('success', '2 siswa disetujui.');
    expect($a->fresh()->status)->toBe(S::Terdaftar)->and($b->fresh()->status)->toBe(S::Terdaftar);

    $this->post('/verifikasi/setujui', ['entry_ids' => [$a->id]])->assertSessionHasErrors('entry_ids');
});

test('antrean memuat opsi sesi yang punya antrean, urut tanggal, untuk filter', function () {
    $nanti = makeSchedule(null, now('Asia/Jakarta')->addDays(9)->toDateString());
    $segera = makeSchedule(null, now('Asia/Jakarta')->addDays(3)->toDateString());
    makeSchedule();                                                          // tanpa antrean
    makeEntry(S::MenungguVerifikasi, $nanti->school, schedule: $nanti);
    makeEntry(S::MenungguVerifikasi, $segera->school, schedule: $segera);
    makeEntry(S::Terdaftar, schedule: makeSchedule());                       // bukan antrean
    $this->actingAs($this->petugas);

    $this->get('/verifikasi')->assertInertia(fn (Assert $p) => $p
        ->has('sessions', 2)
        ->where('sessions.0.id', $segera->id)->where('sessions.1.id', $nanti->id)
        ->where('sessions.0.start_time', '08:00')->where('sessions.0.school', $segera->school->name));
});
