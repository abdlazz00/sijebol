<?php

namespace App\Domains\Access\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Mengakhiri sesi masuk pengguna. Hanya sesi driver database yang tercatat per pengguna (produksi memakainya). */
class SessionService
{
    /**
     * Keluarkan semua perangkat lain; sesi dan perangkat saat ini tetap masuk.
     * `$password` adalah password yang berlaku sekarang (teks asli).
     */
    public function endOthers(User $user, Request $request, string $password): void
    {
        // Putar remember token supaya cookie "Ingat saya" di perangkat lain tidak masuk lagi. logoutOtherDevices
        // tidak memutarnya sendiri; ia hanya memasang ulang cookie perangkat ini dengan token baru.
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        Auth::logoutOtherDevices($password);

        DB::table(config('session.table'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    /** Akhiri semua sesi pengguna (mis. sesudah reset password, saat pelaku belum tentu masuk di perangkat ini). */
    public function endAll(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        DB::table(config('session.table'))->where('user_id', $user->id)->delete();
    }
}
