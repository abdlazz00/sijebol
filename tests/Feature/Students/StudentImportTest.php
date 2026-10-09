<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;

/** Membuat berkas xlsx; kolom NIK dan NISN dipaksa bertipe teks seperti pada template. */
function xlsx(array $rows, array $heading = ['NIK', 'NISN', 'Nama', 'Tanggal lahir', 'Jenis kelamin'], bool $teks = true): UploadedFile
{
    $book = new Spreadsheet;
    $ws = $book->getActiveSheet();
    $ws->fromArray([$heading, ...$rows], null, 'A1', true);

    if ($teks) {
        foreach ($ws->getRowIterator(2) as $row) {
            foreach (['A', 'B'] as $col) {
                $cell = $ws->getCell($col.$row->getRowIndex());
                $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
            }
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/** Impor terakhir yang tersimpan; queue sync memproses job di dalam request. */
function lastImport(): StudentImport
{
    return StudentImport::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['siswa.lihat', 'siswa.impor'], $this->school, true, 'op-impor');
});

test('sebagian baris gagal: yang benar masuk, yang salah dilaporkan dengan nomor baris', function () {
    $file = xlsx([
        ['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L'],
        ['123', '0012345679', 'NIK Salah', '2009-05-01', 'P'],
        ['3201123412341235', '0012345680', 'Siti', '02/06/2009', 'Perempuan'],
        ['3201123412341234', '0012345681', 'Ganda', '2009-05-01', 'L'],
    ]);

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => $file])
        ->assertRedirect()->assertSessionHas('import.id');

    expect(lastImport()->imported)->toBe(2)
        ->and(lastImport()->failed)->toBe([
            ['row' => 3, 'reason' => 'NIK harus 16 digit angka.'],
            ['row' => 5, 'reason' => 'NIK ganda di berkas.'],
        ]);
    expect(Student::count())->toBe(2)
        ->and(Student::where('name', 'Siti')->first()->birth_date->toDateString())->toBe('2009-06-02');
});

test('NIK bertipe angka ditolak, NISN angka dipulihkan dengan nol di depan', function () {
    $file = xlsx([
        [3.2011234123412E+15, 12345678, 'Angka', '2009-05-01', 'L'],
        ['3201123412341299', 12345678, 'NISN Angka', '2009-05-01', 'L'],
    ], teks: false);

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => $file]);

    expect(lastImport()->imported)->toBe(1)
        ->and(lastImport()->failed)->toBe([['row' => 2, 'reason' => 'NIK harus diisi sebagai teks (format kolom NIK = Teks).']]);

    expect(Student::first()->nisn)->toBe('0012345678');
});

test('tanggal bernomor serial Excel dan baris kosong ditangani', function () {
    $file = xlsx([
        ['3201123412341234', '0012345678', 'Serial', 40000, 'L'],
        [null, null, null, null, null],
        ['3201123412341235', '0012345679', 'Setelah Kosong', '2009-05-01', 'P'],
    ]);

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => $file]);

    expect(lastImport()->imported)->toBe(2)->and(lastImport()->failed)->toBe([]);

    expect(Student::where('name', 'Serial')->first()->birth_date->toDateString())->toBe('2009-07-06');
});

test('kolom wajib hilang, lebih dari 500 baris, dan berkas bukan xlsx ditolak', function () {
    $this->actingAs($this->op);
    $this->post('/siswa/impor', ['file' => xlsx([['3201123412341234', 'Budi']], ['NIK', 'Nama'])])
        ->assertSessionHasErrors('file');

    $baris = array_map(
        fn ($i) => ['32'.str_pad((string) $i, 14, '0', STR_PAD_LEFT), str_pad((string) $i, 10, '0', STR_PAD_LEFT), "S$i", '2009-05-01', 'L'],
        range(1, 501),
    );
    $this->post('/siswa/impor', ['file' => xlsx($baris)])->assertSessionHasErrors('file');

    $this->post('/siswa/impor', ['file' => UploadedFile::fake()->create('a.txt', 1, 'text/plain')])->assertSessionHasErrors('file');
    expect(Student::count())->toBe(0);
});

test('operator tidak bisa menyuntikkan sekolah lain; staf wajib memilih sekolah', function () {
    $lain = School::factory()->create();
    $baris = [['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L']];

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => xlsx($baris), 'school_id' => $lain->id]);
    expect(Student::withoutGlobalScopes()->first()->school_id)->toBe($this->school->id);

    Student::withoutGlobalScopes()->delete();
    $staf = makeUserWith(['siswa.impor'], roleName: 'staf-impor');
    $this->actingAs($staf)->post('/siswa/impor', ['file' => xlsx($baris)])->assertSessionHasErrors('school_id');
    $this->actingAs($staf)->post('/siswa/impor', ['file' => xlsx($baris), 'school_id' => $lain->id])->assertSessionHasNoErrors();
    expect(Student::withoutGlobalScopes()->first()->school_id)->toBe($lain->id);
});

test('satu log ringkas per impor dan template dapat diunduh dengan judul yang benar', function () {
    $this->actingAs($this->op)->post('/siswa/impor', ['file' => xlsx([['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L']])]);

    expect(Activity::where('description', 'siswa.diimpor')->count())->toBe(1)
        ->and(Activity::where('log_name', 'siswa')->where('event', 'created')->count())->toBe(0);

    $res = $this->get('/siswa/impor/template')->assertOk();
    $sheet = IOFactory::load($res->baseResponse->getFile()->getPathname());
    expect($sheet->getActiveSheet()->rangeToArray('A1:E1')[0])->toBe(['NIK', 'NISN', 'Nama', 'Tanggal lahir', 'Jenis kelamin']);
});

test('berkas sangat besar dibaca terbatas dan tetap ditolak sebagai lebih dari 500 baris', function () {
    $baris = array_map(
        fn ($i) => ['32'.str_pad((string) $i, 14, '0', STR_PAD_LEFT), str_pad((string) $i, 10, '0', STR_PAD_LEFT), "S$i", '2009-05-01', 'L'],
        range(1, 1200),
    );

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => xlsx($baris)])->assertSessionHasErrors('file');

    expect(Student::count())->toBe(0);
});

test('tepat 500 baris diterima', function () {
    $baris = array_map(
        fn ($i) => ['32'.str_pad((string) $i, 14, '0', STR_PAD_LEFT), str_pad((string) $i, 10, '0', STR_PAD_LEFT), "S$i", '2009-05-01', 'L'],
        range(1, 500),
    );

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => xlsx($baris)]);

    expect(lastImport()->imported)->toBe(500)->and(lastImport()->failed)->toBe([]);
});

test('berkas bernama xlsx tetapi isinya bukan xlsx ditolak', function () {
    $this->actingAs($this->op);
    // Berkas sungguhan: UploadedFile::fake() menebak MIME dari nama sehingga tidak bisa menguji penyamaran.
    $path = tempnam(sys_get_temp_dir(), 'imp');
    file_put_contents($path, "nik,nisn,nama\n1,2,Budi\n");
    $palsu = new UploadedFile($path, 'siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->post('/siswa/impor', ['file' => $palsu])->assertSessionHasErrors('file');

    expect(Student::count())->toBe(0);
});
