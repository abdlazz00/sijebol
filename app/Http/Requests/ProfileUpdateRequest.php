<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^08[0-9]{8,12}$/'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Nomor HP harus diawali 08 dan berisi 10–14 digit.'];
    }
}
