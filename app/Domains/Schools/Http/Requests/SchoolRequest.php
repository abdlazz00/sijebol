<?php

namespace App\Domains\Schools\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['required', 'digits:8', Rule::unique('schools', 'npsn')->ignore($this->route('school'))],
            'level' => ['required', Rule::in(['SMA', 'SMK', 'MA'])],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
