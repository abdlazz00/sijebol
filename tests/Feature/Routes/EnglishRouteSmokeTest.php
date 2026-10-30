<?php

use Illuminate\Support\Facades\Route;

test('semua named route bahasa Inggris terdaftar di router', function () {
    $expectedRoutes = [
        'me.show',
        'me.update',
        'me.sessions.index',
        'me.sessions.register',
        'me.tickets.show',
        'me.registrations.cancel',
        'me.registrations.resubmit',
        'register.show',
        'register.store',
        'activation.show',
        'activation.store',
        'students.index',
        'schedules.index',
        'verifications.index',
        'reports.index',
        'schools.index',
        'users.index',
        'roles.index',
        'registration-links.index',
        'audit-logs.index',
        'notifications.index',
    ];

    foreach ($expectedRoutes as $name) {
        expect(Route::has($name))->toBeTrue("Route [{$name}] belum terdaftar.");
    }
});
