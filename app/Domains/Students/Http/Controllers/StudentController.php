<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Schools\Services\SchoolService;
use App\Domains\Students\Http\Requests\StudentRequest;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Domains\Students\Services\StudentDocumentService;
use App\Domains\Students\Services\StudentService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Nik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'schools' => $this->schoolOptions($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Siswa/Form', ['student' => null, 'schools' => $this->schoolOptions($request->user())]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = $this->students->create($request->validated(), $request->user());

        return redirect()->route('siswa.show', $student)->with('success', 'Siswa ditambahkan.');
    }

    public function show(Request $request, Student $student): Response
    {
        $student->load(['school', 'documents']);

        return Inertia::render('Siswa/Show', [
            'student' => $this->row($student) + [
                'birth_date' => $student->birth_date->toDateString(),
                'gender' => $student->gender,
            ],
            'documents' => $request->user()->can('dokumen.lihat')
                ? $student->documents->map(fn ($d) => [
                    'type' => $d->type,
                    'name' => $this->documents->fileName($student, $d),
                    'size' => $d->size,
                    'url' => route('siswa.dokumen.download', [$student, $d->type]),
                ])->values()
                : [],
            'eligibility' => $this->eligibility->check($student),
        ]);
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('Siswa/Form', [
            'student' => [
                'id' => $student->id, 'nik' => $student->nik, 'nisn' => $student->nisn, 'name' => $student->name,
                'birth_date' => $student->birth_date->toDateString(), 'gender' => $student->gender,
            ],
            'schools' => null,
        ]);
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $this->students->update($student, $request->validated());

        return redirect()->route('siswa.show', $student)->with('success', 'Siswa diperbarui.');
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

    /** Daftar sekolah hanya untuk staf tanpa sekolah; operator terkunci ke sekolahnya. */
    private function schoolOptions(User $actor): mixed
    {
        return $actor->school_id === null
            ? $this->schools->options()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()
            : null;
    }
}
