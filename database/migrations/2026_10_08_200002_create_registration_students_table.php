<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draf');
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            // Terisi hanya saat status aktif: indeks unik di bawah menjaga satu entri aktif per siswa.
            // Daftar status ini harus sama dengan RegistrationStatus::active().
            $table->unsignedBigInteger('active_student_id')->nullable()->storedAs(
                "CASE WHEN status IN ('diajukan','perlu_perbaikan','terverifikasi','terjadwal','sudah_rekaman') THEN student_id END"
            );
            $table->timestamps();

            $table->unique(['registration_id', 'student_id']);
            $table->unique('active_student_id');
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_students');
    }
};
