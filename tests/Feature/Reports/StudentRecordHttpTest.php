<?php

use App\Domains\Access\Models\Role;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Reports\Exports\StudentRecordExport;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->sekolah = School::factory()->create(['name' => 'SMAN 1']);
    $this->pelapor = makeUserWith(['laporan.rekaman'], roleName: 'pelapor-rekaman');
});

/** Siswa lahir pada tanggal tertentu, bersesi 2026-10-20 dengan status tertentu (tanpa status = tanpa pendaftaran). */
function siswaLaporan(School $sekolah, string $lahir, ?S $status = null, array $atribut = []): Student
{
    $siswa = Student::factory()->for($sekolah)->create(['birth_date' => $lahir, ...$atribut]);
    if ($status !== null) {
        makeEntry($status, $sekolah, $siswa, makeSchedule($sekolah, '2026-10-20'));
    }

    return $siswa;
}

test('halaman memuat kelompok, baris tanpa NIK, dan pilihan filter', function () {
    siswaLaporan($this->sekolah, '2009-10-19', S::SudahRekaman, ['name' => 'Aziz', 'nik' => '3201123412341234']);
    siswaLaporan($this->sekolah, '2009-10-20', S::SudahRekaman);
    siswaLaporan($this->sekolah, '2010-05-05');

    $this->actingAs($this->pelapor)->get('/reports/students')->assertInertia(fn (Assert $p) => $p
        ->component('Laporan/Siswa')
        ->where('groups', [
            ['key' => 'langsung', 'label' => 'Langsung terbit', 'count' => 1],
            ['key' => 'menunggu', 'label' => 'Menunggu genap 17', 'count' => 1],
            ['key' => 'belum', 'label' => 'Belum rekaman', 'count' => 1],
        ])
        ->has('records.data', 3)
        ->where('records.data.0.name', 'Aziz')
        ->where('records.data.0.group', 'langsung')
        ->where('records.data.0.school', 'SMAN 1')
        ->missing('records.data.0.nik')->missing('records.data.0.birth_date')->missing('records.data.0.gender')
        ->has('schools', 1)->has('schedules', 2));
});

test('NIK penuh maupun yang termasking tidak ada di halaman', function () {
    siswaLaporan($this->sekolah, '2009-10-19', S::SudahRekaman, ['nik' => '3201123412341234']);

    $this->actingAs($this->pelapor)->get('/reports/students')
        ->assertDontSee('3201123412341234', false)
        ->assertDontSee('3201********1234', false);
});

test('halaman memakai filter, dan baris dipaginasi 25 per halaman', function () {
    siswaLaporan($this->sekolah, '2010-05-05', null, ['name' => 'Andi']);
    siswaLaporan($this->sekolah, '2009-10-19', S::SudahRekaman, ['name' => 'Zaki']);

    $this->actingAs($this->pelapor)->get('/reports/students?filter[group]=belum')->assertInertia(fn (Assert $p) => $p
        ->has('records.data', 1)->where('records.data.0.name', 'Andi')
        ->where('groups.0.count', 1)->where('groups.2.count', 1)->where('filters.group', 'belum'));

    Student::factory()->for($this->sekolah)->count(30)->create();
    $this->get('/reports/students?page=2')->assertInertia(fn (Assert $p) => $p
        ->where('records.current_page', 2)->where('records.per_page', 25)->has('records.data', 7));
});

test('tanpa laporan.rekaman ditolak', function () {
    $this->actingAs(makeUserWith(['laporan.lihat'], roleName: 'hanya-rekap'))->get('/reports/students')->assertForbidden();
});

test('pengguna terikat sekolah hanya melihat sekolahnya, tanpa pilihan sekolah', function () {
    $lain = School::factory()->create(['name' => 'SMAN 2']);
    siswaLaporan($this->sekolah, '2010-05-05', null, ['name' => 'Milik A']);
    siswaLaporan($lain, '2010-05-05', null, ['name' => 'Milik B']);
    $operator = makeUserWith(['laporan.rekaman'], $this->sekolah, true, 'op-laporan');

    $this->actingAs($operator)->get('/reports/students')->assertInertia(fn (Assert $p) => $p
        ->has('records.data', 1)->where('records.data.0.name', 'Milik A')->where('schools', null));

    $this->get("/reports/students?filter[school_id]={$lain->id}")->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
});

test('filter tidak valid ditolak', function () {
    $this->actingAs($this->pelapor)->from('/reports/students');

    $this->get('/reports/students?filter[group]=bukan')->assertSessionHasErrors('filter.group');
    $this->get('/reports/students?filter[dari]=2026-10-06&filter[sampai]=2026-10-05')->assertSessionHasErrors('filter.sampai');
    $this->get('/reports/students?filter[dari]=bukan-tanggal')->assertSessionHasErrors('filter.dari');
    $this->get('/reports/students?filter[sampai]=2026-10-05')->assertOk()->assertSessionHasNoErrors();
});

test('izin laporan rekaman dan NIK hanya untuk super-admin dan admin kecamatan secara bawaan', function () {
    $this->seed(AccessSeeder::class);

    foreach (['super-admin', 'admin-kecamatan'] as $nama) {
        expect(Role::findByName($nama)->hasPermissionTo('laporan.rekaman'))->toBeTrue()
            ->and(Role::findByName($nama)->hasPermissionTo('laporan.unduh-nik'))->toBeTrue();
    }
    foreach (['operator-sekolah', 'siswa'] as $nama) {
        expect(Role::findByName($nama)->hasPermissionTo('laporan.rekaman'))->toBeFalse()
            ->and(Role::findByName($nama)->hasPermissionTo('laporan.unduh-nik'))->toBeFalse();
    }
});

test('Excel: judul kolom dan isi tiga kelompok, tanggal rekaman kosong untuk yang belum', function () {
    Excel::fake();
    siswaLaporan($this->sekolah, '2009-10-19', S::SudahRekaman, ['name' => 'Aziz', 'nik' => '3201123412341234', 'nisn' => '0081234567', 'gender' => 'L']);
    siswaLaporan($this->sekolah, '2009-10-20', S::SudahRekaman, ['name' => 'Budi', 'nik' => '3201123412345678', 'nisn' => '0081234568', 'gender' => 'P']);
    siswaLaporan($this->sekolah, '2010-05-05', S::TidakHadir, ['name' => 'Citra', 'nik' => '3201123412349999', 'nisn' => '0081234569', 'gender' => 'P']);

    $this->actingAs($this->pelapor)->get('/reports/students/export');

    Excel::assertDownloaded('daftar-rekaman-ktp-el.xlsx', function (StudentRecordExport $e) {
        $rows = $e->array();

        return $e->headings() === ['Nama', 'NIK', 'NISN', 'Tanggal lahir', 'Jenis kelamin', 'Sekolah', 'Tanggal rekaman', 'KTP-el', 'Terbit mulai', 'Alasan']
            && $rows[0] === ['Aziz', '3201********1234', '0081234567', '2009-10-19', 'Laki-laki', 'SMAN 1', '2026-10-20', 'Langsung terbit', '2026-10-20', '']
            && $rows[1] === ['Budi', '3201********5678', '0081234568', '2009-10-20', 'Perempuan', 'SMAN 1', '2026-10-20', 'Menunggu genap 17', '2026-10-21', '']
            && $rows[2] === ['Citra', '3201********9999', '0081234569', '2010-05-05', 'Perempuan', 'SMAN 1', '', 'Belum rekaman', '2027-05-06', 'Tidak hadir'];
    });
});

test('Excel: NIK penuh hanya untuk pemegang laporan.unduh-nik', function () {
    Excel::fake();
    siswaLaporan($this->sekolah, '2009-10-19', S::SudahRekaman, ['nik' => '3201123412341234']);

    $this->actingAs(makeUserWith(['laporan.rekaman', 'laporan.unduh-nik'], roleName: 'pengunduh-nik'))->get('/reports/students/export');

    Excel::assertDownloaded('daftar-rekaman-ktp-el.xlsx', fn (StudentRecordExport $e) => $e->array()[0][1] === '3201123412341234');
});

test('Excel mengikuti filter dan mencakup semua halaman', function () {
    Excel::fake();
    Student::factory()->for($this->sekolah)->count(30)->create(['birth_date' => '2010-05-05']);
    siswaLaporan($this->sekolah, '2009-10-19', S::SudahRekaman);

    $this->actingAs($this->pelapor)->get('/reports/students/export?filter[group]=belum');

    Excel::assertDownloaded('daftar-rekaman-ktp-el.xlsx', fn (StudentRecordExport $e) => count($e->array()) === 30);
});

test('Excel menetralkan nama dan sekolah berawalan simbol formula', function () {
    Excel::fake();
    $sekolah = School::factory()->create(['name' => '@sekolah']);
    siswaLaporan($sekolah, '2010-05-05', null, ['name' => '=HYPERLINK("http://x")']);

    $this->actingAs($this->pelapor)->get('/reports/students/export');

    Excel::assertDownloaded('daftar-rekaman-ktp-el.xlsx', fn (StudentRecordExport $e) => $e->array()[0][0] === "'=HYPERLINK(\"http://x\")" && $e->array()[0][5] === "'@sekolah");
});

test('Excel mengikat NIK dan NISN sebagai teks, bukan angka', function () {
    $sel = (new Spreadsheet)->getActiveSheet()->getCell('A1');

    (new StudentRecordExport([], false))->bindValue($sel, '3201123412341234');

    expect($sel->getDataType())->toBe(DataType::TYPE_STRING)->and($sel->getValue())->toBe('3201123412341234');
});

test('unduhan sungguhan berupa xlsx dan tercatat di log beserta penanda NIK penuh', function () {
    siswaLaporan($this->sekolah, '2009-10-19', S::SudahRekaman);
    siswaLaporan($this->sekolah, '2010-05-05');
    $pengunduh = makeUserWith(['laporan.rekaman', 'laporan.unduh-nik'], roleName: 'pengunduh-log');

    $this->actingAs($pengunduh)->get('/reports/students/export?filter[group]=belum&filter[dari]=2026-10-01')
        ->assertOk()->assertDownload('daftar-rekaman-ktp-el.xlsx');

    $log = Activity::where('description', 'laporan.diekspor')->sole();
    expect($log->causer_id)->toBe($pengunduh->id)
        ->and($log->properties['jenis'])->toBe('daftar-rekaman')
        ->and($log->properties['nik_penuh'])->toBeTrue()
        ->and($log->properties['jumlah'])->toBe(1)
        ->and($log->properties['filter'])->toEqual(['group' => 'belum', 'dari' => '2026-10-01']);
});

test('ekspor daftar ditolak tanpa laporan.rekaman dan dibatasi lajunya', function () {
    $this->actingAs(makeUserWith(['laporan.lihat'], roleName: 'hanya-rekap-2'))->get('/reports/students/export')->assertForbidden();

    expect(app('router')->getRoutes()->getByName('reports.students.export')->gatherMiddleware())->toContain('throttle:20,1');
});
