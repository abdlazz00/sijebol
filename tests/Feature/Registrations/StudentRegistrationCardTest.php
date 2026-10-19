<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

test('detail siswa memuat status kunci dan daftar pendaftaran sesinya', function () {
    $sekolah = School::factory()->create();
    $siswa = eligibleStudent($sekolah);
    $entry = makeEntry(S::MenungguVerifikasi, student: $siswa);
    $user = makeUserWith(['siswa.lihat', 'dokumen.lihat'], $sekolah, true, 'op-kartu');

    $this->actingAs($user)->get("/siswa/{$siswa->id}")->assertInertia(fn (Assert $p) => $p
        ->where('locked', true)
        ->has('registrations', 1)
        ->where('registrations.0.id', $entry->id)
        ->where('registrations.0.status', 'menunggu_verifikasi')
        ->where('registrations.0.schedule.id', $entry->schedule_id)
        ->where('registrations.0.schedule.date', $entry->schedule->date->toDateString())
        ->where('registrations.0.schedule.start_time', '08:00')
        ->where('registrations.0.schedule.location', $entry->schedule->location));

    $bebas = eligibleStudent($sekolah);
    $this->get("/siswa/{$bebas->id}")->assertInertia(fn (Assert $p) => $p->where('locked', false)->has('registrations', 0));
});
