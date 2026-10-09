<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

/** @param list<S> $statuses */
function makeEntries(array $statuses, ?School $school = null): void
{
    foreach ($statuses as $status) {
        makeEntry($status, $school ?? School::factory()->create());
    }
}

test('staf melihat kartu dan aksi se-kecamatan', function () {
    makeEntries([S::Diajukan, S::Diajukan, S::Terverifikasi, S::Terjadwal, S::SudahRekaman, S::PerluPerbaikan, S::Draf]);
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.lihat', 'jadwal.buat', 'pendaftaran.lihat'], roleName: 'staf-beranda');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('summary', 3)
        ->where('summary.0.label', 'Menunggu verifikasi')->where('summary.0.value', 2)
        ->where('summary.1.label', 'Terjadwal')->where('summary.1.value', 1)
        ->where('summary.2.label', 'Sudah rekaman')->where('summary.2.value', 1)
        ->has('actions', 2)
        ->where('actions.0.title', '2 entri menunggu verifikasi')->where('actions.0.href', '/verifikasi')
        ->where('actions.1.title', '1 siswa terverifikasi belum dijadwalkan')->where('actions.1.href', '/jadwal'));
});

test('operator hanya melihat angka dan tugas sekolahnya', function () {
    $sekolah = School::factory()->create();
    makeEntries([S::Draf, S::PerluPerbaikan, S::PerluPerbaikan, S::Terjadwal, S::SudahRekaman], $sekolah);
    makeEntries([S::Diajukan, S::Diajukan, S::PerluPerbaikan, S::Terjadwal]);
    $op = makeUserWith(['pendaftaran.lihat', 'jadwal.lihat'], $sekolah, true, 'op-beranda-2');

    $this->actingAs($op)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('summary', 3)
        ->where('summary.0.label', 'Draf')->where('summary.0.value', 1)
        ->where('summary.1.label', 'Perlu perbaikan')->where('summary.1.value', 2)
        ->where('summary.2.label', 'Sudah rekaman')->where('summary.2.value', 1)
        ->has('actions', 2)
        ->where('actions.0.title', '2 entri perlu diperbaiki')->where('actions.0.href', '/pendaftaran')
        ->where('actions.1.title', '1 siswa terjadwal')->where('actions.1.href', '/jadwal'));
});

test('kartu dan aksi hilang bila izin modulnya tidak ada', function () {
    makeEntries([S::Diajukan, S::Terverifikasi, S::Terjadwal]);

    $this->actingAs(makeUserWith([], roleName: 'tanpa-izin'))->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p->has('summary', 0)->has('actions', 0));

    $this->actingAs(makeUserWith(['jadwal.lihat'], roleName: 'hanya-jadwal'))->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p
            ->has('summary', 2)->where('summary.0.label', 'Terjadwal')->where('summary.1.label', 'Sudah rekaman')
            ->has('actions', 1)->where('actions.0.title', '1 siswa terjadwal'));
});

test('aksi dengan hitungan 0 disembunyikan tetapi kartu tetap tampil dengan angka 0', function () {
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.buat', 'jadwal.lihat'], roleName: 'staf-kosong');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('actions', 0)->where('summary.0.value', 0)->has('summary', 3));
});
