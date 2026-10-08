<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class PruneActivityLog extends Command
{
    protected $signature = 'logs:prune {--days=365 : Hapus log yang lebih tua dari jumlah hari ini}';

    protected $description = 'Hapus log aktivitas yang melewati masa retensi';

    public function handle(): int
    {
        $deleted = Activity::query()->where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();
        $this->info("{$deleted} baris log dihapus.");

        return self::SUCCESS;
    }
}
