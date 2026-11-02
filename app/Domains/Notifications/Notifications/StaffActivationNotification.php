<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Access\Services\ActivationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Str;

/**
 * Email aktivasi untuk staf. Tanpa kanal database agar link yang sudah mati tidak tersisa di lonceng;
 * dienkripsi di antrean karena memuat token aktivasi mentah.
 */
class StaffActivationNotification extends BaseNotification implements ShouldBeEncrypted
{
    public function __construct(
        private readonly string $token,
        private readonly ?string $role,
        private readonly ?string $school,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function title(): string
    {
        return 'Aktifkan akun SIJEBOL';
    }

    public function body(): string
    {
        $sebagai = collect([$this->role ? Str::headline($this->role) : null, $this->school])->filter()->implode(' · ');

        return 'Akun SIJEBOL telah dibuatkan untuk Anda'.($sebagai !== '' ? " sebagai {$sebagai}" : '')
            .'. Aktifkan akun dan buat kata sandi lewat tombol di bawah; link berlaku '
            .ActivationService::TTL_DAYS.' hari dan hanya dapat dipakai sekali. Abaikan email ini bila Anda tidak merasa dibuatkan akun.';
    }

    public function url(): string
    {
        return "/activation/{$this->token}";
    }
}
