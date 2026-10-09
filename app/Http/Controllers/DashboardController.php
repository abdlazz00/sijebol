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
        return Inertia::render('Dashboard', [
            'schedules' => $request->user()->can('jadwal.lihat')
                ? $schedules->upcoming(3)->map(fn (Schedule $s) => [
                    'date' => $s->date->toDateString(),
                    'school' => $s->school?->name,
                    'location' => $s->location,
                    'participants' => $s->entries_count,
                ])->values()
                : [],
            ...$dashboard->forUser($request->user()),
        ]);
    }
}
