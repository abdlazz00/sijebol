<?php

namespace App\Domains\Access\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property bool $is_school_scoped
 * @property ?string $description
 */
class Role extends SpatieRole
{
    public const SUPER_ADMIN = 'super-admin';

    protected function casts(): array
    {
        return ['is_school_scoped' => 'boolean'];
    }

    public function isProtected(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }
}
