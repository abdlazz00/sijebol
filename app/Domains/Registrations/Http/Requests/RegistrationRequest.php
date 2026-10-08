<?php

namespace App\Domains\Registrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Satu kelas untuk buat dan ubah draf; `$this->route('registration')` kosong saat buat. */
class RegistrationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schoolId = $this->route('registration')->school_id ?? $this->user()->school_id ?? $this->input('school_id');

        return [
            'title' => ['required', 'string', 'max:150'],
            'expected_count' => ['nullable', 'integer', 'min:1', 'max:5000'],
            // Operator sekolah dikunci ke sekolahnya; hanya staf memilih sekolah, dan hanya saat buat.
            'school_id' => $this->user()->school_id === null && $this->isMethod('post')
                ? ['required', 'integer', 'exists:schools,id']
                : ['nullable'],
            'student_ids' => ['present', 'array'],
            'student_ids.*' => ['integer', 'distinct', Rule::exists('students', 'id')->where('school_id', $schoolId)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul pendaftaran wajib diisi.',
            'school_id.required' => 'Pilih sekolah.',
            'student_ids.*.exists' => 'Siswa tidak ditemukan di sekolah ini.',
        ];
    }
}
