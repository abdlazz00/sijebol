<?php

use App\Domains\Access\Services\ActivationService;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function dataDiri(array $override = []): array
{
    return [...['nik' => '3201123412341234', 'nisn' => '0012345678', 'name' => 'Budi Santoso', 'birth_date' => '2009-05-01', 'gender' => 'L'], ...$override];
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(AccessSeeder::class);
    $this->school = School::factory()->create(['name' => 'SMAN 1 Sungai Beduk']);
    $this->akun = User::factory()->create(['name' => 'Budi', 'school_id' => $this->school->id])->assignRole('siswa');
});

test('siswa baru melihat form kosong dengan alasan data belum disimpan', function () {
    $this->actingAs($this->akun)->get('/saya')->assertInertia(fn (Assert $p) => $p
        ->component('Saya/Index')
        ->where('student', null)
        ->where('school', 'SMAN 1 Sungai Beduk')
        ->where('account_name', 'Budi')
        ->where('documents', [])
        ->where('eligibility.eligible', false)
        ->where('eligibility.reasons', ['Data diri belum disimpan.'])
        ->where('locked', false));
});

test('menyimpan data pertama membuat baris siswa terhubung ke akun dan sekolahnya', function () {
    $lain = School::factory()->create();

    $this->actingAs($this->akun)
        ->put('/saya', [...dataDiri(), 'school_id' => $lain->id, 'user_id' => 999])
        ->assertRedirect('/saya')
        ->assertSessionHas('success');

    $siswa = Student::withoutGlobalScopes()->where('nik', '3201123412341234')->first();

    expect($siswa->user_id)->toBe($this->akun->id)
        ->and($siswa->school_id)->toBe($this->school->id);
});

test('menyimpan ulang memperbarui baris yang sama dan NIK sendiri tidak dianggap bentrok', function () {
    $this->actingAs($this->akun);
    $this->put('/saya', dataDiri());
    $this->put('/saya', dataDiri(['name' => 'Budi Baru']))->assertSessionHasNoErrors();

    expect(Student::withoutGlobalScopes()->count())->toBe(1)
        ->and(Student::withoutGlobalScopes()->first()->name)->toBe('Budi Baru');
});

test('NIK milik siswa lain ditolak dan tidak ada yang tersimpan', function () {
    Student::factory()->create(['nik' => '3201123412341234']);

    $this->actingAs($this->akun)->put('/saya', dataDiri())->assertSessionHasErrors('nik');

    expect(Student::withoutGlobalScopes()->count())->toBe(1);
});

test('data diri divalidasi', function () {
    $this->actingAs($this->akun);

    $this->put('/saya', dataDiri(['nik' => '123']))->assertSessionHasErrors('nik');
    $this->put('/saya', dataDiri(['nisn' => '123']))->assertSessionHasErrors('nisn');
    $this->put('/saya', dataDiri(['birth_date' => now()->addDay()->toDateString()]))->assertSessionHasErrors('birth_date');
    $this->put('/saya', dataDiri(['gender' => 'X']))->assertSessionHasErrors('gender');
    $this->put('/saya', dataDiri(['name' => '']))->assertSessionHasErrors('name');
});

test('halaman memuat data, dokumen, dan alasan kelayakan setelah disimpan', function () {
    $this->actingAs($this->akun)->put('/saya', dataDiri());
    $siswa = Student::withoutGlobalScopes()->first();
    $siswa->documents()->create(['type' => 'kk', 'path' => 'students/1/kk.pdf', 'mime' => 'application/pdf', 'size' => 1]);

    $this->get('/saya')->assertInertia(fn (Assert $p) => $p
        ->where('student.nik', '3201123412341234')
        ->where('student.name', 'Budi Santoso')
        ->has('documents', 1)
        ->where('documents.0.type', 'kk')
        ->where('eligibility.eligible', false)
        ->where('eligibility.reasons', ['Akta kelahiran belum diunggah.']));
});

test('ubah, unggah, dan hapus dokumen ditolak saat entri diproses', function (S $status) {
    $this->actingAs($this->akun)->put('/saya', dataDiri());
    $siswa = Student::withoutGlobalScopes()->first();
    $siswa->documents()->create(['type' => 'kk', 'path' => 'students/1/kk.pdf', 'mime' => 'application/pdf', 'size' => 1]);
    makeEntry($status, student: $siswa);

    $this->put('/saya', dataDiri(['name' => 'Nama Baru']))->assertSessionHas('error', Student::LOCKED_MESSAGE);
    $this->post("/siswa/{$siswa->id}/dokumen/akta_lahir", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->assertSessionHas('error', Student::LOCKED_MESSAGE);
    $this->delete("/siswa/{$siswa->id}/dokumen/kk")->assertSessionHas('error', Student::LOCKED_MESSAGE);

    expect($siswa->fresh()->name)->toBe('Budi Santoso')
        ->and($siswa->documents()->count())->toBe(1);

    $this->get('/saya')->assertInertia(fn (Assert $p) => $p->where('locked', true));
})->with([[S::MenungguVerifikasi], [S::Terdaftar]]);

test('ubah dan dokumen diizinkan saat draf atau perlu perbaikan', function (S $status) {
    $this->actingAs($this->akun)->put('/saya', dataDiri());
    $siswa = Student::withoutGlobalScopes()->first();
    $siswa->documents()->create(['type' => 'kk', 'path' => 'students/1/kk.pdf', 'mime' => 'application/pdf', 'size' => 1]);
    makeEntry($status, student: $siswa);

    $this->put('/saya', dataDiri(['name' => 'Nama Baru']))->assertSessionMissing('error');
    $this->delete("/siswa/{$siswa->id}/dokumen/kk")->assertSessionMissing('error');

    expect($siswa->fresh()->name)->toBe('Nama Baru')
        ->and($siswa->documents()->count())->toBe(0);
})->with([[S::PerluPerbaikan], [S::Ditolak]]);

test('akun yang bukan siswa mendapat 404 pada /saya', function () {
    $staf = makeUserWith(['profil.lihat', 'profil.ubah'], roleName: 'bukan-siswa');

    $this->actingAs($staf)->get('/saya')->assertNotFound();
    $this->put('/saya', dataDiri())->assertNotFound();
    $this->put('/saya', [])->assertNotFound();
});

test('siswa yang membuka dashboard dialihkan ke /saya, toast aktivasi tetap terbawa', function () {
    $user = User::factory()->pending()->create(['school_id' => $this->school->id])->assignRole('siswa');
    $token = app(ActivationService::class)->issue($user);

    $this->post("/aktivasi/{$token}", ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
        ->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect('/saya');
    $this->get('/saya')->assertInertia(fn (Assert $p) => $p->where('flash.success', 'Akun aktif. Selamat datang.'));
});

test('kartu kelayakan /saya tidak menyebut pendaftaran aktif sebagai alasan', function () {
    [$akun, $siswa] = siswaLengkap(School::factory()->create());
    makeEntry(S::Terdaftar, student: $siswa);

    $this->actingAs($akun)->get('/saya')->assertInertia(fn (Assert $p) => $p
        ->where('eligibility.eligible', true)->where('eligibility.reasons', []));
});
