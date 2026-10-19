<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('registration_students')->exists() || DB::table('schedules')->exists()) {
            throw new RuntimeException('Data lama masih ada. Jalankan "php artisan sijebol:bersihkan-data-siswa" lebih dulu, lalu ulangi migrasi.');
        }

        Schema::table('schedules', function (Blueprint $table) {
            $table->time('start_time')->after('date');
            $table->time('end_time')->after('start_time');
            $table->unsignedSmallInteger('quota')->after('location');
            $table->timestamp('cancelled_at')->nullable()->after('quota');
        });

        Schema::table('registration_students', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->dropUnique(['registration_id', 'student_id']);
            $table->dropColumn('registration_id');
            $table->dropUnique(['active_student_id']);
            $table->dropColumn('active_student_id');
        });

        Schema::table('registration_students', function (Blueprint $table) {
            $table->string('status', 20)->default('menunggu_verifikasi')->change();
            $table->unsignedBigInteger('schedule_id')->nullable(false)->change();
            // Daftar status ini harus sama dengan RegistrationStatus::active().
            $table->unsignedBigInteger('active_student_id')->nullable()->storedAs(
                "CASE WHEN status IN ('menunggu_verifikasi','perlu_perbaikan','terdaftar','sudah_rekaman') THEN student_id END"
            );
            $table->unique('active_student_id');
        });

        Schema::table('registration_status_histories', function (Blueprint $table) {
            $table->string('from_status', 20)->nullable()->change();
        });

        Schema::dropIfExists('registrations');
    }

    public function down(): void
    {
        throw new RuntimeException('Migrasi ini satu arah; pulihkan dari cadangan basis data.');
    }
};
