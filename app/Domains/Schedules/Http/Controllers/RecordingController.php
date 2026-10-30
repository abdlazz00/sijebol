<?php

namespace App\Domains\Schedules\Http\Controllers;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Http\Requests\RecordingRequest;
use App\Domains\Schedules\Http\Requests\UndoMarkingRequest;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\RecordingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecordingController extends Controller
{
    public function __construct(private readonly RecordingService $recording) {}

    public function show(Schedule $schedule): Response
    {
        $schedule->load(['school', 'entries.student']);
        $entries = $schedule->entries;

        return Inertia::render('Jadwal/Rekaman', [
            'schedule' => ScheduleController::detail($schedule),
            'entries' => $entries->where('status', RegistrationStatus::Terdaftar)
                ->map(fn (RegistrationStudent $e) => ScheduleController::checklistItem($e))->values(),
            'marked' => $entries->where('status', '!=', RegistrationStatus::Terdaftar)
                ->map(fn (RegistrationStudent $e) => ScheduleController::participant($e))->values(),
            'can_mark' => $schedule->isOpenForMarking(),
        ]);
    }

    public function undo(UndoMarkingRequest $request, Schedule $schedule, RegistrationStudent $entry): RedirectResponse
    {
        $this->recording->undo($entry, $request->validated('note'), $request->user());

        return redirect()->route('schedules.recording.show', $schedule)->with('success', 'Penandaan dibatalkan.');
    }

    public function store(RecordingRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->recording->mark(
            $schedule,
            $request->validated('entry_ids'),
            RegistrationStatus::from($request->validated('status')),
            $request->validated('note'),
            $request->user(),
        );

        return redirect()->route('schedules.recording.show', $schedule)->with('success', 'Penandaan disimpan.');
    }
}
