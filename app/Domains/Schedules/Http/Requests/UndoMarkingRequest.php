<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UndoMarkingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['note.required' => 'Catatan wajib diisi untuk membatalkan penandaan.'];
    }
}
