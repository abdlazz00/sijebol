<?php

namespace App\Console\Commands;

use Database\Seeders\DummyDataSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class SeedDummyData extends Command
{
    protected $signature = 'sijebol:isi-data-dummy {--siswa=1500 : Jumlah siswa dummy} {--bersihkan : Hanya hapus data dummy}';

    protected $description = 'Isi siswa, sesi, pendaftaran, dokumen, dan akun dummy ke sekolah yang sudah ada (hanya lokal); --bersihkan menghapusnya';

    public function handle(DummyDataSeeder $seeder): int
    {
        try {
            if ($this->option('bersihkan')) {
                $result = $seeder->clean();
                $this->info('Data dummy dihapus.');
            } else {
                $result = $seeder->generate(max(1, (int) $this->option('siswa')));
                $this->info('Data dummy dibuat. Kata sandi semua akun dummy: password.');
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result as $item => $count) {
            $this->line("{$item}: {$count}");
        }

        return self::SUCCESS;
    }
}
