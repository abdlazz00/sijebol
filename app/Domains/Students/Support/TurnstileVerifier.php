<?php

namespace App\Domains\Students\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/** Verifikasi Cloudflare Turnstile untuk formulir publik. Aktif hanya bila secret terisi. */
class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function enabled(): bool
    {
        return filled(config('services.turnstile.secret'));
    }

    /** Gagal tertutup: galat jaringan atau respons selain success=true berarti ditolak. */
    public function passes(?string $token, ?string $ip): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            return Http::asForm()->timeout(5)->post(self::ENDPOINT, [
                'secret' => config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ])->json('success') === true;
        } catch (Throwable) {
            return false;
        }
    }
}
