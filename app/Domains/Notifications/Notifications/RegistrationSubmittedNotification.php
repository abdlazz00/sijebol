<?php

namespace App\Domains\Notifications\Notifications;

class RegistrationSubmittedNotification extends BaseNotification
{
    public function __construct(
        private readonly string $school,
        private readonly int $count,
        private readonly bool $again = false,
    ) {}

    public function title(): string
    {
        return $this->again ? 'Pengajuan ulang diterima' : 'Pendaftaran baru diajukan';
    }

    public function body(): string
    {
        return "{$this->school}: {$this->count} siswa menunggu verifikasi.";
    }

    public function url(): string
    {
        return '/verifikasi';
    }
}
