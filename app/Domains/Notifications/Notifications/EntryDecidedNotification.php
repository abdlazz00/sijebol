<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Registrations\Enums\RegistrationStatus;
use Illuminate\Support\Str;

class EntryDecidedNotification extends BaseNotification
{
    public function __construct(
        private readonly RegistrationStatus $decision,
        private readonly string $student,
        private readonly int $registrationId,
        private readonly ?string $note,
    ) {}

    public function title(): string
    {
        return match ($this->decision) {
            RegistrationStatus::Terverifikasi => 'Pendaftaran diverifikasi',
            RegistrationStatus::PerluPerbaikan => 'Perlu perbaikan',
            default => 'Pendaftaran ditolak',
        };
    }

    public function body(): string
    {
        return $this->note ? "{$this->student}: ".Str::limit($this->note, 120) : $this->student;
    }

    public function url(): string
    {
        return "/pendaftaran/{$this->registrationId}";
    }
}
