<?php

use App\Domains\Access\Models\Role;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
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

/** Entri pendaftaran dengan status tertentu; siswa dan sekolah dibuat bila tidak diberikan. */
function makeEntry(RegistrationStatus $status = RegistrationStatus::Draf, ?School $school = null, ?Student $student = null): RegistrationStudent
{
    $school ??= $student?->school ?? School::factory()->create();
    $student ??= Student::factory()->for($school)->create();
    $registration = Registration::factory()->for($school)->create([
        'submitted_at' => $status === RegistrationStatus::Draf ? null : now(),
    ]);

    return RegistrationStudent::factory()->create([
        'registration_id' => $registration->id,
        'school_id' => $school->id,
        'student_id' => $student->id,
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
