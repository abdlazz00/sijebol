<?php

namespace App\Domains\Registrations\Http\Controllers;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Http\Requests\ApproveManyRequest;
use App\Domains\Registrations\Http\Requests\DecisionRequest;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\VerificationService;
use App\Domains\Registrations\Support\EntryPresenter;
use App\Domains\Schedules\Http\Controllers\ScheduleController;
use App\Domains\Schedules\Models\Schedule;
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
                'student' => ['id' => $e->student->id, 'name' => $e->student->name, 'nisn' => $e->student->nisn, 'ktp_langsung' => $this->eligibility->directKtp($e->student, $e->schedule->date)],
                'school' => $e->schedule->school?->name,
                'session' => $this->session($e->schedule),
                'since' => $e->updated_at->toIso8601String(),
            ]),
            'filters' => $request->query('filter', []),
            'schools' => $this->schools->optionsFor($request->user()),
            // Opsi filter sesi: hanya sesi yang sedang punya antrean.
            'sessions' => Schedule::query()->with('school')
                ->whereHas('entries', fn ($q) => $q->where('status', RegistrationStatus::MenungguVerifikasi->value))
                ->orderBy('date')->orderBy('start_time')->orderBy('id')->get()
                ->map(fn (Schedule $s) => $this->session($s) + ['school' => $s->school?->name])->values(),
        ]);
    }

    public function show(Request $request, RegistrationStudent $entry): Response
    {
        $entry->load(['student.school', 'student.documents', 'student.registrationEntries', 'schedule', 'histories.user']);
        $student = $entry->student;
        $canRequestFix = ! $entry->schedule->isOpenForMarking();

        return Inertia::render('Verifikasi/Show', [
            'entry' => EntryPresenter::row($entry) + [
                'session' => $this->session($entry->schedule),
                // Perbaikan hanya boleh diminta sampai sehari sebelum sesi; tenggat yang akan dikirim ditampilkan lebih dulu.
                'can_request_fix' => $canRequestFix,
                'fix_deadline' => $entry->fix_deadline_at?->toIso8601String(),
                'fix_deadline_preview' => $canRequestFix ? $entry->schedule->fixDeadlineFrom(now())->toIso8601String() : null,
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nisn' => $student->nisn,
                'nik' => Nik::mask($student->nik),
                'birth_date' => $student->birth_date->toDateString(),
                'age' => $this->eligibility->age($student, $entry->schedule->date),
                'ktp_langsung' => $this->eligibility->directKtp($student, $entry->schedule->date),
                'gender' => $student->gender,
                'school' => $student->school?->name,
            ],
            'documents' => $request->user()->can('dokumen.lihat') ? $this->documents->rows($student) : [],
            'eligibility' => $this->eligibility->check($student, $entry->schedule->date, $entry->id),
        ]);
    }

    public function approveMany(ApproveManyRequest $request): RedirectResponse
    {
        $count = $this->verification->approveMany($request->validated('entry_ids'), $request->user());

        return redirect()->route('verifikasi.index')->with('success', "{$count} siswa disetujui.");
    }

    public function decide(DecisionRequest $request, RegistrationStudent $entry): RedirectResponse
    {
        $this->verification->decide($entry, $request->validated('decision'), $request->validated('note'), $request->user());

        return redirect()->route('verifikasi.index')->with('success', 'Keputusan disimpan.');
    }

    /** @return array{id: int, date: string, start_time: string, end_time: string, location: string} */
    private function session(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'date' => $schedule->date->toDateString(),
            'start_time' => ScheduleController::time($schedule->start_time),
            'end_time' => ScheduleController::time($schedule->end_time),
            'location' => $schedule->location,
        ];
    }
}
