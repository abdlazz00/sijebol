<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Students\Models\StudentImport;

class StudentImportFinishedNotification extends BaseNotification
{
    private function __construct(
        private readonly int $importId,
        private readonly string $title,
        private readonly string $body,
    ) {}

    public static function finished(StudentImport $import): self
    {
        $failed = count($import->failed ?? []);

        return new self($import->id, 'Impor siswa selesai', "{$import->imported} siswa ditambahkan, {$failed} baris gagal.");
    }

    public static function failed(StudentImport $import): self
    {
        return new self($import->id, 'Impor siswa gagal', 'Berkas tidak dapat diproses. Periksa berkas lalu coba lagi.');
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
        return "/siswa?impor={$this->importId}";
    }
}
