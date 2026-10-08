<?php

namespace App\Domains\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dasar semua notifikasi SIJEBOL: subclass hanya menentukan judul, isi, dan tautan. */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    abstract public function title(): string;

    abstract public function body(): string;

    /** Path relatif di aplikasi, mis. `/pengguna/5`. */
    abstract public function url(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => $this->url()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Halo, '.$notifiable->name.'.')
            ->line($this->body())
            ->action('Buka di SIJEBOL', url($this->url()))
            ->salutation('Salam, SIJEBOL');
    }
}
