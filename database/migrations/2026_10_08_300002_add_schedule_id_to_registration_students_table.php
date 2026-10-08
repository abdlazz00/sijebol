<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            // Tetap terisi setelah penandaan (jejak jadwal yang diikuti); hanya "keluarkan dari jadwal" yang mengosongkan.
            $table->foreignId('schedule_id')->nullable()->after('decided_at')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_id');
        });
    }
};
