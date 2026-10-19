<?php

namespace App\Domains\Students\Support;

use Illuminate\Validation\Rule;

final class StudentRules
{
    public const DOCUMENT_TYPES = ['kk', 'akta_lahir'];

    /** @return array<string, array<int, mixed>> */
    public static function fields(?int $ignoreId = null): array
    {
        return [
            'nik' => ['required', 'digits:16', Rule::unique('students', 'nik')->ignore($ignoreId)],
            'nisn' => ['required', 'digits:10'],
            'name' => ['required', 'string', 'max:150'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['L', 'P'])],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK ini sudah terdaftar pada akun lain. Bila menurut Anda keliru, hubungi operator sekolah atau admin kecamatan.',
            'nisn.required' => 'NISN wajib diisi.',
            'nisn.digits' => 'NISN harus 10 digit angka.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date' => 'Tanggal lahir tidak valid.',
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
            'gender.required' => 'Pilih jenis kelamin.',
            'gender.in' => 'Pilih jenis kelamin.',
        ];
    }
}
