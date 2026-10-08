<?php

namespace App\Domains\Notifications\Notifications;

use Carbon\Carbon;

class ScheduleNotification extends BaseNotification
{
    private function __construct(
        private readonly int $scheduleId,
        private readonly string $title,
        private readonly string $body,
    ) {}

    public static function assigned(int $scheduleId, int $count, string $date, string $location): self
    {
        return new self($scheduleId, 'Jadwal ditetapkan', "{$count} siswa dijadwalkan pada ".self::format($date)." di {$location}.");
    }

    public static function changed(int $scheduleId, string $date, string $location): self
    {
        return new self($scheduleId, 'Jadwal diubah', 'Jadwal sekarang pada '.self::format($date)." di {$location}.");
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function url(): string
    {
        return "/jadwal/{$this->scheduleId}";
    }

    private static function format(string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('j F Y');
    }
}
