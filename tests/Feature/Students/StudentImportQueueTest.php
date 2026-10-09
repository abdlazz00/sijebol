<?php

use App\Domains\Notifications\Notifications\StudentImportFinishedNotification;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Jobs\ImportStudentsJob;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;

/** Berkas xlsx sungguhan dengan kolom NIK dan NISN bertipe teks. */
function queueXlsx(array $rows): UploadedFile
{
    $book = new Spreadsheet;
    $ws = $book->getActiveSheet();
    $ws->fromArray([['NIK', 'NISN', 'Nama', 'Tanggal lahir', 'Jenis kelamin'], ...$rows], null, 'A1', true);
    foreach ($ws->getRowIterator(2) as $row) {
        foreach (['A', 'B'] as $col) {
            $cell = $ws->getCell($col.$row->getRowIndex());
            $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['siswa.lihat', 'siswa.impor'], $this->school, true, 'op-antri');
    $this->baris = [
        ['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L'],
        ['123', '0012345679', 'NIK Salah', '2009-05-01', 'P'],
    ];
});

test('impor diproses job: hasil tersimpan, berkas dihapus, satu log dengan pembuat sebagai penyebab', function () {
    $this->actingAs($this->op)->post('/siswa/impor', ['file' => queueXlsx($this->baris)])
        ->assertRedirect()->assertSessionHas('import.id');

    $import = StudentImport::query()->sole();
    expect($import->status)->toBe(StudentImport::SELESAI)
        ->and($import->imported)->toBe(1)
        ->and($import->failed)->toBe([['row' => 3, 'reason' => 'NIK harus 16 digit angka.']])
        ->and($import->path)->toBeNull()
        ->and($import->started_at)->not->toBeNull()->and($import->finished_at)->not->toBeNull()
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([])
        ->and(Student::count())->toBe(1);

    $log = Activity::where('description', 'siswa.diimpor')->sole();
    expect($log->causer_id)->toBe($this->op->id);
});

test('pra-periksa menolak berkas bermasalah seketika tanpa baris impor dan tanpa berkas tersimpan', function () {
    $this->actingAs($this->op);
    $lebih = array_map(
        fn ($i) => ['32'.str_pad((string) $i, 14, '0', STR_PAD_LEFT), str_pad((string) $i, 10, '0', STR_PAD_LEFT), "S$i", '2009-05-01', 'L'],
        range(1, 501),
    );

    $this->post('/siswa/impor', ['file' => queueXlsx($lebih)])->assertSessionHasErrors('file');

    $staf = makeUserWith(['siswa.impor'], roleName: 'staf-antri');
    $this->actingAs($staf)->post('/siswa/impor', ['file' => queueXlsx($this->baris)])->assertSessionHasErrors('school_id');

    expect(StudentImport::count())->toBe(0)->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

test('notifikasi selesai dikirim ke pembuat dengan jumlah dan tautan hasil', function () {
    Notification::fake();

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => queueXlsx($this->baris)]);

    $import = StudentImport::query()->sole();
    Notification::assertSentTo($this->op, StudentImportFinishedNotification::class, fn (StudentImportFinishedNotification $n) => $n->title() === 'Impor siswa selesai'
        && $n->body() === '1 siswa ditambahkan, 1 baris gagal.'
        && $n->url() === "/siswa?impor={$import->id}");
});

test('kegagalan job menandai gagal, menghapus berkas, dan memberi tahu pembuat', function () {
    Notification::fake();
    Storage::disk('local')->put('imports/rusak.xlsx', 'bukan xlsx');
    $import = StudentImport::create([
        'user_id' => $this->op->id, 'school_id' => $this->school->id, 'original_name' => 'rusak.xlsx', 'path' => 'imports/rusak.xlsx',
    ]);

    // Pada queue sync, kegagalan memanggil failed() lalu melempar ulang ke pemanggil.
    expect(fn () => ImportStudentsJob::dispatchSync($import->id))->toThrow(Exception::class);

    $import->refresh();
    expect($import->status)->toBe(StudentImport::GAGAL)
        ->and($import->error)->not->toBeNull()->and($import->path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
    Notification::assertSentTo($this->op, StudentImportFinishedNotification::class, fn (StudentImportFinishedNotification $n) => $n->title() === 'Impor siswa gagal');
});

test('hasil impor hanya untuk pembuat dan staf kecamatan; operator lain 404', function () {
    $this->actingAs($this->op)->post('/siswa/impor', ['file' => queueXlsx($this->baris)]);
    $import = StudentImport::query()->sole();

    $this->get("/siswa/impor/{$import->id}")->assertOk()->assertJson([
        'id' => $import->id, 'status' => 'selesai', 'imported' => 1, 'failed' => [['row' => 3, 'reason' => 'NIK harus 16 digit angka.']], 'error' => null,
    ]);

    $staf = makeUserWith(['siswa.impor'], roleName: 'staf-hasil');
    $this->actingAs($staf)->get("/siswa/impor/{$import->id}")->assertOk();

    $lain = makeUserWith(['siswa.impor'], School::factory()->create(), true, 'op-lain-impor');
    $this->actingAs($lain)->get("/siswa/impor/{$import->id}")->assertNotFound();
});

test('daftar siswa memuat recent_import hanya bila berhak', function () {
    $this->actingAs($this->op)->post('/siswa/impor', ['file' => queueXlsx($this->baris)]);
    $import = StudentImport::query()->sole();

    $this->get("/siswa?impor={$import->id}")->assertInertia(fn (Assert $p) => $p
        ->where('recent_import.id', $import->id)->where('recent_import.status', 'selesai'));
    $this->get('/siswa')->assertInertia(fn (Assert $p) => $p->where('recent_import', null));

    $lain = makeUserWith(['siswa.lihat', 'siswa.impor'], School::factory()->create(), true, 'op-lain-impor2');
    $this->actingAs($lain)->get("/siswa?impor={$import->id}")->assertInertia(fn (Assert $p) => $p->where('recent_import', null));
});
