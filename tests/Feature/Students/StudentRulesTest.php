<?php

use App\Domains\Students\Models\Student;
use App\Domains\Students\Support\StudentRules;
use App\Support\Nik;
use Illuminate\Support\Facades\Validator;

function validasi(array $data, ?int $ignore = null): Illuminate\Validation\Validator
{
    return Validator::make($data, StudentRules::fields($ignore), StudentRules::messages());
}

function dataSiswa(array $override = []): array
{
    return [...['nik' => '3201123412341234', 'nisn' => '0012345678', 'name' => 'Budi', 'birth_date' => '2009-05-01', 'gender' => 'L'], ...$override];
}

test('data lengkap valid', function () {
    expect(validasi(dataSiswa())->passes())->toBeTrue();
});

test('NIK harus 16 digit dan unik, pesan tidak menyebut sekolah', function () {
    expect(validasi(dataSiswa(['nik' => '320112341234123']))->errors()->first('nik'))->toBe('NIK harus 16 digit angka.')
        ->and(validasi(dataSiswa(['nik' => '32011234123412345']))->passes())->toBeFalse();

    Student::factory()->create(['nik' => '3201123412341234']);
    expect(validasi(dataSiswa())->errors()->first('nik'))->toBe('NIK ini sudah terdaftar pada akun lain. Bila menurut Anda keliru, hubungi operator sekolah atau admin kecamatan.');
});

test('mengabaikan siswa sendiri saat ubah', function () {
    $s = Student::factory()->create(['nik' => '3201123412341234']);
    expect(validasi(dataSiswa(), $s->id)->passes())->toBeTrue();
});

test('NISN, tanggal lahir masa depan, dan gender tak valid ditolak', function () {
    expect(validasi(dataSiswa(['nisn' => '123']))->errors()->first('nisn'))->toBe('NISN harus 10 digit angka.')
        ->and(validasi(dataSiswa(['birth_date' => now()->addDay()->toDateString()]))->errors()->has('birth_date'))->toBeTrue()
        ->and(validasi(dataSiswa(['gender' => 'X']))->errors()->first('gender'))->toBe('Pilih jenis kelamin.');
});

test('Nik::mask menyisakan 4 awal dan 4 akhir', function () {
    expect(Nik::mask('3201123412341234'))->toBe('3201********1234')
        ->and(Nik::mask('12345'))->toBe('*****');
});
