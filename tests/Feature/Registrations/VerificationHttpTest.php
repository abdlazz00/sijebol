<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    // Penerima notifikasi keputusan dicari lewat permission ini; di produksi sudah dibuat seeder.
    Permission::findOrCreate('pendaftaran.lihat', 'web');
    $this->petugas = makeUserWith(['verifikasi.lihat', 'verifikasi.putuskan', 'dokumen.lihat'], roleName: 'petugas-http');
});

test('petugas melihat antrean lintas sekolah dan halaman keputusan', function () {
    $a = makeEntry(S::Diajukan, student: eligibleStudent());
    makeEntry(S::Diajukan, student: eligibleStudent());
    makeEntry(S::Draf);
    $this->actingAs($this->petugas);

    $this->get('/verifikasi')->assertInertia(fn (Assert $p) => $p->component('Verifikasi/Index')->has('queue.data', 2));
    $this->get("/verifikasi/{$a->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Verifikasi/Show')
        ->where('entry.id', $a->id)->where('entry.status', 'diajukan')
        ->where('student.nik', fn ($nik) => str_contains($nik, '*'))
        ->has('documents', 2)->where('eligibility.eligible', true));
});

test('keputusan: catatan wajib untuk minta perbaikan dan tolak', function () {
    $entry = makeEntry(S::Diajukan, student: eligibleStudent());
    $this->actingAs($this->petugas);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'fix'])->assertSessionHasErrors('note');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'reject', 'note' => ''])->assertSessionHasErrors('note');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'lain'])->assertSessionHasErrors('decision');
    expect($entry->fresh()->status)->toBe(S::Diajukan);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'fix', 'note' => 'KK buram'])
        ->assertRedirect('/verifikasi')->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe(S::PerluPerbaikan)->and($entry->fresh()->note)->toBe('KK buram');
});

test('setujui tanpa catatan; keputusan kedua menghasilkan galat', function () {
    $entry = makeEntry(S::Diajukan, student: eligibleStudent());
    $this->actingAs($this->petugas);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'approve'])->assertSessionHas('success');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'reject', 'note' => 'x'])->assertSessionHas('error');
    expect($entry->fresh()->status)->toBe(S::Terverifikasi);
});

test('pengguna terikat sekolah lain mendapat 404 untuk entri sekolah berbeda', function () {
    $entry = makeEntry(S::Diajukan, student: eligibleStudent());
    $terikat = makeUserWith(['verifikasi.lihat', 'verifikasi.putuskan'], School::factory()->create(), true, 'terikat');
    $this->actingAs($terikat);

    $this->get("/verifikasi/{$entry->id}")->assertNotFound();
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'approve'])->assertNotFound();
    $this->get('/verifikasi')->assertInertia(fn (Assert $p) => $p->has('queue.data', 0));
});
