<?php

namespace App\Domains\Notifications\Notifications;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/** Pemberitahuan ke siswa tentang pendaftaran sesinya (email + lonceng). Tanpa data pribadi selain yang sudah diketahui siswa. */
class RegistrationNotification extends BaseNotification
{
    private function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
    ) {}

    public static function approved(int $entryId, string $date, string $startTime, string $endTime, string $location): self
    {
        return new self(
            'Pendaftaran disetujui',
            'Anda terdaftar pada '.self::day($date).', pukul '.self::range($startTime, $endTime)." di {$location}. Tiket Anda sudah tersedia; tunjukkan kepada petugas saat sesi. Simpan tiket sebagai gambar agar tetap bisa dibuka tanpa sinyal.",
            "/saya/tiket/{$entryId}",
        );
    }

    public static function needsFix(string $note, CarbonInterface $deadline): self
    {
        return new self(
            'Pendaftaran perlu diperbaiki',
            'Catatan petugas: '.self::sentence($note).' Perbaiki data atau dokumen Anda sebelum '.self::moment($deadline).', lalu ajukan ulang.',
            '/saya',
        );
    }

    public static function rejected(string $note): self
    {
        return new self(
            'Pendaftaran ditolak',
            'Catatan petugas: '.self::sentence($note).' Kursi Anda dilepas. Anda dapat mendaftar ke sesi lain bila perlu.',
            '/saya/sesi',
        );
    }

    public static function seatReleased(string $reason): self
    {
        return new self('Kursi Anda dilepas', self::sentence($reason).' Anda dapat mendaftar ke sesi lain.', '/saya/sesi');
    }

    /** Dipakai juga saat sekolah siswa diganti admin (catatan menjelaskan alasannya). */
    public static function cancelledByStaff(string $note): self
    {
        return new self('Pendaftaran dibatalkan petugas', self::sentence($note).' Kursi Anda dilepas. Anda dapat mendaftar ke sesi lain.', '/saya/sesi');
    }

    public static function sessionChanged(string $date, string $startTime, string $endTime, string $location): self
    {
        return new self(
            'Jadwal sesi berubah',
            'Sesi rekaman Anda sekarang pada '.self::day($date).', pukul '.self::range($startTime, $endTime)." di {$location}.",
            '/saya/sesi',
        );
    }

    public static function sessionCancelled(string $date, string $note): self
    {
        return new self(
            'Sesi dibatalkan',
            'Sesi '.self::day($date).' dibatalkan: '.self::sentence($note).' Pendaftaran Anda ikut dibatalkan; silakan pilih sesi lain.',
            '/saya/sesi',
        );
    }

    public static function fixReminder(CarbonInterface $deadline): self
    {
        return new self(
            'Batas perbaikan segera berakhir',
            'Perbaiki dan ajukan ulang pendaftaran Anda sebelum '.self::moment($deadline).'; setelah itu kursi dilepas otomatis.',
            '/saya',
        );
    }

    public static function sessionReminder(string $date, string $startTime, string $endTime, string $location): self
    {
        return new self(
            'Sesi rekaman besok',
            'Besok ('.self::day($date).') pukul '.self::range($startTime, $endTime)." di {$location}. Siapkan tiket Anda.",
            '/saya/sesi',
        );
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
        return $this->url;
    }

    private static function day(string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('l, j F Y');
    }

    private static function range(string $start, string $end): string
    {
        return str_replace(':', '.', substr($start, 0, 5)).'–'.str_replace(':', '.', substr($end, 0, 5));
    }

    private static function moment(CarbonInterface $at): string
    {
        return Carbon::instance($at)->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i');
    }

    /** Catatan petugas diakhiri satu titik, apa pun bentuk aslinya. */
    private static function sentence(string $text): string
    {
        return rtrim(trim($text), '.').'.';
    }
}
