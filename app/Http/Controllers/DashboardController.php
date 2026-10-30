<?php

namespace App\Http\Controllers;

use App\Domains\Reports\Services\DashboardService;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ScheduleService $schedules, DashboardService $dashboard): Response
    {
        $canJadwal = $request->user()->can('jadwal.lihat');

        return Inertia::render('Dashboard', [
            'schedules' => $canJadwal
                ? $schedules->upcoming(3)->map(fn (Schedule $s) => [
                    'date' => $s->date->toDateString(),
                    'school' => $s->school?->name,
                    'location' => $s->location,
                    'participants' => $s->entries_count,
                ])->values()
                : [],
            'schedules_table' => $canJadwal ? $schedules->upcomingTable(8) : [],
            'calendar_events' => $canJadwal ? $schedules->calendarEvents((int) now()->year) : [],
            ...$dashboard->forUser($request->user()),
        ]);
    }
}
