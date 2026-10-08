<?php

namespace App\Domains\Students\Http\Requests;

use App\Domains\Students\Support\StudentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Satu kelas untuk tambah dan ubah; `$this->route('student')` kosong saat tambah. */
class StudentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...StudentRules::fields($this->route('student')?->id),
            // Operator sekolah dikunci ke sekolahnya: nilai apa pun diabaikan tanpa diperiksa keberadaannya.
            'school_id' => $this->user()->school_id === null
                ? ['nullable', 'integer', 'exists:schools,id', Rule::requiredIf(fn () => $this->isMethod('post'))]
                : ['nullable'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...StudentRules::messages(), 'school_id.required' => 'Pilih sekolah.'];
    }
}
