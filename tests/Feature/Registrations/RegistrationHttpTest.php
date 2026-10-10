<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    // Penerima notifikasi dicari lewat permission ini; di produksi sudah dibuat seeder.
    Permission::findOrCreate('verifikasi.putuskan', 'web');
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.lihat', 'pendaftaran.buat', 'pendaftaran.ajukan'], $this->school, true, 'op-http');
    $this->siswa = eligibleStudent($this->school);
});

test('operator membuat draf lalu melihat detailnya', function () {
    $res = $this->actingAs($this->op)->post('/pendaftaran', ['title' => 'Gelombang 1', 'expected_count' => 20, 'student_ids' => [$this->siswa->id]]);

    $r = Registration::firstOrFail();
    $res->assertRedirect("/pendaftaran/{$r->id}");
    expect($r->school_id)->toBe($this->school->id);

    $this->get("/pendaftaran/{$r->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Pendaftaran/Show')
        ->where('registration.title', 'Gelombang 1')
        ->has('entries', 1)
        ->where('entries.0.status', 'draf')
        ->where('entries.0.student.id', $this->siswa->id));
});

test('judul wajib dan siswa sekolah lain ditolak', function () {
    $asing = eligibleStudent();
    $this->actingAs($this->op);

    $this->post('/pendaftaran', ['title' => '', 'student_ids' => []])->assertSessionHasErrors('title');
    $this->post('/pendaftaran', ['title' => 'X', 'student_ids' => [$asing->id]])->assertSessionHasErrors('student_ids.0');
    expect(Registration::count())->toBe(0);
});

test('staf kecamatan wajib memilih sekolah dan melihat kandidat sekolah itu', function () {
    $staf = makeUserWith(['pendaftaran.buat'], roleName: 'staf-http');
    $this->actingAs($staf);

    $this->post('/pendaftaran', ['title' => 'X', 'student_ids' => []])->assertSessionHasErrors('school_id');
    $this->get("/pendaftaran/buat?school_id={$this->school->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Pendaftaran/Form')
        ->has('schools', School::count())
        ->has('candidates.data', 1)
        ->where('candidates.data.0.eligible', true));
    $this->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 0));
});

test('operator melihat kandidat sekolahnya tanpa daftar sekolah', function () {
    $this->actingAs($this->op)->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p
        ->where('schools', null)->has('candidates.data', 1)->where('candidates.data.0.selected', false));
});

test('ubah dan hapus draf; pendaftaran terajukan tidak dapat diubah', function () {
    $this->actingAs($this->op)->post('/pendaftaran', ['title' => 'A', 'student_ids' => [$this->siswa->id]]);
    $r = Registration::firstOrFail();

    $this->put("/pendaftaran/{$r->id}", ['title' => 'B', 'student_ids' => []])->assertRedirect("/pendaftaran/{$r->id}");
    expect($r->fresh()->title)->toBe('B')->and($r->entries()->count())->toBe(0);

    $this->put("/pendaftaran/{$r->id}", ['title' => 'B', 'student_ids' => [$this->siswa->id]]);
    $this->post("/pendaftaran/{$r->id}/ajukan")->assertRedirect()->assertSessionHas('success');
    expect($r->entries()->first()->status)->toBe(S::Diajukan);

    $this->put("/pendaftaran/{$r->id}", ['title' => 'C', 'student_ids' => []])->assertSessionHas('error');
    $this->get("/pendaftaran/{$r->id}/ubah")->assertRedirect("/pendaftaran/{$r->id}");
    $this->delete("/pendaftaran/{$r->id}")->assertSessionHas('error');
    expect(Registration::find($r->id))->not->toBeNull();
});

test('draf dapat dihapus', function () {
    $this->actingAs($this->op)->post('/pendaftaran', ['title' => 'A', 'student_ids' => []]);
    $r = Registration::firstOrFail();

    $this->delete("/pendaftaran/{$r->id}")->assertRedirect('/pendaftaran');
    expect(Registration::count())->toBe(0);
});

test('pengajuan yang gagal mengembalikan galat per siswa', function () {
    $this->siswa->documents()->where('type', 'kk')->delete();
    $this->actingAs($this->op)->post('/pendaftaran', ['title' => 'A', 'student_ids' => [$this->siswa->id]]);
    $r = Registration::firstOrFail();

    $this->post("/pendaftaran/{$r->id}/ajukan")
        ->assertSessionHasErrors(['student_ids', "student_ids.{$this->siswa->id}"]);
    expect($r->fresh()->submitted_at)->toBeNull();
});

test('ajukan ulang lewat URL bertingkat dan entri milik pendaftaran lain 404', function () {
    $entry = makeEntry(S::PerluPerbaikan, $this->school, eligibleStudent($this->school));
    $lain = makeEntry(S::PerluPerbaikan, $this->school, eligibleStudent($this->school));
    $this->actingAs($this->op);

    $this->post("/pendaftaran/{$entry->registration_id}/entri/{$lain->id}/ajukan-ulang")->assertNotFound();
    $this->post("/pendaftaran/{$entry->registration_id}/entri/{$entry->id}/ajukan-ulang")->assertRedirect()->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe(S::Diajukan);
});

test('operator tidak dapat mengakses pendaftaran sekolah lain dan daftar hanya milik sendiri', function () {
    $milik = makeEntry(S::Draf, $this->school);
    $asing = makeEntry(S::Draf);
    $this->actingAs($this->op);

    $this->get("/pendaftaran/{$asing->registration_id}")->assertNotFound();
    $this->get("/pendaftaran/{$asing->registration_id}/ubah")->assertNotFound();
    $this->put("/pendaftaran/{$asing->registration_id}", ['title' => 'x', 'student_ids' => []])->assertNotFound();
    $this->delete("/pendaftaran/{$asing->registration_id}")->assertNotFound();
    $this->post("/pendaftaran/{$asing->registration_id}/ajukan")->assertNotFound();
    $this->get('/pendaftaran')->assertInertia(fn (Assert $p) => $p
        ->has('registrations.data', 1)->where('registrations.data.0.id', $milik->registration_id));
});

test('daftar dapat difilter menurut status', function () {
    makeEntry(S::Draf, $this->school);
    $diajukan = makeEntry(S::Diajukan, $this->school);
    $this->actingAs($this->op)->get('/pendaftaran?filter[status]=diajukan')->assertInertia(fn (Assert $p) => $p
        ->has('registrations.data', 1)->where('registrations.data.0.id', $diajukan->registration_id)
        ->where('registrations.data.0.counts.diajukan', 1));
});

test('operator sekolah tetap dapat membuka detail yang riwayatnya dibuat staf kecamatan', function () {
    $staf = makeUserWith(['verifikasi.putuskan'], roleName: 'staf-kec');
    $entry = makeEntry(S::Terverifikasi, $this->school);
    $entry->histories()->create(['from_status' => S::Diajukan, 'to_status' => S::Terverifikasi, 'by_user_id' => $staf->id]);

    $this->actingAs($this->op)->get("/pendaftaran/{$entry->registration_id}")
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Pendaftaran/Show')
            ->where('entries.0.history.0.by', $staf->name));
});
