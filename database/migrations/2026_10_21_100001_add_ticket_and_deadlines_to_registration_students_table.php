<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            // Kode di QR tiket; keabsahan ditentukan status (Terdaftar), bukan ada tidaknya kode.
            $table->string('ticket_code', 40)->nullable()->unique()->after('decided_at');
            $table->timestamp('fix_deadline_at')->nullable()->after('ticket_code');
            $table->timestamp('fix_reminded_at')->nullable()->after('fix_deadline_at');
            $table->timestamp('session_reminded_at')->nullable()->after('fix_reminded_at');
        });

        // Aksi otomatis (scheduler) tidak punya pelaku: dicatat sebagai "Sistem".
        Schema::table('registration_status_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('by_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            $table->dropUnique(['ticket_code']);
            $table->dropColumn(['ticket_code', 'fix_deadline_at', 'fix_reminded_at', 'session_reminded_at']);
        });
        // by_user_id dibiarkan nullable: baris riwayat aksi sistem tidak dapat dipulihkan menjadi NOT NULL.
    }
};
