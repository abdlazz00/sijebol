<?php

namespace App\Support;

final class Nik
{
    /** "3201123412341234" → "3201********1234"; NIK pendek dimasker penuh. */
    public static function mask(string $nik): string
    {
        $length = strlen($nik);

        return $length <= 8
            ? str_repeat('*', $length)
            : substr($nik, 0, 4).str_repeat('*', $length - 8).substr($nik, -4);
    }
}
