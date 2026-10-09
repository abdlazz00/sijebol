<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['jadwal.lihat', 'jadwal.buat', 'jadwal.ubah', 'rekaman.lihat'], roleName: 'admin-http');
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-http-jdw');
    $this->besok = now('Asia/Jakarta')->addDay()->toDateString();
});

test('admin membuat jadwal lalu melihat detailnya', function () {
    $this->actingAs($this->admin)
        ->post('/jadwal', ['school_id' => $this->school->id, 'date' => $this->besok, 'location' => 'Aula SMPN 1'])
        ->assertRedirect();

    $s = Schedule::firstOrFail();
    $this->get("/jadwal/{$s->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Jadwal/Show')
        ->where('schedule.location', 'Aula SMPN 1')->where('schedule.is_past', false)
        ->has('participants', 0)->has('candidates', 0));
});

test('validasi: lokasi wajib, sekolah wajib untuk staf, tanggal tidak boleh lewat', function () {
    $this->actingAs($this->admin);

    $this->post('/jadwal', ['date' => $this->besok, 'location' => ''])->assertSessionHasErrors(['location', 'school_id']);
    $this->post('/jadwal', ['school_id' => $this->school->id, 'date' => now('Asia/Jakarta')->subDay()->toDateString(), 'location' => 'X'])
        ->assertSessionHasErrors('date');
    expect(Schedule::count())->toBe(0);
});

test('ubah: tanggal lampau yang tidak berubah boleh, memindahkan ke masa lalu tidak', function () {
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDays(3)->toDateString());
    $this->actingAs($this->admin);

    $this->put("/jadwal/{$lewat->id}", ['date' => $lewat->date->toDateString(), 'location' => 'Lokasi baru'])
        ->assertRedirect("/jadwal/{$lewat->id}");
    expect($lewat->fresh()->location)->toBe('Lokasi baru');

    $depan = makeSchedule($this->school, $this->besok);
    $this->put("/jadwal/{$depan->id}", ['date' => now('Asia/Jakarta')->subDay()->toDateString(), 'location' => 'X'])->assertSessionHasErrors('date');
});

test('kandidat hanya untuk pemegang jadwal.ubah dan operator tidak melihatnya', function () {
    $s = makeSchedule($this->school, $this->besok);
    makeEntry(S::Terverifikasi, $this->school);
    makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->admin)->get("/jadwal/{$s->id}")->assertInertia(fn (Assert $p) => $p
        ->has('candidates', 1)->where('candidates.0.hint', fn ($h) => is_string($h)));
    $this->actingAs($this->op)->get("/jadwal/{$s->id}")->assertInertia(fn (Assert $p) => $p->has('candidates', 0));
});

test('masukkan dan keluarkan siswa lewat HTTP, entri milik jadwal lain 404', function () {
    $s = makeSchedule($this->school, $this->besok);
    $e = makeEntry(S::Terverifikasi, $this->school);
    $lain = makeScheduledEntry(S::Terjadwal, makeSchedule($this->school, $this->besok));
    $this->actingAs($this->admin);

    $this->post("/jadwal/{$s->id}/siswa", ['entry_ids' => []])->assertSessionHasErrors('entry_ids');
    $this->post("/jadwal/{$s->id}/siswa", ['entry_ids' => [$e->id]])->assertRedirect("/jadwal/{$s->id}")->assertSessionHas('success');
    expect($e->fresh()->status)->toBe(S::Terjadwal);

    $this->delete("/jadwal/{$s->id}/siswa/{$lain->id}")->assertNotFound();
    $this->delete("/jadwal/{$s->id}/siswa/{$e->id}")->assertSessionHas('success');
    expect($e->fresh()->status)->toBe(S::Terverifikasi);
});

test('penempatan gagal menghasilkan galat entry_ids', function () {
    $s = makeSchedule($this->school, $this->besok);
    $diajukan = makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->admin)->post("/jadwal/{$s->id}/siswa", ['entry_ids' => [$diajukan->id]])->assertSessionHasErrors('entry_ids');
});

test('operator hanya melihat jadwal sekolahnya; sekolah lain 404; tanpa izin ubah ditolak', function () {
    $milik = makeSchedule($this->school, $this->besok);
    $asing = makeSchedule(null, $this->besok);
    $this->actingAs($this->op);

    $this->get('/jadwal')->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.id', $milik->id));
    $this->get("/jadwal/{$asing->id}")->assertNotFound();
    $this->get("/jadwal/{$milik->id}/ubah")->assertForbidden();
    $this->post('/jadwal', ['date' => $this->besok, 'location' => 'X'])->assertForbidden();
});

test('daftar menyaring periode dan memuat ringkasan status', function () {
    $depan = makeSchedule($this->school, $this->besok);
    makeSchedule($this->school, now('Asia/Jakarta')->subDays(2)->toDateString());
    makeScheduledEntry(S::Terjadwal, $depan);
    $this->actingAs($this->admin);

    $this->get('/jadwal?filter[period]=akan-datang')->assertInertia(fn (Assert $p) => $p
        ->has('schedules.data', 1)->where('schedules.data.0.participants_count', 1)->where('schedules.data.0.counts.terjadwal', 1));
    $this->get('/jadwal?filter[period]=lewat')->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.is_past', true));
});

test('daftar jadwal: akan datang terdekat dulu, lalu yang lewat terbaru dulu', function () {
    $tanggal = fn (int $hari) => now('Asia/Jakarta')->addDays($hari)->toDateString();
    foreach ([5, -3, 1, -1, 0] as $hari) {
        makeSchedule($this->school, $tanggal($hari));
    }

    $this->actingAs($this->admin)->get('/jadwal')->assertInertia(fn (Assert $p) => $p
        ->where('schedules.data.0.date', $tanggal(0))->where('schedules.data.1.date', $tanggal(1))
        ->where('schedules.data.2.date', $tanggal(5))->where('schedules.data.3.date', $tanggal(-1))
        ->where('schedules.data.4.date', $tanggal(-3)));

    $this->get('/jadwal?filter[period]=lewat')->assertInertia(fn (Assert $p) => $p
        ->where('schedules.data.0.date', $tanggal(-1))->where('schedules.data.1.date', $tanggal(-3)));
});
