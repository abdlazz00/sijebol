<?php

use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Models\User;

it('hanya mengirim notifikasi di aplikasi ke akun dummy, tanpa email', function () {
    $notification = RegistrationNotification::sessionCancelled('2026-11-10', 'Sesi dibatalkan.');

    $dummy = User::factory()->make(['email' => 'siswa.9912345678@dummy.sijebol.test']);
    $nyata = User::factory()->make(['email' => 'siswa@example.com']);

    expect($notification->via($dummy))->toBe(['database'])
        ->and($notification->via($nyata))->toBe(['database', 'mail']);
});
