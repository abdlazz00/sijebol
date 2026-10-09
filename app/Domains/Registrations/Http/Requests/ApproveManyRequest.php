<?php

namespace App\Domains\Registrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveManyRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entry_ids' => ['required', 'array', 'min:1', 'max:50'],
            'entry_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'entry_ids.required' => 'Pilih minimal satu siswa.',
            'entry_ids.min' => 'Pilih minimal satu siswa.',
            'entry_ids.max' => 'Maksimal 50 siswa sekali setujui.',
        ];
    }
}
