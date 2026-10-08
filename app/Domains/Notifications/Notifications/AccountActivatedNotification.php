<?php

namespace App\Domains\Notifications\Notifications;

use App\Models\User;

class AccountActivatedNotification extends BaseNotification
{
    public function __construct(private readonly User $activated) {}

    public function title(): string
    {
        return 'Akun diaktifkan';
    }

    public function body(): string
    {
        return "{$this->activated->name} telah mengaktifkan akunnya.";
    }

    public function url(): string
    {
        return "/pengguna/{$this->activated->id}";
    }
}
