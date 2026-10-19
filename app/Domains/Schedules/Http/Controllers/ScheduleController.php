<?php

namespace App\Domains\Schedules\Http\Controllers;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Http\Requests\CancelScheduleRequest;
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
        $holding = RegistrationStatus::holdingSeat();

        return Inertia::render('Jadwal/Index', [
            'schedules' => $this->schedules->paginate()->through(fn (Schedule $s) => [
                'id' => $s->id,
                'date' => $s->date->toDateString(),
                'start_time' => self::time($s->start_time),
                'end_time' => self::time($s->end_time),
                'location' => $s->location,
                'school' => $s->school?->name,
                'is_past' => $s->isPast(),
                'cancelled' => $s->isCancelled(),
                'quota' => $s->quota,
                'taken' => $s->entries->filter(fn (RegistrationStudent $e) => in_array($e->status, $holding, true))->count(),
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

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Sesi dibuat.');
    }

    public function show(Schedule $schedule): Response
    {
        $schedule->load(['school', 'creator', 'entries' => fn ($q) => $q->orderBy('id'), 'entries.student']);

        return Inertia::render('Jadwal/Show', [
            'schedule' => self::detail($schedule) + ['created_by' => $schedule->creator?->name],
            'participants' => $schedule->entries->map(fn (RegistrationStudent $e) => self::participant($e))->values(),
        ]);
    }

    public function edit(Schedule $schedule): Response
    {
        return Inertia::render('Jadwal/Form', [
            'schedule' => [
                'id' => $schedule->id,
                'date' => $schedule->date->toDateString(),
                'start_time' => self::time($schedule->start_time),
                'end_time' => self::time($schedule->end_time),
                'location' => $schedule->location,
                'quota' => $schedule->quota,
                'has_participants' => $schedule->entries()->exists(),
            ],
            'schools' => null,
        ]);
    }

    public function destroy(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->schedules->delete($schedule, $request->user());

        return redirect()->route('jadwal.index')->with('success', 'Sesi dihapus.');
    }

    public function update(ScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->schedules->update($schedule, $request->validated(), $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Sesi diperbarui.');
    }

    public function cancel(CancelScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $count = $this->schedules->cancel($schedule, $request->validated('note'), $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', "Sesi dibatalkan; {$count} pendaftaran ikut dibatalkan.");
    }

    /** Jam `HH:MM:SS` dari basis data menjadi `HH:MM`. */
    public static function time(string $value): string
    {
        return substr($value, 0, 5);
    }

    /** @return array<string, mixed> */
    public static function detail(Schedule $s): array
    {
        return [
            'id' => $s->id,
            'date' => $s->date->toDateString(),
            'start_time' => self::time($s->start_time),
            'end_time' => self::time($s->end_time),
            'location' => $s->location,
            'school' => $s->school?->name,
            'quota' => $s->quota,
            'taken' => $s->seatsTaken(),
            'cancelled' => $s->isCancelled(),
            'is_past' => $s->isPast(),
            'last_registration_day' => $s->lastRegistrationDay(),
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

    /** @return array{id: int, name: string, nisn: string} */
    public static function checklistItem(RegistrationStudent $e): array
    {
        return ['id' => $e->id, 'name' => $e->student->name, 'nisn' => $e->student->nisn];
    }
}
