<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetStudentData extends Command
{
    protected $signature = 'sijebol:bersihkan-data-siswa {--force : Lewati konfirmasi}';

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
        if (! $this->option('force') && ! $this->confirm('Hapus SEMUA data siswa, pendaftaran, jadwal, dan dokumen? Akun pengguna tidak ikut dihapus.')) {
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
}
