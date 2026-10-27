<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Students\Http\Requests\SelfStudentRequest;
use App\Domains\Students\Services\EligibilityService;
use App\Domains\Students\Services\StudentDocumentService;
use App\Domains\Students\Services\StudentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SelfStudentController extends Controller
{
    public function __construct(
        private readonly StudentService $students,
        private readonly EligibilityService $eligibility,
        private readonly StudentDocumentService $documents,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 404);

        $student = $user->student()->with(['documents', 'registrationEntries'])->first();
        $active = $student?->registrationEntries->first(fn ($e) => $e->status->isActive());

        return Inertia::render('Saya/Index', [
            'student' => $student ? [
                'id' => $student->id,
                'nik' => $student->nik,
                'nisn' => $student->nisn,
                'name' => $student->name,
                'birth_date' => $student->birth_date->toDateString(),
                'gender' => $student->gender,
            ] : null,
            'school' => $user->school->name,
            'account_name' => $user->name,
            'documents' => $student ? $this->documents->rows($student) : [],
            'eligibility' => $student ? $this->eligibility->check($student, null, $active?->id) : ['eligible' => false, 'reasons' => ['Data diri belum disimpan.']],
            'locked' => $student?->isLocked() ?? false,
        ]);
    }

    public function save(SelfStudentRequest $request): RedirectResponse
    {
        $first = ! $request->user()->student()->exists();
        $this->students->saveOwn($request->user(), $request->validated());

        return redirect()->route('me.show')->with('success', $first ? 'Data disimpan. Sekarang unggah dokumen.' : 'Data diperbarui.');
    }
}
