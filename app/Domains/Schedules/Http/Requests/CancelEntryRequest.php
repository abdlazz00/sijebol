<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelEntryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:5', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => 'Catatan wajib diisi.',
            'note.min' => 'Catatan minimal 5 karakter.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
