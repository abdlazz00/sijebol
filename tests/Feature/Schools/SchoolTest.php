<?php

use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

test('daftar sekolah dengan pencarian dan jumlah operator', function () {
    $a = School::factory()->create(['name' => 'SMAN 1 Sungai Beduk', 'npsn' => '11111111']);
    School::factory()->create(['name' => 'SMKN 2 Lain', 'npsn' => '22222222']);
    makeUserWith([], $a);

    $this->actingAs(makeUserWith(['sekolah.lihat']))
        ->get('/schools?filter[search]=Sungai')
        ->assertInertia(fn (Assert $p) => $p
            ->component('Sekolah/Index')
            ->has('schools.data', 1)
            ->where('schools.data.0.name', 'SMAN 1 Sungai Beduk')
            ->where('schools.data.0.users_count', 1));
});

test('tambah dan ubah sekolah', function () {
    $this->actingAs(makeUserWith(['sekolah.buat', 'sekolah.ubah']));

    $this->post('/schools', ['name' => 'MAN 1', 'npsn' => '33333333', 'level' => 'MA', 'address' => 'Jl. A'])
        ->assertRedirect('/schools');
    $school = School::firstWhere('npsn', '33333333');
    expect($school->name)->toBe('MAN 1');

    $this->put("/schools/{$school->id}", ['name' => 'MAN 1 Baru', 'npsn' => '33333333', 'level' => 'MA'])
        ->assertRedirect('/schools');
    expect($school->fresh()->name)->toBe('MAN 1 Baru');
});

test('validasi: NPSN unik dan jenjang terbatas', function () {
    School::factory()->create(['npsn' => '44444444']);
    $this->actingAs(makeUserWith(['sekolah.buat']));

    $this->post('/schools', ['name' => 'X', 'npsn' => '44444444', 'level' => 'SD'])
        ->assertSessionHasErrors(['npsn', 'level']);
});

test('halaman form tambah dan ubah', function () {
    $school = School::factory()->create();
    $this->actingAs(makeUserWith(['sekolah.buat', 'sekolah.ubah']));

    $this->get('/schools/create')->assertInertia(fn (Assert $p) => $p->component('Sekolah/Form')->where('school', null));
    $this->get("/schools/{$school->id}/edit")->assertInertia(fn (Assert $p) => $p->where('school.id', $school->id));
});

test('pengguna terikat sekolah ditolak menambah sekolah walau role-nya memegang izin sekolah.buat', function () {
    $milik = School::factory()->create();
    $operator = makeUserWith(['sekolah.buat'], $milik, true, 'operator-tambah-sekolah');

    $this->actingAs($operator)
        ->post('/schools', ['name' => 'SMA Baru', 'npsn' => '55555555', 'level' => 'SMA'])
        ->assertSessionHas('error', 'Hanya staf kecamatan yang dapat menambah sekolah.');

    expect(School::where('npsn', '55555555')->exists())->toBeFalse();
});

test('pengguna terikat sekolah ditolak mengubah sekolah mana pun, termasuk sekolahnya sendiri, walau role-nya memegang izin sekolah.ubah', function () {
    $milik = School::factory()->create(['name' => 'Milik Sendiri']);
    $lain = School::factory()->create(['name' => 'Sekolah Lain']);
    $operator = makeUserWith(['sekolah.ubah'], $milik, true, 'operator-ubah-sekolah');

    foreach ([$milik, $lain] as $sekolah) {
        $this->actingAs($operator)
            ->put("/schools/{$sekolah->id}", ['name' => 'Diganti', 'npsn' => $sekolah->npsn, 'level' => $sekolah->level])
            ->assertSessionHas('error', 'Hanya staf kecamatan yang dapat mengubah data sekolah.');
    }

    expect($milik->fresh()->name)->toBe('Milik Sendiri')
        ->and($lain->fresh()->name)->toBe('Sekolah Lain');
});
