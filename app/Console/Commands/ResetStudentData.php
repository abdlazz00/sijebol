<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetStudentData extends Command
{
    protected $signature = 'sijebol:bersihkan-data-siswa {--force : Lewati konfirmasi (hanya di lokal)} {--lingkungan= : Nama lingkungan, untuk skrip di luar lokal}';

    protected $description = 'Hapus semua data siswa, pendaftaran, jadwal, dan dokumen (akun pengguna tetap) untuk memulai dari kosong';

    /** Urut dari tabel anak ke induk agar kunci asing tidak menolak penghapusan. */
    private const TABLES = [
        'registration_status_histories',
        'registration_students',
        'schedules',
        'student_documents',
        'students',
    ];

    public function handle(): int
    {
        if (! $this->confirmed()) {
            $this->warn('Dibatalkan. Tidak ada data yang dihapus.');

            return self::FAILURE;
        }

        $deleted = DB::transaction(fn () => collect(self::TABLES)
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->delete()])
            ->all());
        Storage::disk('local')->deleteDirectory('students');

        foreach ($deleted as $table => $count) {
            $this->line("{$table}: {$count} baris dihapus");
        }
        $this->info('Selesai.');

        return self::SUCCESS;
    }

    /**
     * Lokal dan tes: konfirmasi biasa (--force melewatinya). Di luar itu (UAT, produksi) --force tidak cukup:
     * operator harus mengetik nama lingkungan, sesudah melihat database dan jumlah baris yang akan dihapus.
     */
    private function confirmed(): bool
    {
        if (app()->environment('local', 'testing')) {
            return $this->option('force')
                || $this->confirm('Hapus SEMUA data siswa, pendaftaran, jadwal, dan dokumen? Akun pengguna tidak ikut dihapus.');
        }

        $environment = app()->environment();
        $this->warn("Lingkungan {$environment}, database ".DB::connection()->getDatabaseName().'.');
        foreach (self::TABLES as $table) {
            $this->line("{$table}: ".DB::table($table)->count().' baris akan dihapus');
        }

        $typed = $this->option('lingkungan') ?? $this->ask("Ketik nama lingkungan ({$environment}) untuk melanjutkan");

        return $typed === $environment;
    }
}
