<?php

use App\Domains\Access\Models\Role;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Test BE tidak boleh bergantung pada build FE: halaman Inertia boleh belum ada di manifest Vite.
        test()->withoutVite();
        config(['inertia.testing.ensure_pages_exist' => false]);
    })
    ->in('Feature');

/**
 * Buat user aktif dengan role `$roleName` yang memegang `$permissions`.
 * Role dengan nama sama dipakai ulang antar-panggilan, izinnya bertambah.
 *
 * @param  list<string>  $permissions
 */
function makeUserWith(array $permissions = [], ?School $school = null, bool $scoped = false, string $roleName = 'uji'): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $name) {
        PermissionModel::findOrCreate($name, 'web');
    }

    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['is_school_scoped' => $scoped]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create(['school_id' => $school?->id]);
    $user->assignRole($role);

    return $user;
}

/** Pendaftaran sesi dengan status tertentu; sekolah, siswa, dan sesi dibuat bila tidak diberikan (satu sekolah). */
function makeEntry(RegistrationStatus $status = RegistrationStatus::MenungguVerifikasi, ?School $school = null, ?Student $student = null, ?Schedule $schedule = null): RegistrationStudent
{
    $school ??= $student?->school ?? $schedule?->school ?? School::factory()->create();
    $student ??= Student::factory()->for($school)->create();
    $schedule ??= makeSchedule($school);

    return RegistrationStudent::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'status' => $status,
    ]);
}

/** Siswa umur 16 dengan KK dan akta: memenuhi semua syarat eligibility. */
function eligibleStudent(?School $school = null): Student
{
    $student = Student::factory()->for($school ?? School::factory()->create())->create();
    foreach (['kk', 'akta_lahir'] as $type) {
        $student->documents()->create(['type' => $type, 'path' => "students/{$student->id}/{$type}.pdf", 'mime' => 'application/pdf', 'size' => 1]);
    }

    return $student->load('documents');
}

/** Sesi untuk sekolah (default: sekolah baru) pada tanggal tertentu (default: 7 hari lagi); kuota 30, 08:00-12:00. */
function makeSchedule(?School $school = null, ?string $date = null): Schedule
{
    return Schedule::factory()->for($school ?? School::factory()->create())->create(array_filter(['date' => $date]));
}

/** Akun siswa + baris siswa lengkap (KK, akta, umur 16+) di sekolah tertentu. @return array{0: User, 1: Student} */
function siswaLengkap(School $school): array
{
    $user = makeUserWith(['pendaftaran.lihat', 'pendaftaran.buat'], $school, true, 'siswa');
    $student = eligibleStudent($school);
    $student->update(['user_id' => $user->id]);

    return [$user, $student];
}
