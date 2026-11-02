<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Reports\Services\ReportService;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;

beforeEach(function () {
    $this->service = app(ReportService::class);
    $this->sekolah = School::factory()->create(['name' => 'SMAN 1']);
});

/** Siswa lahir pada tanggal tertentu, dengan pendaftaran berstatus tertentu pada sesi tertentu (tanpa status = tanpa pendaftaran). */
function siswaLahir(School $sekolah, string $lahir, ?S $status = null, string $sesi = '2026-10-20', string $nama = 'Siswa'): Student
{
    $siswa = Student::factory()->for($sekolah)->create(['birth_date' => $lahir, 'name' => $nama]);
    if ($status !== null) {
        makeEntry($status, $sekolah, $siswa, makeSchedule($sekolah, $sesi));
    }

    return $siswa;
}

function barisSiswa(array $hasil, Student $siswa): array
{
    return collect($hasil['rows'])->firstWhere('id', $siswa->id);
}

test('directKtpFrom: ulang tahun ke-17 ditambah satu hari, termasuk lahir 29 Februari', function () {
    $eligibility = app(EligibilityService::class);
    $lahir = fn (string $tanggal) => Student::factory()->make(['birth_date' => $tanggal]);

    expect($eligibility->directKtpFrom($lahir('2009-10-20'))->toDateString())->toBe('2026-10-21')
        ->and($eligibility->directKtpFrom($lahir('2008-02-29'))->toDateString())->toBe('2025-03-01');
});

test('kelompok mengikuti status rekaman dan umur pada tanggal sesi', function () {
    $langsung = siswaLahir($this->sekolah, '2009-10-19', S::SudahRekaman);
    $tepat17 = siswaLahir($this->sekolah, '2009-10-20', S::SudahRekaman);
    $kabisat = siswaLahir($this->sekolah, '2008-02-29', S::SudahRekaman, '2026-10-20');
    $absen = siswaLahir($this->sekolah, '2010-05-05', S::TidakHadir);
    $ditolak = siswaLahir($this->sekolah, '2010-05-05', S::Ditolak);
    $menunggu = siswaLahir($this->sekolah, '2010-05-05', S::MenungguVerifikasi);
    $belumDaftar = siswaLahir($this->sekolah, '2010-05-05');

    $hasil = $this->service->studentRecords([]);

    expect(barisSiswa($hasil, $langsung)['group'])->toBe('langsung')
        ->and(barisSiswa($hasil, $langsung)['reason'])->toBeNull()
        ->and(barisSiswa($hasil, $tepat17)['group'])->toBe('menunggu')
        ->and(barisSiswa($hasil, $tepat17)['eligible_from'])->toBe('2026-10-21')
        ->and(barisSiswa($hasil, $kabisat)['group'])->toBe('langsung')
        ->and(barisSiswa($hasil, $kabisat)['eligible_from'])->toBe('2025-03-01')
        ->and(barisSiswa($hasil, $absen)['group'])->toBe('belum')
        ->and(barisSiswa($hasil, $absen)['reason'])->toBe('Tidak hadir')
        ->and(barisSiswa($hasil, $ditolak)['reason'])->toBe('Ditolak')
        ->and(barisSiswa($hasil, $menunggu)['reason'])->toBe('Menunggu verifikasi')
        ->and(barisSiswa($hasil, $belumDaftar)['group'])->toBe('belum')
        ->and(barisSiswa($hasil, $belumDaftar)['reason'])->toBe('Belum mendaftar sesi')
        ->and(barisSiswa($hasil, $belumDaftar)['session_date'])->toBeNull()
        ->and(barisSiswa($hasil, $langsung)['session_date'])->toBe('2026-10-20');
});

test('pendaftaran terkini yang menentukan, bukan yang lama', function () {
    $siswa = siswaLahir($this->sekolah, '2010-05-05', S::TidakHadir, '2026-09-01');
    makeEntry(S::Terdaftar, $this->sekolah, $siswa, makeSchedule($this->sekolah, '2026-11-20'));

    $baris = barisSiswa($this->service->studentRecords([]), $siswa);

    expect($baris['group'])->toBe('belum')->and($baris['reason'])->toBe('Terdaftar')->and($baris['session_date'])->toBe('2026-11-20');
});

test('jumlah kelompok tidak terpengaruh filter kelompok dan totalnya sama dengan jumlah siswa', function () {
    siswaLahir($this->sekolah, '2009-10-19', S::SudahRekaman);
    siswaLahir($this->sekolah, '2009-10-20', S::SudahRekaman);
    siswaLahir($this->sekolah, '2010-05-05', S::TidakHadir);
    siswaLahir($this->sekolah, '2010-05-05');

    $semua = $this->service->studentRecords([]);
    $belum = $this->service->studentRecords(['group' => 'belum']);

    expect($semua['counts'])->toBe(['langsung' => 1, 'menunggu' => 1, 'belum' => 2])
        ->and($belum['counts'])->toBe($semua['counts'])
        ->and(array_sum($semua['counts']))->toBe(Student::count())
        ->and($belum['rows'])->toHaveCount(2)
        ->and(collect($belum['rows'])->pluck('group')->unique()->all())->toBe(['belum']);
});

test('rentang tanggal menyaring tanggal sesi, siswa tanpa pendaftaran tetap tampil', function () {
    $oktober = siswaLahir($this->sekolah, '2010-05-05', S::TidakHadir, '2026-10-20');
    $november = siswaLahir($this->sekolah, '2010-05-05', S::TidakHadir, '2026-11-20');
    $tanpa = siswaLahir($this->sekolah, '2010-05-05');

    $ids = fn (array $f) => collect($this->service->studentRecords($f)['rows'])->pluck('id')->sort()->values()->all();
    $urut = fn (Student ...$s) => collect($s)->pluck('id')->sort()->values()->all();

    expect($ids(['dari' => '2026-11-01']))->toBe($urut($november, $tanpa))
        ->and($ids(['sampai' => '2026-10-31']))->toBe($urut($oktober, $tanpa))
        ->and($ids(['dari' => '2026-10-20', 'sampai' => '2026-10-20']))->toBe($urut($oktober, $tanpa));
});

test('filter sesi, sekolah, dan pencarian', function () {
    $lain = School::factory()->create();
    $a = siswaLahir($this->sekolah, '2010-05-05', S::Terdaftar, '2026-10-20', 'Abdul Aziz');
    $b = siswaLahir($lain, '2010-05-05', S::Terdaftar, '2026-10-21', 'Budi Santoso');
    $a->update(['nisn' => '0081234567', 'nik' => '3201123412341234']);
    $sesiA = $a->registrationEntries()->first()->schedule_id;
    $ids = fn (array $f) => collect($this->service->studentRecords($f)['rows'])->pluck('id')->all();

    expect($ids(['schedule_id' => $sesiA]))->toBe([$a->id])
        ->and($ids(['school_id' => $lain->id]))->toBe([$b->id])
        ->and($ids(['search' => 'aziz']))->toBe([$a->id])
        ->and($ids(['search' => '00812']))->toBe([$a->id])
        ->and($ids(['search' => '3201123412']))->toBe([]);
});

test('urutan: kelompok lalu nama', function () {
    siswaLahir($this->sekolah, '2010-05-05', null, nama: 'Andi');
    siswaLahir($this->sekolah, '2009-10-19', S::SudahRekaman, nama: 'Zaki');
    siswaLahir($this->sekolah, '2009-10-20', S::SudahRekaman, nama: 'Budi');

    expect(collect($this->service->studentRecords([])['rows'])->pluck('name')->all())->toBe(['Zaki', 'Budi', 'Andi']);
});
