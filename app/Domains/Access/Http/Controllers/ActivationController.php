<?php

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Exceptions\InvalidActivationException;
use App\Domains\Access\Http\Requests\ActivateRequest;
use App\Domains\Access\Services\ActivationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ActivationController extends Controller
{
    public function __construct(private readonly ActivationService $activations) {}

    public function show(string $token): Response
    {
        $activation = $this->activations->findValid($token);

        return Inertia::render('Auth/Activate', $activation === null
            ? ['valid' => false]
            : [
                'valid' => true,
                'name' => $activation->user->name,
                'email' => $activation->user->email,
                'school' => $activation->user->school?->name,
            ]);
    }

    public function store(ActivateRequest $request, string $token): RedirectResponse
    {
        try {
            $user = $this->activations->complete($token, $request->validated('password'));
        } catch (InvalidActivationException) {
            return redirect()->route('aktivasi.show', $token);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Akun aktif. Selamat datang.');
    }
}
