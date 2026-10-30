<?php

namespace App\Domains\Students\Http\Requests;

use App\Domains\Students\Support\StudentRules;
use Illuminate\Foundation\Http\FormRequest;

/** Koreksi data siswa oleh staf: catatan alasan wajib; sekolah hanya boleh diganti oleh pelaku tanpa sekolah. */
class StudentCorrectionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...StudentRules::fields($this->route('student')?->id),
            // Pelaku yang terikat sekolah: nilai apa pun diabaikan tanpa diperiksa keberadaannya.
            'school_id' => $this->user()->school_id === null ? ['nullable', 'integer', 'exists:schools,id'] : ['nullable'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...StudentRules::messages(),
            'note.required' => 'Catatan koreksi wajib diisi (minimal 5 karakter).',
            'note.min' => 'Catatan koreksi wajib diisi (minimal 5 karakter).',
            'note.max' => 'Catatan koreksi maksimal 500 karakter.',
        ];
    }
}
