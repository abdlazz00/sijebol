<?php

namespace App\Domains\Students\Services;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\ActivationService;
use App\Domains\Notifications\Notifications\StudentSignupNotification;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class StudentSignupService
{
    /** Batas email pendaftaran per alamat: membatasi spam ke satu alamat dan penerbitan ulang link. */
    private const MAX_PER_WINDOW = 3;

    private const WINDOW_SECONDS = 900;

    public function __construct(private readonly ActivationService $activations) {}

    public function signup(School $school, string $name, string $email): void
    {
        $email = mb_strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && (! $user->isStudent() || $user->activated_at !== null)) {
            return;
        }

        // Di atas batas, permintaan diabaikan diam-diam: respons ke pengguna tetap sama.
        RateLimiter::attempt('siswa-daftar:'.sha1($email), self::MAX_PER_WINDOW, function () use ($school, $name, $email, $user) {
            $user ??= DB::transaction(function () use ($school, $name, $email) {
                $user = User::create(['name' => $name, 'email' => $email, 'school_id' => $school->id]);
                $user->assignRole(Role::SISWA);

                return $user;
            });

            $user->notify(new StudentSignupNotification(
                $this->activations->issue($user),
                $user->school->name,
            ));
        }, self::WINDOW_SECONDS);
    }
}
