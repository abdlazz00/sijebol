<?php

use App\Domains\Access\Exceptions\InvalidActivationException;
use App\Domains\Access\Models\AccountActivation;
use App\Domains\Access\Services\ActivationService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->service = app(ActivationService::class));

test('issue menyimpan hash, bukan token, berlaku 3 hari', function () {
    $user = User::factory()->pending()->create();

    $token = $this->service->issue($user);

    expect($token)->toHaveLength(64);
    $row = AccountActivation::firstOrFail();
    expect($row->token_hash)->toBe(hash('sha256', $token))
        ->and($row->token_hash)->not->toBe($token)
        ->and($row->expires_at->isSameDay(now()->addDays(3)))->toBeTrue();
});

test('token baru mencabut token lama', function () {
    $user = User::factory()->pending()->create();
    $old = $this->service->issue($user);
    $new = $this->service->issue($user);

    expect($this->service->findValid($old))->toBeNull()
        ->and($this->service->findValid($new))->not->toBeNull()
        ->and(AccountActivation::count())->toBe(1);
});

test('complete mengisi password, mengaktifkan, dan menandai token terpakai', function () {
    $user = User::factory()->pending()->create();
    $token = $this->service->issue($user);

    $result = $this->service->complete($token, 'rahasia123');

    expect($result->is($user))->toBeTrue()
        ->and($user->fresh()->activated_at)->not->toBeNull()
        ->and(Hash::check('rahasia123', $user->fresh()->password))->toBeTrue()
        ->and(AccountActivation::first()->used_at)->not->toBeNull();
});

test('submit kedua dengan token yang sama gagal', function () {
    $user = User::factory()->pending()->create();
    $token = $this->service->issue($user);
    $this->service->complete($token, 'rahasia123');

    expect(fn () => $this->service->complete($token, 'lain12345'))
        ->toThrow(InvalidActivationException::class);
});

test('token kedaluwarsa, tak dikenal, atau milik user nonaktif tidak berlaku', function () {
    $user = User::factory()->pending()->create();
    $token = $this->service->issue($user);

    $this->travel(4)->days();
    expect($this->service->findValid($token))->toBeNull();
    $this->travelBack();

    expect($this->service->findValid('tidak-dikenal'))->toBeNull();

    $user->update(['is_active' => false]);
    expect($this->service->findValid($token))->toBeNull()
        ->and(fn () => $this->service->complete($token, 'rahasia123'))->toThrow(InvalidActivationException::class);
});
