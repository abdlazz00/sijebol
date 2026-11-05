<?php

namespace App\Http\Controllers;

use App\Domains\Access\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileSessionController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    /** Keluarkan semua perangkat lain; sesi dan perangkat saat ini tetap masuk. */
    public function destroy(Request $request): RedirectResponse
    {
        // Hanya sesi database yang bisa diakhiri per pengguna; driver lain akan melapor sukses palsu.
        abort_unless(config('session.driver') === 'database', 501, 'Keluar dari perangkat lain butuh sesi database.');

        $request->validate(['current_password' => ['required', 'current_password']]);

        $this->sessions->endOthers($request->user(), $request, $request->input('current_password'));

        return back()->with('success', 'Perangkat lain sudah dikeluarkan.');
    }
}
