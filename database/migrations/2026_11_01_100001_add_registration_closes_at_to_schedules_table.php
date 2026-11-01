<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Batas waktu pendaftaran siswa per sesi (zona aplikasi), menggantikan aturan global "tanggal sesi dikurangi N hari".
            $table->dateTime('registration_closes_at')->nullable()->after('end_time');
        });

        // Sesi yang sudah ada mempertahankan aturan lama: ditutup dua hari sebelum sesi, akhir hari.
        DB::table('schedules')->update([
            'registration_closes_at' => DB::raw("TIMESTAMP(DATE_SUB(`date`, INTERVAL 2 DAY), '23:59:59')"),
        ]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->dateTime('registration_closes_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('registration_closes_at');
        });
    }
};
