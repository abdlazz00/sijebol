<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password') + ['is_active' => true];
        $credentials[] = fn (Builder $query) => $query->whereNotNull('activated_at');

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey(), (int) config('security.login_ip_window'));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Hitungan per IP sengaja tidak direset: penyerang bisa login ke akun miliknya untuk membersihkannya.
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $blockedKey = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->ipThrottleKey(), (int) config('security.login_ip_failures')) => $this->ipThrottleKey(),
            default => null,
        };

        if ($blockedKey === null) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($blockedKey);

        throw ValidationException::withMessages([
            'throttle' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /** Kegagalan dari satu IP dengan email apa pun (password spraying), terpisah dari batas per email|IP. */
    private function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }
}
