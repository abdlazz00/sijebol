<?php

namespace App\Providers;

use App\Domains\Access\Listeners\LogAuthEvents;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\AccessGuard;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::before(fn ($user) => $user->hasRole(Role::SUPER_ADMIN) ? true : null);

        Gate::define('manage-user', fn (User $actor, User $target) => AccessGuard::canManageUser($actor, $target));

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Event::listen(Login::class, [LogAuthEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthEvents::class, 'handleLogout']);
        Event::listen(Lockout::class, [LogAuthEvents::class, 'handleLockout']);
    }
}
