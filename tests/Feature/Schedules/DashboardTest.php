<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
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
