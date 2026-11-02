<?php

namespace App\Domains\Students\Http\Requests;

use App\Domains\Students\Support\TurnstileVerifier;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentSignupRequest extends FormRequest
{
    private const TURNSTILE_MESSAGE = 'Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $turnstile = app(TurnstileVerifier::class);

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'turnstile' => Rule::when($turnstile->enabled(), [
                'bail',
                'required',
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => $turnstile->passes($value, $this->ip()) || $fail(self::TURNSTILE_MESSAGE),
            ]),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'turnstile.required' => self::TURNSTILE_MESSAGE,
            'turnstile.string' => self::TURNSTILE_MESSAGE,
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 150 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
        ];
    }
}
