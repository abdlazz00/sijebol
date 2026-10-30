<?php

namespace App\Domains\Students\Services;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\ActivationService;
use App\Domains\Notifications\Notifications\StudentSignupNotification;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StudentSignupService
{
    public function __construct(private readonly ActivationService $activations) {}

    public function signup(School $school, string $name, string $email): void
    {
        $email = mb_strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = DB::transaction(function () use ($school, $name, $email) {
                $user = User::create(['name' => $name, 'email' => $email, 'school_id' => $school->id]);
                $user->assignRole(Role::SISWA);

                return $user;
            });
        } elseif (! $user->isStudent() || $user->activated_at !== null) {
            return;
        }

        $user->notify(new StudentSignupNotification(
            $this->activations->issue($user),
            $user->school->name,
        ));
    }
}
