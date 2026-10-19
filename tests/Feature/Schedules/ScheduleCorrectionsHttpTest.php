<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['jadwal.lihat', 'jadwal.ubah'], roleName: 'admin-koreksi');
    $this->besok = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
});

test('hapus sesi kosong lewat HTTP; yang berisi memberi galat dan tetap ada', function () {
    $isi = makeSchedule($this->school, now('Asia/Jakarta')->addDays(3)->toDateString());
    makeEntry(S::Ditolak, $this->school, schedule: $isi);
    $this->actingAs($this->admin);

    $this->delete("/jadwal/{$this->besok->id}")->assertRedirect('/jadwal')->assertSessionHas('success');
    expect(Schedule::whereKey($this->besok->id)->exists())->toBeFalse();

    $this->delete("/jadwal/{$isi->id}")->assertSessionHas('error');
    expect(Schedule::whereKey($isi->id)->exists())->toBeTrue();
});

test('batalkan sesi lewat HTTP: catatan wajib, kursi dibatalkan, tanpa izin 403, sekolah lain 404', function () {
    $e = makeEntry(S::Terdaftar, $this->school, schedule: $this->besok);
    $url = "/jadwal/{$this->besok->id}/batalkan";
    $this->actingAs($this->admin);

    $this->post($url, [])->assertSessionHasErrors('note');
    $this->post($url, ['note' => 'abc'])->assertSessionHasErrors('note');
    expect($this->besok->fresh()->isCancelled())->toBeFalse();

    $this->post($url, ['note' => 'Gedung dipakai ujian.'])->assertRedirect("/jadwal/{$this->besok->id}")->assertSessionHas('success');
    expect($this->besok->fresh()->isCancelled())->toBeTrue()->and($e->fresh()->status)->toBe(S::Dibatalkan);

    $this->post($url, ['note' => 'Sekali lagi'])->assertSessionHas('error');

    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-batal-sesi');
    $this->actingAs($op)->post($url, ['note' => 'Tidak boleh'])->assertForbidden();

    $asing = makeSchedule(School::factory()->create(), now('Asia/Jakarta')->addDay()->toDateString());
    $opUbah = makeUserWith(['jadwal.lihat', 'jadwal.ubah'], $this->school, true, 'op-ubah-sesi');
    $this->actingAs($opUbah)->post("/jadwal/{$asing->id}/batalkan", ['note' => 'Sekolah lain'])->assertNotFound();
});
