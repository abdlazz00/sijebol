<?php

namespace App\Http\Middleware;

use App\Domains\Access\Services\MenuService;
use App\Domains\Notifications\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? $user->only(['id', 'name', 'email']) + ['role' => $user->currentRole()?->name] : null,
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'menu' => fn () => app(MenuService::class)->forUser($user),
            'notifications' => fn () => $user ? app(NotificationService::class)->summary($user) : null,
        ];
    }
}
