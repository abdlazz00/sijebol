<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Halaman Log menyaring rentang waktu pada created_at.
    public function up(): void
    {
        Schema::table('activity_log', fn (Blueprint $table) => $table->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('activity_log', fn (Blueprint $table) => $table->dropIndex(['created_at']));
    }
};
