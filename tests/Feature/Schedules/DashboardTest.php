<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('Beranda memuat jadwal terdekat milik sekolah pengguna tanpa yang lewat', function () {
    $sekolah = School::factory()->create();
    $depan = makeSchedule($sekolah, now('Asia/Jakarta')->addDays(2)->toDateString());
    makeEntry(S::Terdaftar, $sekolah, schedule: $depan);
    makeEntry(S::Ditolak, $sekolah, schedule: $depan);
    makeSchedule($sekolah, now('Asia/Jakarta')->subDays(2)->toDateString());
    makeSchedule(null, now('Asia/Jakarta')->addDay()->toDateString());
    $op = makeUserWith(['jadwal.lihat'], $sekolah, true, 'op-beranda');

    $this->actingAs($op)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->component('Dashboard')->has('schedules', 1)
        ->where('schedules.0.participants', 1)->where('schedules.0.location', $depan->location));
});

test('Beranda tanpa izin jadwal tidak memuat jadwal; staf melihat semua sekolah', function () {
    makeSchedule(null, now('Asia/Jakarta')->addDay()->toDateString());
    makeSchedule(null, now('Asia/Jakarta')->addDays(2)->toDateString());

    $this->actingAs(makeUserWith([], roleName: 'tanpa-jdw'))->get('/dashboard')->assertInertia(fn (Assert $p) => $p->has('schedules', 0));
    $this->actingAs(makeUserWith(['jadwal.lihat'], roleName: 'staf-jdw'))->get('/dashboard')->assertInertia(fn (Assert $p) => $p->has('schedules', 2));
});

test('sesi dibatalkan tidak muncul di jadwal terdekat, tabel, maupun kalender', function () {
    $sekolah = School::factory()->create();
    $aktif = makeSchedule($sekolah, now('Asia/Jakarta')->addDays(2)->toDateString());
    $batal = makeSchedule($sekolah, now('Asia/Jakarta')->addDays(3)->toDateString());
    $batal->update(['cancelled_at' => now()]);
    $staf = makeUserWith(['jadwal.lihat'], roleName: 'staf-batal');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('schedules', 1)
        ->has('schedules_table', 1)->where('schedules_table.0.id', $aktif->id)
        ->has('calendar_events', 1)->where('calendar_events.0.id', $aktif->id));
});

test('baris tabel jadwal memuat kuota, terisi, menunggu verifikasi, dan sudah rekaman', function () {
    $sekolah = School::factory()->create();
    $sesi = makeSchedule($sekolah, now('Asia/Jakarta')->addDays(2)->toDateString());
    foreach ([S::MenungguVerifikasi, S::MenungguVerifikasi, S::PerluPerbaikan, S::Terdaftar, S::SudahRekaman, S::SudahRekaman, S::BelumRekaman, S::Ditolak, S::Dibatalkan, S::Kedaluwarsa, S::TidakHadir] as $status) {
        makeEntry($status, $sekolah, schedule: $sesi);
    }
    $staf = makeUserWith(['jadwal.lihat'], roleName: 'staf-baris');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('schedules_table.0.quota', 30)
        ->where('schedules_table.0.participants', 7)
        ->where('schedules_table.0.waiting', 2)
        ->where('schedules_table.0.present', 2)
        ->where('schedules_table.0.status', 'Dijadwalkan'));
});

test('beranda tidak menambah query per sesi', function () {
    $sekolah = School::factory()->create();
    $staf = makeUserWith(['jadwal.lihat'], roleName: 'staf-query');
    $this->actingAs($staf);
    $hitung = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/dashboard')->assertOk();

        return count(DB::getQueryLog());
    };
    $this->get('/dashboard');
    foreach (range(1, 2) as $i) {
        makeEntry(S::Terdaftar, $sekolah, schedule: makeSchedule($sekolah, now('Asia/Jakarta')->addDays($i)->toDateString()));
    }
    $kecil = $hitung();
    foreach (range(3, 8) as $i) {
        makeEntry(S::Terdaftar, $sekolah, schedule: makeSchedule($sekolah, now('Asia/Jakarta')->addDays($i)->toDateString()));
    }

    expect($hitung())->toBeLessThanOrEqual($kecil);
});
