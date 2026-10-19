<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();

    $izin = ['profil.lihat', 'dokumen.lihat', 'dokumen.unggah', 'dokumen.hapus'];
    $this->akunA = makeUserWith($izin, $this->school, true, 'siswa');
    $this->akunB = makeUserWith($izin, $this->school, true, 'siswa');

    // Dua siswa satu sekolah, masing-masing punya akun dan satu dokumen KK.
    $this->a = Student::factory()->for($this->school)->create(['user_id' => $this->akunA->id]);
    $this->b = Student::factory()->for($this->school)->create(['user_id' => $this->akunB->id]);
    foreach ([$this->a, $this->b] as $siswa) {
        Storage::disk('local')->put("students/{$siswa->id}/kk.pdf", 'isi');
        $siswa->documents()->create(['type' => 'kk', 'path' => "students/{$siswa->id}/kk.pdf", 'mime' => 'application/pdf', 'size' => 4]);
    }
});

test('siswa tidak dapat melihat, mengunduh, mengunggah, atau menghapus dokumen siswa lain satu sekolah', function () {
    $this->actingAs($this->akunA);

    $this->get("/siswa/{$this->b->id}/dokumen/kk")->assertNotFound();
    $this->get("/siswa/{$this->b->id}/dokumen/kk/preview")->assertNotFound();
    $this->post("/siswa/{$this->b->id}/dokumen/akta_lahir", [
        'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
    ])->assertNotFound();
    $this->delete("/siswa/{$this->b->id}/dokumen/kk")->assertNotFound();

    expect($this->b->documents()->count())->toBe(1);
});

test('dokumen milik sendiri tetap dapat diakses', function () {
    $this->actingAs($this->akunA)->get("/siswa/{$this->a->id}/dokumen/kk")->assertOk();
});

test('siswa hanya melihat barisnya sendiri walau keliru diberi siswa.lihat', function () {
    makeUserWith(['siswa.lihat'], $this->school, true, 'siswa'); // salah konfigurasi role

    $this->actingAs($this->akunA)->get('/siswa')->assertInertia(fn (Assert $p) => $p
        ->component('Siswa/Index')
        ->has('students.data', 1)
        ->where('students.data.0.id', $this->a->id));

    $this->get("/siswa/{$this->b->id}")->assertNotFound();
});

test('operator sekolah tetap melihat semua siswa sekolahnya', function () {
    $operator = makeUserWith(['siswa.lihat'], $this->school, true, 'operator-uji');

    $this->actingAs($operator)->get('/siswa')->assertInertia(fn (Assert $p) => $p->has('students.data', 2));
});
