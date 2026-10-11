<?php

namespace App\Providers;

use App\Domains\Access\Listeners\LogAuthEvents;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\AccessGuard;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Batas umum semua rute web; limit khusus yang lebih ketat (login, impor, ekspor) tetap berlaku di atasnya.
        RateLimiter::for('web', fn (Request $request) => Limit::perMinute(config('security.rate_limit'))
            ->by($request->user()->id ?? $request->ip()));

        Gate::before(fn ($user) => $user->hasRole(Role::SUPER_ADMIN) ? true : null);

        Gate::define('manage-user', fn (User $actor, User $target) => AccessGuard::canManageUser($actor, $target));

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        ResetPassword::toMailUsing(function (User $notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Atur Ulang Password Akun SIJEBOL')
                ->greeting("Halo, {$notifiable->name}")
                ->line('Kami menerima permintaan untuk mengatur ulang password akun SIJEBOL Anda.')
                ->action('Atur Ulang Password', $url)
                ->line('Tautan atur ulang password ini akan kedaluwarsa dalam 60 menit.')
                ->line('Jika Anda tidak merasa meminta pengaturan ulang password, Anda dapat mengabaikan email ini.');
        });

        Event::listen(Login::class, [LogAuthEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthEvents::class, 'handleLogout']);
        Event::listen(Lockout::class, [LogAuthEvents::class, 'handleLockout']);
    }
}
