<?php

use App\Domains\Schools\Models\School;
use App\Domains\Schools\Services\SchoolService;

test('sekolah baru otomatis mendapat token pendaftaran acak yang unik', function () {
    [$a, $b] = School::factory()->count(2)->create();

    expect($a->registration_token)->toHaveLength(40)
        ->and($a->registration_token)->not->toBe($b->registration_token);
});

test('token tidak dapat diubah lewat pengisian massal', function () {
    $school = School::factory()->create();
    $token = $school->registration_token;

    $school->update(['registration_token' => 'diretas', 'name' => 'Nama Baru']);

    expect($school->fresh()->registration_token)->toBe($token)
        ->and($school->fresh()->name)->toBe('Nama Baru');
});

test('sekolah dicari lewat token dan token acak tidak menemukan apa pun', function () {
    $school = School::factory()->create();
    $service = app(SchoolService::class);

    expect($service->findByRegistrationToken($school->registration_token)?->id)->toBe($school->id)
        ->and($service->findByRegistrationToken('acak'))->toBeNull();
});
