<?php

namespace App\Domains\Audit\Services;

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
            $out[$key] = $key === 'nik' && is_string($value) ? self::maskNik($value) : $value;
        }

        return $out;
    }

    private static function maskNik(string $nik): string
    {
        $length = strlen($nik);

        return $length <= 8
            ? str_repeat('*', $length)
            : substr($nik, 0, 4).str_repeat('*', $length - 8).substr($nik, -4);
    }
}
