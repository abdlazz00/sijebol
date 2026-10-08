<?php

namespace App\Domains\Registrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'fix', 'reject'])],
            'note' => ['nullable', 'string', 'max:500', Rule::requiredIf(fn () => in_array($this->input('decision'), ['fix', 'reject'], true))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.required' => 'Pilih keputusan.',
            'decision.in' => 'Pilih keputusan.',
            'note.required' => 'Catatan wajib diisi untuk keputusan ini.',
        ];
    }
}
