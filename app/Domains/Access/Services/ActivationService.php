<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Exceptions\InvalidActivationException;
use App\Domains\Access\Models\AccountActivation;
use App\Domains\Notifications\Notifications\AccountActivatedNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivationService
{
    public const TTL_DAYS = 3;

    /** Buat token baru dan cabut yang belum terpakai. Mengembalikan token polos (tidak disimpan). */
    public function issue(User $user): string
    {
        $token = Str::random(64);

        DB::transaction(function () use ($user, $token) {
            $user->activations()->whereNull('used_at')->delete();
            $user->activations()->create([
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(self::TTL_DAYS),
            ]);
        });

        activity('akses')->performedOn($user)->causedBy(auth()->user())->log('aktivasi.dibuat');

        return $token;
    }

    public function findValid(string $token): ?AccountActivation
    {
        $activation = AccountActivation::query()
            ->with('user.school')
            ->where('token_hash', hash('sha256', $token))
            ->pending()
            ->first();

        return $activation?->user->is_active && $activation->user->activated_at === null ? $activation : null;
    }

    /** @throws InvalidActivationException */
    public function complete(string $token, string $password): User
    {
        $user = DB::transaction(function () use ($token, $password) {
            $activation = AccountActivation::query()
                ->where('token_hash', hash('sha256', $token))
                ->pending()
                ->lockForUpdate()
                ->first();

            $user = $activation?->user;

            if ($user === null || ! $user->is_active || $user->activated_at !== null) {
                throw new InvalidActivationException;
            }

            $user->forceFill(['password' => $password, 'activated_at' => now()])->save();
            $activation->update(['used_at' => now()]);

            activity('akses')->performedOn($user)->causedBy($user)->log('aktivasi.selesai');

            return $user;
        });

        // Dikirim setelah transaksi selesai (tidak ikut rollback); notifikasi antre sehingga respons tidak melambat.
        $creator = $user->creator;
        if ($creator !== null && $creator->is_active) {
            $creator->notify(new AccountActivatedNotification($user));
        }

        return $user;
    }
}
