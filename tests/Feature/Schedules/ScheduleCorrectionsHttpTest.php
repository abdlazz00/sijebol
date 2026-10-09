<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['jadwal.lihat', 'jadwal.ubah', 'rekaman.lihat', 'rekaman.tandai'], roleName: 'admin-koreksi');
    $this->hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
    $this->besok = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
});

test('hapus jadwal kosong lewat HTTP; yang berisi memberi galat dan tetap ada', function () {
    $isi = makeSchedule($this->school, now('Asia/Jakarta')->addDays(3)->toDateString());
    makeScheduledEntry(S::Terjadwal, $isi);
    $this->actingAs($this->admin);

    $this->delete("/jadwal/{$this->besok->id}")->assertRedirect('/jadwal')->assertSessionHas('success');
    expect(Schedule::whereKey($this->besok->id)->exists())->toBeFalse();

    $this->delete("/jadwal/{$isi->id}")->assertSessionHas('error');
    expect(Schedule::whereKey($isi->id)->exists())->toBeTrue();
});

test('pindah lewat HTTP: sukses, target wajib, entri jadwal lain 404', function () {
    $e = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $lain = makeScheduledEntry(S::Terjadwal, $this->besok);
    $this->actingAs($this->admin);
    $url = "/jadwal/{$this->hariIni->id}/siswa/{$e->id}/pindah";

    $this->post($url, [])->assertSessionHasErrors('target_schedule_id');
    $this->post($url, ['target_schedule_id' => 999999])->assertSessionHasErrors('target_schedule_id');
    $this->post("/jadwal/{$this->hariIni->id}/siswa/{$lain->id}/pindah", ['target_schedule_id' => $this->besok->id])->assertNotFound();

    $this->post($url, ['target_schedule_id' => $this->besok->id])->assertRedirect("/jadwal/{$this->hariIni->id}")->assertSessionHas('success');
    expect($e->fresh()->schedule_id)->toBe($this->besok->id);
});

test('pindah ke jadwal sekolah lain menghasilkan galat tanpa mengubah entri', function () {
    $e = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $asing = makeSchedule(null, now('Asia/Jakarta')->addDay()->toDateString());
    $this->actingAs($this->admin);

    $this->post("/jadwal/{$this->hariIni->id}/siswa/{$e->id}/pindah", ['target_schedule_id' => $asing->id])->assertSessionHas('error');
    expect($e->fresh()->schedule_id)->toBe($this->hariIni->id);
});

test('detail jadwal memuat jadwal tujuan hanya bagi pemegang jadwal.ubah', function () {
    makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-koreksi');

    $this->actingAs($this->admin)->get("/jadwal/{$this->hariIni->id}")->assertInertia(fn (Assert $p) => $p
        ->has('targets', 1)->where('targets.0.id', $this->besok->id)->where('targets.0.location', $this->besok->location));
    $this->actingAs($op)->get("/jadwal/{$this->hariIni->id}")->assertInertia(fn (Assert $p) => $p->has('targets', 0));
});

test('batalkan penandaan lewat HTTP: catatan wajib, sukses, entri jadwal lain 404, tanpa izin 403', function () {
    $e = makeScheduledEntry(S::SudahRekaman, $this->hariIni);
    $lain = makeScheduledEntry(S::SudahRekaman, $this->besok);
    $url = "/jadwal/{$this->hariIni->id}/rekaman/{$e->id}/batalkan";
    $this->actingAs($this->admin);

    $this->post($url, [])->assertSessionHasErrors('note');
    $this->post($url, ['note' => ''])->assertSessionHasErrors('note');
    $this->post("/jadwal/{$this->hariIni->id}/rekaman/{$lain->id}/batalkan", ['note' => 'x'])->assertNotFound();
    expect($e->fresh()->status)->toBe(S::SudahRekaman);

    $this->post($url, ['note' => 'Salah tandai'])->assertRedirect("/jadwal/{$this->hariIni->id}/rekaman")->assertSessionHas('success');
    expect($e->fresh()->status)->toBe(S::Terjadwal);

    $op = makeUserWith(['jadwal.lihat', 'rekaman.lihat'], $this->school, true, 'op-batal');
    $this->actingAs($op)->post($url, ['note' => 'x'])->assertForbidden();
});
