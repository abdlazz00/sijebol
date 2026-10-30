<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('logs:prune')->daily();

// Pendaftaran sesi: lepas kursi yang tidak jadi dipakai dan kirim pengingat. Butuh cron `schedule:run` tiap menit.
Schedule::command('sijebol:kedaluwarsakan')->hourly()->withoutOverlapping();
Schedule::command('sijebol:kirim-pengingat')->hourly()->withoutOverlapping();

// Siswa Terdaftar yang tidak terpindai sampai sesinya lewat menjadi Tidak hadir (mulai hari setelah sesi).
Schedule::command('sijebol:tandai-tidak-hadir')->hourly()->withoutOverlapping();
