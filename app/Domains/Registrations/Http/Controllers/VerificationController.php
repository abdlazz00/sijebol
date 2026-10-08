<?php

namespace App\Domains\Registrations\Http\Controllers;

use App\Domains\Registrations\Http\Requests\DecisionRequest;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\VerificationService;
use App\Domains\Registrations\Support\EntryPresenter;
use App\Domains\Schools\Services\SchoolService;
use App\Domains\Students\Services\EligibilityService;
use App\Domains\Students\Services\StudentDocumentService;
use App\Http\Controllers\Controller;
use App\Support\Nik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function __construct(
        private readonly VerificationService $verification,
        private readonly EligibilityService $eligibility,
        private readonly StudentDocumentService $documents,
        private readonly SchoolService $schools,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Verifikasi/Index', [
            'queue' => $this->verification->queue()->through(fn (RegistrationStudent $e) => [
                'id' => $e->id,
                'student' => ['id' => $e->student->id, 'name' => $e->student->name, 'nisn' => $e->student->nisn],
                'school' => $e->registration->school?->name,
                'registration' => $e->registration->title,
                'since' => $e->updated_at->toIso8601String(),
            ]),
            'filters' => $request->query('filter', []),
            'schools' => $this->schools->optionsFor($request->user()),
        ]);
    }

    public function show(Request $request, RegistrationStudent $entry): Response
    {
        $entry->load(['student.school', 'student.documents', 'student.registrationEntries', 'registration', 'histories.user']);
        $student = $entry->student;

        return Inertia::render('Verifikasi/Show', [
            'entry' => EntryPresenter::row($entry) + ['registration' => ['id' => $entry->registration->id, 'title' => $entry->registration->title]],
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nisn' => $student->nisn,
                'nik' => Nik::mask($student->nik),
                'birth_date' => $student->birth_date->toDateString(),
                'age' => $this->eligibility->age($student),
                'gender' => $student->gender,
                'school' => $student->school?->name,
            ],
            'documents' => $request->user()->can('dokumen.lihat') ? $this->documents->rows($student) : [],
            'eligibility' => $this->eligibility->check($student, null, $entry->registration_id),
        ]);
    }

    public function decide(DecisionRequest $request, RegistrationStudent $entry): RedirectResponse
    {
        $this->verification->decide($entry, $request->validated('decision'), $request->validated('note'), $request->user());

        return redirect()->route('verifikasi.index')->with('success', 'Keputusan disimpan.');
    }
}
