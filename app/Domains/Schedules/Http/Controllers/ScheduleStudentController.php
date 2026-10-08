<?php

namespace App\Domains\Schedules\Http\Controllers;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Http\Requests\AssignRequest;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleStudentController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function store(AssignRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->schedules->assign($schedule, $request->validated('entry_ids'), $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Siswa dijadwalkan.');
    }

    public function destroy(Request $request, Schedule $schedule, RegistrationStudent $entry): RedirectResponse
    {
        $this->schedules->unassign($entry, $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Siswa dikeluarkan dari jadwal.');
    }
}
