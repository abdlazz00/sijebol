<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Schedules\Http\Controllers\ScheduleController;
use App\Domains\Schools\Services\SchoolService;
use App\Domains\Students\Http\Requests\StudentCorrectionRequest;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Domains\Students\Services\StudentDocumentService;
use App\Domains\Students\Services\StudentService;
use App\Http\Controllers\Controller;
use App\Support\Nik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentService $students,
        private readonly EligibilityService $eligibility,
        private readonly SchoolService $schools,
        private readonly StudentDocumentService $documents,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Siswa/Index', [
            'students' => $this->students->paginate()->through(fn (Student $s) => $this->row($s)),
            'filters' => $request->query('filter', []),
            'schools' => $this->schools->optionsFor($request->user()),
        ]);
    }

    public function show(Request $request, Student $student): Response
    {
        $student->load(['school', 'documents', 'registrationEntries.schedule']);

        return Inertia::render('Siswa/Show', [
            'student' => $this->row($student) + [
                'birth_date' => $student->birth_date->toDateString(),
                'gender' => $student->gender,
            ],
            'documents' => $request->user()->can('dokumen.lihat') ? $this->documents->rows($student) : [],
            'eligibility' => $this->eligibility->check($student),
            'locked' => $student->isLocked(),
            'registrations' => $student->registrationEntries->map(fn ($e) => [
                'id' => $e->id,
                'status' => $e->status->value,
                'schedule' => [
                    'id' => $e->schedule->id,
                    'date' => $e->schedule->date->toDateString(),
                    'start_time' => ScheduleController::time($e->schedule->start_time),
                    'end_time' => ScheduleController::time($e->schedule->end_time),
                    'location' => $e->schedule->location,
                ],
            ])->values(),
        ]);
    }

    public function edit(Request $request, Student $student): Response
    {
        return Inertia::render('Siswa/Form', [
            'student' => [
                'id' => $student->id, 'nik' => $student->nik, 'nisn' => $student->nisn, 'name' => $student->name,
                'birth_date' => $student->birth_date->toDateString(), 'gender' => $student->gender,
                'school_id' => $student->school_id,
            ],
            'schools' => $this->schools->optionsFor($request->user()),
        ]);
    }

    public function update(StudentCorrectionRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();
        $this->students->correct($student, Arr::except($data, ['note']), $data['note'], $request->user());

        return redirect()->route('siswa.show', $student)->with('success', 'Data siswa dikoreksi.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->students->delete($student);

        return redirect()->route('siswa.index')->with('success', 'Siswa dihapus.');
    }

    /** @return array<string, mixed> */
    private function row(Student $s): array
    {
        $check = $this->eligibility->check($s);
        $types = $s->documents->pluck('type')->all();

        return [
            'id' => $s->id,
            'name' => $s->name,
            'nisn' => $s->nisn,
            'nik' => Nik::mask($s->nik),
            'age' => $this->eligibility->age($s),
            'school' => $s->school?->name,
            'docs' => ['kk' => in_array('kk', $types, true), 'akta_lahir' => in_array('akta_lahir', $types, true)],
            'eligible' => $check['eligible'],
            'reasons' => $check['reasons'],
        ];
    }
}
