<?php

namespace App\Domains\Access\Enums;

enum UserStatus: string
{
    case Aktif = 'aktif';
    case Menunggu = 'menunggu';
    case Kedaluwarsa = 'kedaluwarsa';
    case Nonaktif = 'nonaktif';
}
