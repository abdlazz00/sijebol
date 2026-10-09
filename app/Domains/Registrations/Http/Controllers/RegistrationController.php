<?php

namespace App\Domains\Registrations\Http\Controllers;

use App\Domains\Registrations\Http\Requests\RegistrationRequest;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Registrations\Support\EntryPresenter;
use App\Domains\Schools\Services\SchoolService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Domains\Students\Services\StudentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrations,
        private readonly StudentService $students,
        private readonly EligibilityService $eligibility,
        private readonly SchoolService $schools,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Pendaftaran/Index', [
            'registrations' => $this->registrations->paginate()->through(fn (Registration $r) => $this->summary($r)),
            'filters' => $request->query('filter', []),
            'schools' => $this->schools->optionsFor($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $schoolId = $user->school_id ?? ($request->integer('school_id') ?: null);

        return Inertia::render('Pendaftaran/Form', [
            'registration' => null,
            'candidates' => $this->candidates($schoolId, null, $request),
            'selected' => [],
            'schools' => $this->schools->optionsFor($user),
            'school_id' => $schoolId,
        ]);
    }

    public function store(RegistrationRequest $request): RedirectResponse
    {
        $registration = $this->registrations->createDraft($request->validated(), $request->user());

        return redirect()->route('pendaftaran.show', $registration)->with('success', 'Draf pendaftaran disimpan.');
    }

    public function show(Registration $registration): Response
    {
        $registration->load(['school', 'creator', 'entries.student', 'entries.histories.user']);

        return Inertia::render('Pendaftaran/Show', [
            'registration' => $this->summary($registration) + [
                'expected_count' => $registration->expected_count,
                'created_by' => $registration->creator?->name,
            ],
            'entries' => $registration->entries->map(fn (RegistrationStudent $e) => EntryPresenter::row($e))->values(),
        ]);
    }

    public function edit(Request $request, Registration $registration): Response|RedirectResponse
    {
        if (! $registration->isDraft()) {
            return redirect()->route('pendaftaran.show', $registration)
                ->with('error', 'Pendaftaran yang sudah diajukan tidak dapat diubah.');
        }

        $registration->load(['entries.student.documents', 'entries.student.registrationEntries']);

        return Inertia::render('Pendaftaran/Form', [
            'registration' => ['id' => $registration->id, 'title' => $registration->title, 'expected_count' => $registration->expected_count],
            'candidates' => $this->candidates($registration->school_id, $registration, $request),
            // Pilihan awal dikirim terpisah: siswa terpilih bisa berada di halaman lain dari hasil pencarian.
            'selected' => $registration->entries->map(fn (RegistrationStudent $e) => $this->candidateRow($e->student, $registration, true))->values()->all(),
            'schools' => null,
            'school_id' => $registration->school_id,
        ]);
    }

    public function update(RegistrationRequest $request, Registration $registration): RedirectResponse
    {
        $this->registrations->updateDraft($registration, $request->validated());

        return redirect()->route('pendaftaran.show', $registration)->with('success', 'Draf pendaftaran diperbarui.');
    }

    public function destroy(Registration $registration): RedirectResponse
    {
        $this->registrations->deleteDraft($registration);

        return redirect()->route('pendaftaran.index')->with('success', 'Draf pendaftaran dihapus.');
    }

    public function submit(Request $request, Registration $registration): RedirectResponse
    {
        $this->registrations->submit($registration, $request->user());

        return redirect()->route('pendaftaran.show', $registration)->with('success', 'Pendaftaran diajukan untuk diverifikasi.');
    }

    public function resubmit(Request $request, Registration $registration, RegistrationStudent $entry): RedirectResponse
    {
        $this->registrations->resubmit($entry, $request->user());

        return redirect()->route('pendaftaran.show', $registration)->with('success', 'Siswa diajukan ulang.');
    }

    /** @return array<string, mixed> */
    private function summary(Registration $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'school' => $r->school?->name,
            'created_at' => $r->created_at->toIso8601String(),
            'submitted_at' => $r->submitted_at?->toIso8601String(),
            'students_count' => $r->entries->count(),
            'counts' => $r->entries->countBy(fn (RegistrationStudent $e) => $e->status->value)->all(),
        ];
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    private function candidates(?int $schoolId, ?Registration $registration, Request $request): LengthAwarePaginator
    {
        if ($schoolId === null) {
            return new LengthAwarePaginator([], 0, 20);
        }

        $q = $request->query('q');
        $selected = $registration?->entries->pluck('student_id')->all() ?? [];

        return $this->students->candidatesPage($schoolId, is_string($q) ? $q : null)
            ->through(fn (Student $s) => $this->candidateRow($s, $registration, in_array($s->id, $selected, true)));
    }

    /** @return array<string, mixed> */
    private function candidateRow(Student $s, ?Registration $registration, bool $selected): array
    {
        $check = $this->eligibility->check($s, null, $registration?->id);

        return [
            'id' => $s->id,
            'name' => $s->name,
            'nisn' => $s->nisn,
            'age' => $this->eligibility->age($s),
            'eligible' => $check['eligible'],
            'reasons' => $check['reasons'],
            'missing_docs' => collect(['kk', 'akta_lahir'])->diff($s->documents->pluck('type'))->isNotEmpty(),
            'selected' => $selected,
        ];
    }
}
