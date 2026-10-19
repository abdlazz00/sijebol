<?php

namespace App\Http\Controllers;

use App\Domains\Reports\Services\DashboardService;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ScheduleService $schedules, DashboardService $dashboard): Response|RedirectResponse
    {
        // Siswa tidak punya beranda staf; reflash agar toast aktivasi tidak hilang di pengalihan ini.
        if ($request->user()->isStudent()) {
            $request->session()->reflash();

            return redirect()->route('saya.show');
        }

        $canJadwal = $request->user()->can('jadwal.lihat');

        return Inertia::render('Dashboard', [
            'schedules' => $canJadwal
                ? $schedules->upcoming(3)->map(fn (Schedule $s) => [
                    'date' => $s->date->toDateString(),
                    'school' => $s->school?->name,
                    'location' => $s->location,
                    'participants' => $s->taken_count,
                ])->values()
                : [],
            'schedules_table' => $canJadwal ? $schedules->upcomingTable(8) : [],
            'calendar_events' => $canJadwal ? $schedules->calendarEvents((int) now()->year) : [],
            ...$dashboard->forUser($request->user()),
        ]);
    }
}
