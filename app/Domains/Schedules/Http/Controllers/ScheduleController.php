<?php

namespace App\Domains\Schedules\Http\Controllers;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Http\Requests\ScheduleRequest;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Services\SchoolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $schedules,
        private readonly SchoolService $schools,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Jadwal/Index', [
            'schedules' => $this->schedules->paginate()->through(fn (Schedule $s) => [
                'id' => $s->id,
                'date' => $s->date->toDateString(),
                'location' => $s->location,
                'school' => $s->school?->name,
                'is_past' => $s->isPast(),
                'participants_count' => $s->entries->count(),
                'counts' => $s->entries->countBy(fn (RegistrationStudent $e) => $e->status->value)->all(),
            ]),
            'filters' => $request->query('filter', []),
            'schools' => $this->schools->optionsFor($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Jadwal/Form', ['schedule' => null, 'schools' => $this->schools->optionsFor($request->user())]);
    }

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $schedule = $this->schedules->create($request->validated(), $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Jadwal dibuat.');
    }

    public function show(Request $request, Schedule $schedule): Response
    {
        $schedule->load(['school', 'creator', 'entries.student']);
        $canAssign = $request->user()->can('jadwal.ubah') && ! $schedule->isPast();

        return Inertia::render('Jadwal/Show', [
            'schedule' => self::detail($schedule) + ['created_by' => $schedule->creator?->name],
            'participants' => $schedule->entries->map(fn (RegistrationStudent $e) => self::participant($e))->values(),
            'candidates' => $canAssign
                ? $this->schedules->candidates($schedule)->map(fn (RegistrationStudent $e) => self::checklistItem($e))->values()
                : [],
        ]);
    }

    public function edit(Schedule $schedule): Response
    {
        return Inertia::render('Jadwal/Form', [
            'schedule' => [
                'id' => $schedule->id,
                'date' => $schedule->date->toDateString(),
                'location' => $schedule->location,
                'has_participants' => $schedule->entries()->exists(),
            ],
            'schools' => null,
        ]);
    }

    public function update(ScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->schedules->update($schedule, $request->validated(), $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Jadwal diperbarui.');
    }

    /** @return array<string, mixed> */
    public static function detail(Schedule $s): array
    {
        return [
            'id' => $s->id,
            'date' => $s->date->toDateString(),
            'location' => $s->location,
            'school' => $s->school?->name,
            'is_past' => $s->isPast(),
        ];
    }

    /** @return array<string, mixed> */
    public static function participant(RegistrationStudent $e): array
    {
        return [
            'id' => $e->id,
            'student' => ['id' => $e->student->id, 'name' => $e->student->name, 'nisn' => $e->student->nisn],
            'status' => $e->status->value,
        ];
    }

    /** @return array{id: int, name: string, nisn: string, hint: ?string} */
    public static function checklistItem(RegistrationStudent $e): array
    {
        return ['id' => $e->id, 'name' => $e->student->name, 'nisn' => $e->student->nisn, 'hint' => $e->registration?->title];
    }
}
