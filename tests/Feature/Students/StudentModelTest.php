<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

test('NIK unik dan dokumen unik per jenis', function () {
    $student = Student::factory()->create(['nik' => '3201123412341234']);

    expect(fn () => Student::factory()->create(['nik' => '3201123412341234']))->toThrow(QueryException::class);

    $student->documents()->create(['type' => 'kk', 'path' => 'a', 'mime' => 'application/pdf', 'size' => 10]);
    expect(fn () => $student->documents()->create(['type' => 'kk', 'path' => 'b', 'mime' => 'application/pdf', 'size' => 10]))
        ->toThrow(QueryException::class);
});

test('operator hanya melihat siswa sekolahnya, staf tanpa sekolah melihat semua', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();
    Student::factory()->for($a)->create();
    Student::factory()->for($b)->create();
    $operator = User::factory()->create(['school_id' => $a->id]);

    $this->actingAs($operator);
    expect(Student::count())->toBe(1);

    $this->actingAs(User::factory()->create(['school_id' => null]));
    expect(Student::count())->toBe(2);
});

test('menghapus siswa menghapus dokumennya (cascade) dan perubahan tercatat', function () {
    $this->actingAs(User::factory()->create());
    $student = Student::factory()->create(['name' => 'Awal']);
    $student->documents()->create(['type' => 'kk', 'path' => 'a', 'mime' => 'application/pdf', 'size' => 10]);
    $student->update(['name' => 'Akhir']);

    $student->delete();

    expect(DB::table('student_documents')->count())->toBe(0)
        ->and(Activity::where('log_name', 'siswa')->where('event', 'updated')->count())->toBe(1);
});
