<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Access\Services\ActivationService;
use App\Domains\Schools\Services\SchoolService;
use App\Domains\Students\Http\Requests\StudentSignupRequest;
use App\Domains\Students\Services\StudentSignupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentSignupController extends Controller
{
    public function __construct(
        private readonly SchoolService $schools,
        private readonly StudentSignupService $signup
    ) {}

    public function show(string $token): Response
    {
        $school = $this->schools->findByRegistrationToken($token);

        return Inertia::render('Daftar/Index', [
            'valid' => $school !== null,
            ...($school !== null ? ['school' => $school->name] : []),
            'sent' => (bool) session('sent'),
            'ttl_days' => ActivationService::TTL_DAYS,
        ]);
    }

    public function store(StudentSignupRequest $request, string $token): RedirectResponse
    {
        $school = $this->schools->findByRegistrationToken($token);

        if ($school !== null) {
            $this->signup->signup($school, $request->validated('name'), $request->validated('email'));
        }

        // Respons sama untuk semua kasus pada token yang sah.
        return redirect()->route('register.show', $token)->with('sent', $school !== null);
    }
}
