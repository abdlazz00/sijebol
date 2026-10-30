<?php

use App\Domains\Access\Services\ActivationService;
use App\Domains\Notifications\Notifications\AccountActivatedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('pembuat akun diberi tahu saat akun diaktifkan', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $user = User::factory()->create(['created_by' => $admin->id, 'activated_at' => null]);
    $token = app(ActivationService::class)->issue($user);

    app(ActivationService::class)->complete($token, 'abcd1234');

    Notification::assertSentTo($admin, AccountActivatedNotification::class, function ($n) use ($user) {
        return str_contains($n->body(), $user->name) && $n->url() === "/users/{$user->id}";
    });
});

test('aktivasi tetap selesai bila pembuat tidak ada atau nonaktif', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => false]);
    $user = User::factory()->create(['created_by' => $admin->id, 'activated_at' => null]);
    $tanpa = User::factory()->create(['created_by' => null, 'activated_at' => null]);
    $svc = app(ActivationService::class);

    $svc->complete($svc->issue($user), 'abcd1234');
    $svc->complete($svc->issue($tanpa), 'abcd1234');

    Notification::assertNothingSent();
    expect($user->fresh()->activated_at)->not->toBeNull()
        ->and($tanpa->fresh()->activated_at)->not->toBeNull();
});
