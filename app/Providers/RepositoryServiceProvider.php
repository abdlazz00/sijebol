<?php

namespace App\Providers;

use App\Domains\Access\Repositories\Contracts\RoleRepositoryInterface;
use App\Domains\Access\Repositories\Contracts\UserRepositoryInterface;
use App\Domains\Access\Repositories\EloquentRoleRepository;
use App\Domains\Access\Repositories\EloquentUserRepository;
use App\Domains\Audit\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Domains\Audit\Repositories\EloquentActivityLogRepository;
use App\Domains\Notifications\Repositories\Contracts\NotificationRepositoryInterface;
use App\Domains\Notifications\Repositories\EloquentNotificationRepository;
use App\Domains\Schools\Repositories\Contracts\SchoolRepositoryInterface;
use App\Domains\Schools\Repositories\EloquentSchoolRepository;
use App\Domains\Students\Repositories\Contracts\StudentRepositoryInterface;
use App\Domains\Students\Repositories\EloquentStudentRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        SchoolRepositoryInterface::class => EloquentSchoolRepository::class,
        RoleRepositoryInterface::class => EloquentRoleRepository::class,
        UserRepositoryInterface::class => EloquentUserRepository::class,
        NotificationRepositoryInterface::class => EloquentNotificationRepository::class,
        ActivityLogRepositoryInterface::class => EloquentActivityLogRepository::class,
        StudentRepositoryInterface::class => EloquentStudentRepository::class,
    ];
}
