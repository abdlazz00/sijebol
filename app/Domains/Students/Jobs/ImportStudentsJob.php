<?php

namespace App\Domains\Students\Jobs;

use App\Domains\Notifications\Notifications\StudentImportFinishedNotification;
use App\Domains\Students\Models\StudentImport;
use App\Domains\Students\Services\StudentImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class ImportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Tanpa percobaan ulang: NIK unik membuat percobaan kedua melaporkan semua baris sebagai ganda.
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $importId) {}

    public function handle(StudentImportService $service): void
    {
        $import = StudentImport::query()->findOrFail($this->importId);
        $service->run($import);
        $import->user->notify(StudentImportFinishedNotification::finished($import->refresh()));
    }

    public function failed(Throwable $exception): void
    {
        $import = StudentImport::query()->find($this->importId);
        if ($import === null) {
            return;
        }

        app(StudentImportService::class)->markFailed($import);
        $import->user->notify(StudentImportFinishedNotification::failed($import->refresh()));
    }
}
