<?php

namespace App\Domains\Audit\Services;

use App\Support\Nik;

final class ActivityPropertyMasker
{
    private const DROP = ['password', 'password_confirmation', 'token', 'remember_token'];

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function apply(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (in_array($key, self::DROP, true)) {
                continue;
            }
            $out[$key] = $key === 'nik' && is_string($value) ? Nik::mask($value) : $value;
        }

        return $out;
    }
}
