<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Http\UploadedFile;
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

beforeEach(function () {
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
        ->assertRedirect()->assertSessionHas('import_result', fn ($r) => $r['imported'] === 2
            && $r['failed'] === [
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

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => $file])
        ->assertSessionHas('import_result', fn ($r) => $r['imported'] === 1
            && $r['failed'] === [['row' => 2, 'reason' => 'NIK harus diisi sebagai teks (format kolom NIK = Teks).']]);

    expect(Student::first()->nisn)->toBe('0012345678');
});

test('tanggal bernomor serial Excel dan baris kosong ditangani', function () {
    $file = xlsx([
        ['3201123412341234', '0012345678', 'Serial', 40000, 'L'],
        [null, null, null, null, null],
        ['3201123412341235', '0012345679', 'Setelah Kosong', '2009-05-01', 'P'],
    ]);

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => $file])
        ->assertSessionHas('import_result', fn ($r) => $r['imported'] === 2 && $r['failed'] === []);

    expect(Student::where('name', 'Serial')->first()->birth_date->toDateString())->toBe('2009-07-06');
});

test('kolom wajib hilang, lebih dari 500 baris, dan berkas bukan xlsx ditolak', function () {
    $this->actingAs($this->op);
    $this->post('/siswa/impor', ['file' => xlsx([['3201123412341234', 'Budi']], ['NIK', 'Nama'])])
        ->assertSessionHasErrors('file');

    $baris = array_map(
        fn ($i) => [str_pad((string) $i, 16, '3', STR_PAD_LEFT), str_pad((string) $i, 10, '0', STR_PAD_LEFT), "S$i", '2009-05-01', 'L'],
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
