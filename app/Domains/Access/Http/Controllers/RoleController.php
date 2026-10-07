<?php

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Requests\RoleRequest;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\PermissionCatalog;
use App\Domains\Access\Services\RoleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): Response
    {
        return Inertia::render('Role/Index', [
            'roles' => $this->roles->list()->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'users_count' => $r->users_count,
                'permissions_count' => $r->permissions_count,
                'is_school_scoped' => $r->is_school_scoped,
                'protected' => $r->isProtected(),
            ])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Role/Form', ['role' => null, 'catalog' => PermissionCatalog::forActor($request->user())]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->roles->create($request->validated(), $request->user());

        return redirect()->route('role.index')->with('success', 'Role ditambahkan.');
    }

    public function edit(Request $request, Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('Role/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_school_scoped' => $role->is_school_scoped,
                'permissions' => $role->permissions->pluck('name')->values(),
                'protected' => $role->isProtected(),
            ],
            'catalog' => PermissionCatalog::forActor($request->user()),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, $request->validated(), $request->user());

        return redirect()->route('role.index')->with('success', 'Role diperbarui.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->roles->delete($role, $request->user());

        return redirect()->route('role.index')->with('success', 'Role dihapus.');
    }
}
