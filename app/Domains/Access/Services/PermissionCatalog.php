<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Enums\Permission;
use App\Models\User;

final class PermissionCatalog
{
    /** @return list<array{module: string, actions: list<array{value: string, action: string, editable: bool}>}> */
    public static function forActor(User $actor): array
    {
        $owned = AccessGuard::permissionsOf($actor);

        return collect(Permission::values())
            ->groupBy(fn (string $name) => explode('.', $name)[0])
            ->map(fn ($names, string $module) => [
                'module' => $module,
                'actions' => $names->map(fn (string $name) => [
                    'value' => $name,
                    'action' => explode('.', $name, 2)[1],
                    'editable' => in_array($name, $owned, true),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
