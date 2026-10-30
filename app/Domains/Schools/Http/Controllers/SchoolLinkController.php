<?php

namespace App\Domains\Schools\Http\Controllers;

use App\Domains\Schools\Models\School;
use App\Domains\Schools\Services\SchoolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolLinkController extends Controller
{
    public function __construct(private readonly SchoolService $schools) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sekolah/Tautan', ['schools' => $this->schools->linksFor($request->user())]);
    }

    public function reset(Request $request, School $school): RedirectResponse
    {
        $this->schools->resetRegistrationToken($school, $request->user());

        return back()->with('success', "Link pendaftaran {$school->name} direset.");
    }
}
