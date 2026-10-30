<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileSessionController extends Controller
{
    /** Keluarkan semua perangkat lain; sesi dan perangkat saat ini tetap masuk. */
    public function destroy(Request $request): RedirectResponse
    {
        // Hanya sesi database yang bisa diakhiri per pengguna; driver lain akan melapor sukses palsu.
        abort_unless(config('session.driver') === 'database', 501, 'Keluar dari perangkat lain butuh sesi database.');

        $request->validate(['current_password' => ['required', 'current_password']]);

        // Putar remember token supaya cookie "Ingat saya" di perangkat lain tidak masuk lagi. logoutOtherDevices
        // tidak memutarnya sendiri; ia hanya memasang ulang cookie perangkat ini dengan token baru.
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();
        Auth::logoutOtherDevices($request->input('current_password'));

        DB::table(config('session.table'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'Perangkat lain sudah dikeluarkan.');
    }
}
