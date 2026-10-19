<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('registration_token', 64)->nullable()->after('address');
        });

        // Sekolah yang sudah ada diberi token sebelum kolom dijadikan wajib dan unik.
        DB::table('schools')->orderBy('id')->each(fn ($school) => DB::table('schools')
            ->where('id', $school->id)
            ->update(['registration_token' => Str::random(40)]));

        Schema::table('schools', function (Blueprint $table) {
            $table->string('registration_token', 64)->nullable(false)->change();
            $table->unique('registration_token');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('registration_token');
        });
    }
};
