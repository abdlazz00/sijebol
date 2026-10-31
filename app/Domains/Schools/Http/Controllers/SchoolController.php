<?php

namespace App\Domains\Schools\Http\Controllers;

use App\Domains\Schools\Http\Requests\SchoolRequest;
use App\Domains\Schools\Models\School;
use App\Domains\Schools\Services\SchoolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function __construct(private readonly SchoolService $schools) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sekolah/Index', [
            'schools' => $this->schools->list()->through(fn (School $s) => $s->only(['id', 'name', 'npsn', 'level', 'address', 'users_count', 'students_count', 'schedules_count'])),
            'filters' => $request->query('filter', []),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sekolah/Form', ['school' => null]);
    }

    public function store(SchoolRequest $request): RedirectResponse
    {
        $this->schools->create($request->validated());

        return redirect()->route('schools.index')->with('success', 'Sekolah ditambahkan.');
    }

    public function edit(School $school): Response
    {
        return Inertia::render('Sekolah/Form', ['school' => $school->only(['id', 'name', 'npsn', 'level', 'address'])]);
    }

    public function update(SchoolRequest $request, School $school): RedirectResponse
    {
        $this->schools->update($school, $request->validated());

        return redirect()->route('schools.index')->with('success', 'Sekolah diperbarui.');
    }

    public function destroy(Request $request, School $school): RedirectResponse
    {
        $this->schools->delete($school, $request->user());

        return back()->with('success', 'Sekolah dihapus.');
    }
}
