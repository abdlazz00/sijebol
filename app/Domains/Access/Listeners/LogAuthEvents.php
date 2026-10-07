<?php

namespace App\Domains\Access\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthEvents
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        assert($user instanceof User);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        activity('akses')->performedOn($user)->causedBy($user)->log('login');
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            activity('akses')->performedOn($user)->causedBy($user)->log('logout');
        }
    }

    public function handleLockout(Lockout $event): void
    {
        activity('akses')
            ->withProperties(['email' => $event->request->input('email'), 'ip' => $event->request->ip()])
            ->log('login.terkunci');
    }
}
