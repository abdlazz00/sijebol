<?php

namespace App\Domains\Access\Http\Requests;

use App\Domains\Access\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Satu kelas untuk tambah dan ubah; `$this->route('user')` kosong saat tambah. */
class UserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $scoped = (bool) Role::find($this->input('role_id'))?->is_school_scoped;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'regex:/^08[0-9]{8,12}$/'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'school_id' => [
                'nullable', 'integer', 'exists:schools,id',
                Rule::requiredIf($scoped),
                Rule::prohibitedIf(! $scoped),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'phone.regex' => 'Nomor HP harus diawali 08 dan berisi 10–14 digit.',
            'school_id.required' => 'Role ini terikat sekolah; pilih sekolah.',
            'school_id.prohibited' => 'Role ini tidak terikat sekolah; kosongkan sekolah.',
        ];
    }
}
