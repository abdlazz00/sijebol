<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            // Waktu tiket dipindai petugas; kosong bila kehadiran dicatat manual. Pelaku ada di riwayat status.
            $table->timestamp('scanned_at')->nullable()->after('session_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            $table->dropColumn('scanned_at');
        });
    }
};
