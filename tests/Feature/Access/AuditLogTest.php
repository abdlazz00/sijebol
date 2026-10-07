<?php

use App\Domains\Access\Models\Role;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('perubahan user, role, dan sekolah tercatat dengan pelaku', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $school = School::factory()->create(['name' => 'Awal']);
    $school->update(['name' => 'Akhir']);
    Role::create(['name' => 'baru']);

    expect(Activity::where('subject_type', School::class)->where('event', 'updated')->count())->toBe(1)
        ->and(Activity::where('subject_type', Role::class)->where('event', 'created')->count())->toBe(1)
        ->and(Activity::where('subject_type', School::class)->where('event', 'updated')->first()->causer_id)->toBe($actor->id);
});

test('password dan token tidak pernah masuk log', function () {
    $user = User::factory()->create(['password' => 'rahasia-banget-123']);
    $user->update(['password' => 'rahasia-baru-456', 'name' => 'Ganti']);

    $json = Activity::all()->toJson();

    expect($json)->not->toContain('rahasia')
        ->and($json)->not->toContain('remember_token');
});

test('update tanpa perubahan tidak membuat entri kosong', function () {
    $school = School::factory()->create();
    $before = Activity::count();

    $school->update(['name' => $school->name]);

    expect(Activity::count())->toBe($before);
});
