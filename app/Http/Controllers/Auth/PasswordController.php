<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Access\Services\SessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Password diganti sering karena akun dicurigai bocor: sesi di perangkat lain tidak boleh tetap hidup.
        $this->sessions->endOthers($request->user(), $request, $validated['password']);

        return back();
    }
}
