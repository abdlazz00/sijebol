<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentDocument;
use App\Domains\Students\Services\StudentDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();
    $this->student = Student::factory()->for($this->school)->create(['nisn' => '0012345678']);
    $this->op = makeUserWith(['siswa.lihat', 'dokumen.lihat', 'dokumen.unggah', 'dokumen.hapus'], $this->school, true, 'op-dok');
});

function pdf(string $name = 'a.pdf', int $kb = 10): UploadedFile
{
    return UploadedFile::fake()->create($name, $kb, 'application/pdf');
}

test('unggah menyimpan berkas privat dengan nama acak dan mencatat log', function () {
    $this->actingAs($this->op)
        ->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => pdf('kk-asli.pdf', 100)])
        ->assertRedirect();

    $doc = $this->student->documents()->where('type', 'kk')->firstOrFail();
    Storage::disk('local')->assertExists($doc->path);
    expect($doc->path)->toStartWith("students/{$this->student->id}/")->not->toContain('kk-asli')
        ->and(Activity::where('description', 'dokumen.diunggah')->count())->toBe(1);
});

test('unggah ulang mengganti berkas lama (hanya satu berkas di disk)', function () {
    $this->actingAs($this->op);
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => pdf()]);
    $lama = $this->student->documents()->first()->path;
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => UploadedFile::fake()->image('b.jpg')]);

    Storage::disk('local')->assertMissing($lama);
    expect($this->student->documents()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles("students/{$this->student->id}"))->toHaveCount(1);
});

test('tipe dan ukuran ditolak, type tak dikenal 404', function () {
    $this->actingAs($this->op);
    $url = "/siswa/{$this->student->id}/dokumen/kk";

    $this->post($url, ['file' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
    $this->post($url, ['file' => pdf('besar.pdf', 6000)])->assertSessionHasErrors('file');
    $this->post("/siswa/{$this->student->id}/dokumen/lain", ['file' => pdf()])->assertNotFound();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('kegagalan menyimpan baris tidak meninggalkan berkas yatim', function () {
    StudentDocument::creating(fn () => throw new RuntimeException('gagal simpan'));

    expect(fn () => app(StudentDocumentService::class)->store($this->student, 'kk', pdf(), $this->op))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('unduh memeriksa izin dan scope, serta mencatat log', function () {
    $this->actingAs($this->op);
    $this->post("/siswa/{$this->student->id}/dokumen/akta_lahir", ['file' => pdf()]);

    $res = $this->get("/siswa/{$this->student->id}/dokumen/akta_lahir")->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('akta_lahir-0012345678.pdf')
        ->and(Activity::where('description', 'dokumen.diunduh')->count())->toBe(1);

    $tanpaIzin = makeUserWith(['siswa.lihat'], $this->school, true, 'tanpa-dok');
    $this->actingAs($tanpaIzin)->get("/siswa/{$this->student->id}/dokumen/akta_lahir")->assertForbidden();

    $sekolahLain = School::factory()->create();
    $this->actingAs(makeUserWith(['siswa.lihat', 'dokumen.lihat'], $sekolahLain, true, 'op-lain'))
        ->get("/siswa/{$this->student->id}/dokumen/akta_lahir")->assertNotFound();
});

test('hapus dokumen menghapus berkas dan unduhnya menjadi 404', function () {
    $this->actingAs($this->op);
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => pdf()]);
    $path = $this->student->documents()->first()->path;

    $this->delete("/siswa/{$this->student->id}/dokumen/kk")->assertRedirect();
    Storage::disk('local')->assertMissing($path);
    $this->get("/siswa/{$this->student->id}/dokumen/kk")->assertNotFound();
});

test('menghapus siswa menghapus berkas dokumennya', function () {
    $this->actingAs(makeUserWith(['siswa.lihat', 'siswa.hapus', 'dokumen.unggah'], $this->school, true, 'op-hapus'));
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => pdf()]);

    $this->delete("/siswa/{$this->student->id}");

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('detail siswa menyertakan dokumen hanya bagi pemilik dokumen.lihat', function () {
    $this->actingAs($this->op);
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => pdf()]);

    $this->get("/siswa/{$this->student->id}")->assertInertia(fn ($p) => $p
        ->has('documents', 1)->where('documents.0.type', 'kk')->where('documents.0.name', 'kk-0012345678.pdf')
        ->where('eligibility.eligible', false));

    $this->actingAs(makeUserWith(['siswa.lihat'], $this->school, true, 'tanpa-dok2'))
        ->get("/siswa/{$this->student->id}")->assertInertia(fn ($p) => $p->has('documents', 0));
});
