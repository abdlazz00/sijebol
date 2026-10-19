<?php

namespace App\Domains\Schedules\Http\Controllers;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Schedules\Http\Requests\ScanRequest;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScanService;
use App\Domains\Students\Services\EligibilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    public function __construct(
        private readonly ScanService $scanner,
        private readonly EligibilityService $eligibility,
    ) {}

    public function show(Schedule $schedule): Response
    {
        $schedule->load('school');
        [$from, $to] = $schedule->scanWindow();

        return Inertia::render('Jadwal/Pindai', [
            'schedule' => ScheduleController::detail($schedule),
            'counts' => $this->counts($schedule),
            'window' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
        ]);
    }

    /** JSON, bukan redirect: halaman kamera tidak dimuat ulang tiap pindaian. */
    public function store(ScanRequest $request, Schedule $schedule): JsonResponse
    {
        try {
            $entry = $this->scanner->scan($schedule, $request->validated('code'), $request->user());
        } catch (AccessRuleException $e) {
            return response()->json(['status' => 'rejected', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Tercatat sudah rekaman.',
            'student' => [
                'name' => $entry->student->name,
                'nisn' => $entry->student->nisn,
                'ktp_langsung' => $this->eligibility->directKtp($entry->student, $schedule->date),
            ],
            'scanned_at' => $entry->scanned_at->setTimezone('Asia/Jakarta')->toIso8601String(),
            'counts' => $this->counts($schedule),
        ]);
    }

    /** @return array{waiting: int, done: int} */
    private function counts(Schedule $schedule): array
    {
        $byStatus = $schedule->entries()->withoutGlobalScope('owner')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'waiting' => (int) ($byStatus[RegistrationStatus::Terdaftar->value] ?? 0),
            'done' => (int) ($byStatus[RegistrationStatus::SudahRekaman->value] ?? 0),
        ];
    }
}
