<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.buat'], $this->school, true, 'op-picker');
});

test('kandidat dipaginasi 20 per halaman', function () {
    Student::factory()->for($this->school)->count(25)->create();
    $this->actingAs($this->op);

    $this->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 20)->where('candidates.total', 25)->where('candidates.last_page', 2));
    $this->get('/pendaftaran/buat?page=2')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 5));
});

test('pencarian menurut nama atau NISN; wildcard literal; q bukan string diabaikan', function () {
    Student::factory()->for($this->school)->create(['name' => 'Ani Lestari', 'nisn' => '0011223344']);
    Student::factory()->for($this->school)->create(['name' => 'Budi Santoso', 'nisn' => '0055667788']);
    $this->actingAs($this->op);

    $this->get('/pendaftaran/buat?q=lestari')->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 1)->where('candidates.data.0.name', 'Ani Lestari'));
    $this->get('/pendaftaran/buat?q=0055667788')->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 1)->where('candidates.data.0.name', 'Budi Santoso'));
    $this->get('/pendaftaran/buat?q=%25')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 0));
    $this->get('/pendaftaran/buat?q=_')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 0));
    $this->get('/pendaftaran/buat?q[]=x')->assertOk()->assertInertia(fn (Assert $p) => $p->has('candidates.data', 2));
});

test('kandidat hanya dari sekolah yang berlaku dan siswa sekolah lain tidak bocor', function () {
    $lain = School::factory()->create();
    Student::factory()->for($lain)->create(['name' => 'Siswa Lain']);
    Student::factory()->for($this->school)->create(['name' => 'Siswa Sendiri']);

    $this->actingAs($this->op)->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 1)->where('candidates.data.0.name', 'Siswa Sendiri'));

    $staf = makeUserWith(['pendaftaran.buat'], roleName: 'staf-picker');
    $this->actingAs($staf)->get("/pendaftaran/buat?school_id={$lain->id}")->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 1)->where('candidates.data.0.name', 'Siswa Lain'));
    $this->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 0)->where('candidates.total', 0));
});

test('ubah draf: selected memuat siswa entri walau di halaman lain, dengan alasan bila tidak eligible', function () {
    Student::factory()->for($this->school)->count(25)->create(['name' => 'Aaa Siswa']);
    $terakhir = Student::factory()->for($this->school)->create(['name' => 'Zzz Terakhir']);
    $entry = makeEntry(S::Draf, $this->school, $terakhir);

    $this->actingAs($this->op)->get("/pendaftaran/{$entry->registration_id}/ubah")->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 20)
        ->has('selected', 1)->where('selected.0.name', 'Zzz Terakhir')->where('selected.0.selected', true)
        ->where('selected.0.eligible', false)->has('selected.0.reasons'));
});

test('buat pendaftaran tidak memiliki siswa terpilih', function () {
    Student::factory()->for($this->school)->create();

    $this->actingAs($this->op)->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p->has('selected', 0));
});
