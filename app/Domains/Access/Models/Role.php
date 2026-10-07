<?php

namespace App\Domains\Access\Models;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property bool $is_school_scoped
 * @property ?string $description
 */
class Role extends SpatieRole
{
    use LogsActivity;

    public const SUPER_ADMIN = 'super-admin';

    protected function casts(): array
    {
        return ['is_school_scoped' => 'boolean'];
    }

    public function isProtected(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('akses')
            ->logOnly(['name', 'description', 'is_school_scoped'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
