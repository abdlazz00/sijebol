<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['sudah_rekaman', 'tidak_hadir', 'belum_rekaman'])],
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer', 'distinct'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status penandaan.',
            'status.in' => 'Pilih status penandaan.',
            'entry_ids.required' => 'Pilih minimal satu siswa.',
            'entry_ids.min' => 'Pilih minimal satu siswa.',
        ];
    }
}
