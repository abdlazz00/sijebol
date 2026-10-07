<?php

use App\Domains\Access\Enums\UserStatus;
use App\Domains\Access\Models\AccountActivation;
use App\Domains\Schools\Models\School;
use App\Models\User;

function activationFor(User $user, string $expires = '+1 day', bool $used = false): AccountActivation
{
    return AccountActivation::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', fake()->unique()->uuid()),
        'expires_at' => now()->modify($expires),
        'used_at' => $used ? now() : null,
    ]);
}

test('status dihitung dengan prioritas nonaktif, aktif, menunggu, kedaluwarsa', function () {
    $aktif = User::factory()->create();
    $nonaktif = User::factory()->inactive()->create();
    $menunggu = User::factory()->pending()->create();
    activationFor($menunggu);
    $kedaluwarsa = User::factory()->pending()->create();
    activationFor($kedaluwarsa, '-1 minute');
    $tanpaLink = User::factory()->pending()->create();

    expect($aktif->status)->toBe(UserStatus::Aktif)
        ->and($nonaktif->status)->toBe(UserStatus::Nonaktif)
        ->and($menunggu->status)->toBe(UserStatus::Menunggu)
        ->and($kedaluwarsa->status)->toBe(UserStatus::Kedaluwarsa)
        ->and($tanpaLink->status)->toBe(UserStatus::Kedaluwarsa);
});

test('scope whereStatus memfilter di SQL dan konsisten dengan accessor', function () {
    User::factory()->create();
    User::factory()->inactive()->create();
    activationFor(User::factory()->pending()->create());
    activationFor(User::factory()->pending()->create(), '-1 minute');

    foreach (UserStatus::cases() as $status) {
        $found = User::whereStatus($status)->get();
        expect($found)->toHaveCount(1, $status->value)
            ->and($found->first()->status)->toBe($status);
    }
});

test('operator terikat sekolahnya, staf tanpa sekolah melihat semua', function () {
    [$a, $b] = School::factory()->count(2)->create();
    $opA = User::factory()->create(['school_id' => $a->id]);
    $opB = User::factory()->create(['school_id' => $b->id]);
    $staf = User::factory()->create();

    $this->actingAs($opA);
    expect(User::pluck('id')->all())->toEqual([$opA->id]);
    expect(User::find($opB->id))->toBeNull();

    $this->actingAs($staf);
    expect(User::count())->toBe(3);
});
