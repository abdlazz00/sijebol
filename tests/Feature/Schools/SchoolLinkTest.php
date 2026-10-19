<?php

use App\Domains\Schools\Models\School;
use App\Domains\Schools\Services\SchoolService;
use App\Domains\Students\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

test('operator hanya melihat link sekolahnya, staf melihat semua', function () {
    [$a, $b] = School::factory()->count(2)->create();
    Student::factory()->for($a)->count(2)->create();
    $operator = makeUserWith(['sekolah.tautan'], $a, true, 'op-link');
    $staf = makeUserWith(['sekolah.tautan'], roleName: 'staf-link');

    $this->actingAs($operator)->get('/tautan-pendaftaran')->assertInertia(fn (Assert $p) => $p
        ->component('Sekolah/Tautan')
        ->has('schools', 1)
        ->where('schools.0.id', $a->id)
        ->where('schools.0.url', url("/daftar/{$a->registration_token}"))
        ->where('schools.0.students_count', 2));

    $this->actingAs($staf)->get('/tautan-pendaftaran')->assertInertia(fn (Assert $p) => $p->has('schools', 2));
});

test('reset mengganti token, token lama tidak lagi menemukan sekolah, siswa tetap', function () {
    $school = School::factory()->create();
    Student::factory()->for($school)->create();
    $lama = $school->registration_token;
    $admin = makeUserWith(['sekolah.reset-tautan'], roleName: 'admin-link');

    $this->actingAs($admin)->post("/tautan-pendaftaran/{$school->id}/reset")->assertRedirect();

    $school->refresh();
    expect($school->registration_token)->not->toBe($lama)->toHaveLength(40)
        ->and(app(SchoolService::class)->findByRegistrationToken($lama))->toBeNull()
        ->and(Student::count())->toBe(1)
        ->and(Activity::where('description', 'sekolah.tautan_direset')->exists())->toBeTrue();
});

test('operator tidak dapat mereset link sekolah lain', function () {
    [$a, $b] = School::factory()->count(2)->create();
    $token = $b->registration_token;
    $operator = makeUserWith(['sekolah.reset-tautan'], $a, true, 'op-reset');

    $this->actingAs($operator)->post("/tautan-pendaftaran/{$b->id}/reset")->assertSessionHas('error');

    expect($b->fresh()->registration_token)->toBe($token);
});
