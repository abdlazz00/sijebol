<?php

namespace App\Domains\Notifications\Notifications;

class StudentCorrectedNotification extends BaseNotification
{
    public function __construct(private readonly string $note) {}

    public function title(): string
    {
        return 'Data Anda dikoreksi admin';
    }

    public function body(): string
    {
        return "Admin kecamatan mengoreksi data Anda. Catatan: {$this->note}";
    }

    public function url(): string
    {
        return '/saya';
    }
}
