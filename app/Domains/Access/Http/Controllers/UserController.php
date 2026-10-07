<?php

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Enums\UserStatus;
use App\Domains\Access\Http\Requests\UserRequest;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\AccessGuard;
use App\Domains\Access\Services\RoleService;
use App\Domains\Access\Services\UserService;
use App\Domains\Schools\Services\SchoolService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly RoleService $roles,
        private readonly SchoolService $schools,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Pengguna/Index', [
            'users' => $this->users->list()->through(fn (User $u) => $this->row($u, $request->user())),
            'filters' => $request->query('filter', []),
            'roles' => $this->roles->list()->map->only(['id', 'name'])->values(),
            'schools' => $this->schools->options(),
            'statuses' => array_map(fn (UserStatus $s) => $s->value, UserStatus::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Pengguna/Form', ['user' => null] + $this->formOptions());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        ['user' => $user, 'token' => $token] = $this->users->create($request->validated(), $request->user());

        return $this->toSuccess($user, $token);
    }

    public function show(Request $request, User $user): Response
    {
        return Inertia::render('Pengguna/Show', ['user' => $this->row($user, $request->user()) + [
            'created_by' => $user->creator?->name,
            'created_at' => $user->created_at,
        ]]);
    }

    public function edit(User $user): Response
    {
        Gate::authorize('manage-user', $user);

        return Inertia::render('Pengguna/Form', [
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                'role_id' => $user->currentRole()?->id, 'school_id' => $user->school_id,
                'activated' => $user->activated_at !== null,
            ],
        ] + $this->formOptions());
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-user', $user);
        $this->users->update($user, $request->validated(), $request->user());

        return redirect()->route('pengguna.show', $user)->with('success', 'Pengguna diperbarui.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-user', $user);
        $this->users->deactivate($user, $request->user());

        return back()->with('success', 'Pengguna dinonaktifkan.');
    }

    public function activate(User $user): RedirectResponse
    {
        Gate::authorize('manage-user', $user);
        $this->users->activate($user);

        return back()->with('success', 'Pengguna diaktifkan.');
    }

    public function resend(User $user): RedirectResponse
    {
        Gate::authorize('manage-user', $user);

        return $this->toSuccess($user, $this->users->resendActivation($user), 'ulang');
    }

    /** Link aktivasi hanya tampil sekali: dibaca dari flash session. */
    public function done(User $user): Response|RedirectResponse
    {
        $link = session('activation_link');

        if ($link === null) {
            return redirect()->route('pengguna.show', $user);
        }

        return Inertia::render('Pengguna/Berhasil', [
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'phone' => $user->phone,
                'role' => $user->currentRole()?->name, 'school' => $user->school?->name,
            ],
            'link' => $link,
            'mode' => session('activation_mode', 'dibuat'),
        ]);
    }

    private function toSuccess(User $user, string $token, string $mode = 'dibuat'): RedirectResponse
    {
        return redirect()->route('pengguna.done', $user)
            ->with('activation_link', route('aktivasi.show', $token))
            ->with('activation_mode', $mode);
    }

    /** @return array<string, mixed> */
    private function row(User $u, User $actor): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'role' => $u->currentRole()?->name, 'school' => $u->school?->name,
            'status' => $u->status->value, 'last_login_at' => $u->last_login_at,
            'can_manage' => $actor->can('manage-user', $u),
        ];
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'roles' => $this->roles->list()
                ->filter(fn (Role $r) => AccessGuard::canGrantRole(request()->user(), $r))
                ->map(fn (Role $r) => $r->only(['id', 'name', 'is_school_scoped']))->values(),
            'schools' => $this->schools->options(),
        ];
    }
}
