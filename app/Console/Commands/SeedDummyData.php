<?php

namespace App\Console\Commands;

use Database\Seeders\DummyDataSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class SeedDummyData extends Command
{
    protected $signature = 'sijebol:isi-data-dummy
        {--siswa=1500 : Jumlah siswa dummy}
        {--sandi=password : Kata sandi semua akun dummy (di luar lokal wajib diganti, minimal 8 karakter)}
        {--bersihkan : Hanya hapus data dummy}
        {--force : Lewati konfirmasi di luar lokal}';

    protected $description = 'Isi siswa, sesi, pendaftaran, dokumen, dan akun dummy ke sekolah yang sudah ada (lokal, atau UAT bila SIJEBOL_ALLOW_DUMMY_DATA=true); --bersihkan menghapusnya';

    public function handle(DummyDataSeeder $seeder): int
    {
        try {
            $seeder->assertAllowed();
            $password = (string) $this->option('sandi');
            $outsideLocal = ! app()->environment('local', 'testing');

            if ($outsideLocal && ! $this->option('bersihkan') && (strlen($password) < 8 || $password === 'password')) {
                throw new RuntimeException('Di luar lokal, kata sandi akun dummy harus diganti: --sandi=<minimal 8 karakter, bukan "password">.');
            }
            if ($outsideLocal && ! $this->option('force') && ! $this->confirm('Lingkungan '.app()->environment().'. Data dummy akan ditambahkan ke sekolah yang ada. Lanjut?')) {
                $this->warn('Dibatalkan. Tidak ada yang diubah.');

                return self::FAILURE;
            }

            if ($this->option('bersihkan')) {
                $result = $seeder->clean();
                $this->info('Data dummy dihapus.');
            } else {
                $result = $seeder->generate(max(1, (int) $this->option('siswa')), $password);
                $this->info($outsideLocal ? 'Data dummy dibuat. Kata sandi akun dummy: sesuai --sandi.' : 'Data dummy dibuat. Kata sandi semua akun dummy: '.$password.'.');
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
