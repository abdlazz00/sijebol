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

function formSesi(array $override = []): array
{
    return [...['date' => now('Asia/Jakarta')->addDay()->toDateString(), 'start_time' => '08:00', 'end_time' => '11:30', 'location' => 'Aula SMPN 1', 'quota' => 25], ...$override];
}

test('admin membuat sesi lalu melihat detailnya dengan jam dan kuota', function () {
    $this->actingAs($this->admin)
        ->post('/jadwal', formSesi(['school_id' => $this->school->id]))
        ->assertRedirect();

    $s = Schedule::firstOrFail();
    $this->get("/jadwal/{$s->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Jadwal/Show')
        ->where('schedule.location', 'Aula SMPN 1')->where('schedule.is_past', false)
        ->where('schedule.start_time', '08:00')->where('schedule.end_time', '11:30')
        ->where('schedule.quota', 25)->where('schedule.taken', 0)->where('schedule.cancelled', false)
        ->where('schedule.last_registration_day', now('Asia/Jakarta')->subDay()->toDateString())
        ->has('participants', 0));
});

test('validasi: lokasi, sekolah, jam, dan kuota; tanggal tidak boleh lewat', function () {
    $this->actingAs($this->admin);

    $this->post('/jadwal', formSesi(['location' => '']))->assertSessionHasErrors(['location', 'school_id']);
    $this->post('/jadwal', formSesi(['school_id' => $this->school->id, 'date' => now('Asia/Jakarta')->subDay()->toDateString()]))->assertSessionHasErrors('date');
    $this->post('/jadwal', formSesi(['school_id' => $this->school->id, 'start_time' => '10:00', 'end_time' => '09:00']))->assertSessionHasErrors('end_time');
    $this->post('/jadwal', formSesi(['school_id' => $this->school->id, 'start_time' => '8 pagi']))->assertSessionHasErrors('start_time');
    foreach ([0, 1001, 'banyak', ''] as $kuota) {
        $this->post('/jadwal', formSesi(['school_id' => $this->school->id, 'quota' => $kuota]))->assertSessionHasErrors('quota');
    }
    expect(Schedule::count())->toBe(0);
});

test('ubah: sesi yang sudah lewat ditolak, memindahkan sesi mendatang ke masa lalu juga', function () {
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDays(3)->toDateString());
    $this->actingAs($this->admin);

    $this->put("/jadwal/{$lewat->id}", formSesi(['date' => $lewat->date->toDateString(), 'location' => 'Lokasi baru']))
        ->assertSessionHas('error', 'Sesi yang sudah lewat tidak dapat diubah.');
    expect($lewat->fresh()->location)->not->toBe('Lokasi baru');

    $depan = makeSchedule($this->school, $this->besok);
    $this->put("/jadwal/{$depan->id}", formSesi(['date' => now('Asia/Jakarta')->subDay()->toDateString()]))->assertSessionHasErrors('date');
});

test('ubah sesi berpendaftar: tanggal dan kuota di bawah kursi tertahan memberi galat per kolom', function () {
    $s = makeSchedule($this->school, $this->besok);
    makeEntry(S::Terdaftar, $this->school, schedule: $s);
    makeEntry(S::MenungguVerifikasi, $this->school, schedule: $s);
    $this->actingAs($this->admin);

    $this->put("/jadwal/{$s->id}", formSesi(['date' => now('Asia/Jakarta')->addDays(4)->toDateString()]))->assertSessionHasErrors('date');
    $this->put("/jadwal/{$s->id}", formSesi(['quota' => 1]))->assertSessionHasErrors('quota');
    $this->put("/jadwal/{$s->id}", formSesi(['quota' => 2, 'location' => 'Ruang baru']))->assertRedirect("/jadwal/{$s->id}");

    expect($s->fresh()->location)->toBe('Ruang baru')->and($s->fresh()->quota)->toBe(2);
});

test('halaman ubah memuat jam, kuota, dan penanda peserta', function () {
    $s = makeSchedule($this->school, $this->besok);
    makeEntry(S::Terdaftar, $this->school, schedule: $s);

    $this->actingAs($this->admin)->get("/jadwal/{$s->id}/ubah")->assertInertia(fn (Assert $p) => $p
        ->component('Jadwal/Form')
        ->where('schedule.start_time', '08:00')->where('schedule.end_time', '12:00')
        ->where('schedule.quota', 30)->where('schedule.has_participants', true));
});

test('detail memuat peserta semua status, urut id', function () {
    $s = makeSchedule($this->school, $this->besok);
    $a = makeEntry(S::Terdaftar, $this->school, schedule: $s);
    $b = makeEntry(S::Ditolak, $this->school, schedule: $s);
    makeEntry(S::Terdaftar, $this->school);

    $this->actingAs($this->admin)->get("/jadwal/{$s->id}")->assertInertia(fn (Assert $p) => $p
        ->has('participants', 2)
        ->where('participants.0.id', $a->id)->where('participants.0.status', 'terdaftar')
        ->where('participants.1.id', $b->id)->where('participants.1.status', 'ditolak')
        ->where('schedule.taken', 1));
});

test('rute penempatan siswa lama sudah tidak ada', function () {
    $s = makeSchedule($this->school, $this->besok);
    $e = makeEntry(S::Terdaftar, $this->school, schedule: $s);
    $this->actingAs($this->admin);

    $this->post("/jadwal/{$s->id}/siswa", ['entry_ids' => [$e->id]])->assertNotFound();
    $this->delete("/jadwal/{$s->id}/siswa/{$e->id}")->assertNotFound();
    $this->post("/jadwal/{$s->id}/siswa/{$e->id}/pindah", ['target_schedule_id' => $s->id])->assertNotFound();
});

test('operator hanya melihat sesi sekolahnya; sekolah lain 404; tanpa izin ubah ditolak', function () {
    $milik = makeSchedule($this->school, $this->besok);
    $asing = makeSchedule(null, $this->besok);
    $this->actingAs($this->op);

    $this->get('/jadwal')->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.id', $milik->id));
    $this->get("/jadwal/{$asing->id}")->assertNotFound();
    $this->get("/jadwal/{$milik->id}/ubah")->assertForbidden();
    $this->post('/jadwal', formSesi())->assertForbidden();
});

test('daftar menyaring periode dan memuat ringkasan status, kuota, dan kursi tertahan', function () {
    $depan = makeSchedule($this->school, $this->besok);
    makeSchedule($this->school, now('Asia/Jakarta')->subDays(2)->toDateString());
    makeEntry(S::Terdaftar, $this->school, schedule: $depan);
    makeEntry(S::Ditolak, $this->school, schedule: $depan);
    $depan->update(['cancelled_at' => now()]);
    $this->actingAs($this->admin);

    $this->get('/jadwal?filter[period]=akan-datang')->assertInertia(fn (Assert $p) => $p
        ->has('schedules.data', 1)->where('schedules.data.0.participants_count', 2)
        ->where('schedules.data.0.counts.terdaftar', 1)->where('schedules.data.0.counts.ditolak', 1)
        ->where('schedules.data.0.quota', 30)->where('schedules.data.0.taken', 1)->where('schedules.data.0.cancelled', true));
    $this->get('/jadwal?filter[period]=lewat')->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.is_past', true));
});

test('daftar sesi: akan datang terdekat dulu, lalu yang lewat terbaru dulu', function () {
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
