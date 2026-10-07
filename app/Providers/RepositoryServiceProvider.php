<?php

namespace App\Providers;

use App\Domains\Access\Repositories\Contracts\RoleRepositoryInterface;
use App\Domains\Access\Repositories\EloquentRoleRepository;
use App\Domains\Schools\Repositories\Contracts\SchoolRepositoryInterface;
use App\Domains\Schools\Repositories\EloquentSchoolRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        SchoolRepositoryInterface::class => EloquentSchoolRepository::class,
        RoleRepositoryInterface::class => EloquentRoleRepository::class,
    ];
}
