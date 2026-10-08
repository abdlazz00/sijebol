<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();
    $this->siswa = eligibleStudent($this->school);
    $this->op = makeUserWith(['siswa.lihat', 'siswa.ubah', 'siswa.hapus', 'dokumen.unggah', 'dokumen.hapus'], $this->school, true, 'op-kunci');
    $this->payload = fn (string $nama) => [
        'nik' => $this->siswa->nik, 'nisn' => $this->siswa->nisn, 'name' => $nama,
        'birth_date' => $this->siswa->birth_date->toDateString(), 'gender' => $this->siswa->gender,
    ];
});

test('ubah, unggah, dan hapus dokumen ditolak saat entri diproses', function (S $status) {
    makeEntry($status, student: $this->siswa);
    $this->actingAs($this->op);

    $this->put("/siswa/{$this->siswa->id}", ($this->payload)('Nama Baru'))->assertSessionHas('error', Student::LOCKED_MESSAGE);
    $this->post("/siswa/{$this->siswa->id}/dokumen/kk", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHas('error', Student::LOCKED_MESSAGE);
    $this->delete("/siswa/{$this->siswa->id}/dokumen/kk")->assertSessionHas('error', Student::LOCKED_MESSAGE);

    expect($this->siswa->fresh()->name)->not->toBe('Nama Baru')
        ->and($this->siswa->documents()->count())->toBe(2);
})->with([[S::Diajukan], [S::Terverifikasi]]);

test('ubah dan dokumen diizinkan saat draf atau perlu perbaikan', function (S $status) {
    makeEntry($status, student: $this->siswa);
    $this->actingAs($this->op);

    $this->put("/siswa/{$this->siswa->id}", ($this->payload)('Nama Baru'))->assertSessionMissing('error');
    $this->delete("/siswa/{$this->siswa->id}/dokumen/kk")->assertSessionMissing('error');

    expect($this->siswa->fresh()->name)->toBe('Nama Baru')->and($this->siswa->documents()->count())->toBe(1);
})->with([[S::Draf], [S::PerluPerbaikan]]);

test('siswa dengan entri apa pun tidak dapat dihapus, tanpa entri dapat', function () {
    makeEntry(S::Ditolak, student: $this->siswa);
    $this->actingAs($this->op)->delete("/siswa/{$this->siswa->id}")
        ->assertSessionHas('error', 'Siswa sudah memiliki pendaftaran; keluarkan dari draf atau hubungi admin.');
    expect(Student::find($this->siswa->id))->not->toBeNull();

    $lain = Student::factory()->for($this->school)->create();
    $this->delete("/siswa/{$lain->id}")->assertRedirect('/siswa');
    expect(Student::find($lain->id))->toBeNull();
});
