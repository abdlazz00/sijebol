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
