<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Access\Services\ActivationService;

class StudentSignupNotification extends BaseNotification
{
    public function __construct(private readonly string $token, private readonly string $school) {}

    public function title(): string
    {
        return 'Aktifkan akun SIJEBOL';
    }

    public function body(): string
    {
        return "Anda mendaftar akun SIJEBOL sebagai siswa {$this->school}. Aktifkan akun dan buat password lewat tombol di bawah; link berlaku "
            .ActivationService::TTL_DAYS.' hari. Abaikan email ini bila Anda tidak mendaftar.';
    }

    public function url(): string
    {
        return "/aktivasi/{$this->token}";
    }
}
