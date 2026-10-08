<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

test('detail siswa memuat status kunci dan daftar pendaftarannya', function () {
    $sekolah = School::factory()->create();
    $siswa = eligibleStudent($sekolah);
    $entry = makeEntry(S::Diajukan, student: $siswa);
    $user = makeUserWith(['siswa.lihat', 'dokumen.lihat'], $sekolah, true, 'op-kartu');

    $this->actingAs($user)->get("/siswa/{$siswa->id}")->assertInertia(fn (Assert $p) => $p
        ->where('locked', true)
        ->has('registrations', 1)
        ->where('registrations.0.id', $entry->registration_id)
        ->where('registrations.0.status', 'diajukan'));

    $bebas = eligibleStudent($sekolah);
    $this->get("/siswa/{$bebas->id}")->assertInertia(fn (Assert $p) => $p->where('locked', false)->has('registrations', 0));
});
