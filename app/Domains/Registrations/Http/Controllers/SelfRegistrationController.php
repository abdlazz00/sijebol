<?php

namespace App\Domains\Registrations\Http\Controllers;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Schedules\Http\Controllers\ScheduleController;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman Sesi rekaman milik akun siswa; setiap aksi dibatasi ke akun siswa dan pendaftarannya sendiri. */
class SelfRegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations) {}

    public function index(Request $request): Response
    {
        $student = $this->student($request);
        $entry = $student ? $this->registrations->currentEntry($student) : null;
        $active = $entry?->status->isActive() ?? false;

        return Inertia::render('Saya/Sesi', [
            'ready' => $student !== null,
            'close_days' => (int) config('sijebol.registration_close_days'),
            'entry' => $entry ? $this->entry($entry) : null,
            'schedules' => $student && ! $active ? $this->registrations->openSchedules($student) : [],
        ]);
    }

    public function register(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->student($request);
        $this->registrations->register($request->user(), $schedule);

        return redirect()->route('saya.sesi')->with('success', 'Pendaftaran terkirim. Petugas akan memeriksa data Anda.');
    }

    public function cancel(Request $request, RegistrationStudent $entry): RedirectResponse
    {
        $this->student($request);
        $this->registrations->cancel($entry, $request->user());

        return redirect()->route('saya.sesi')->with('success', 'Pendaftaran dibatalkan.');
    }

    public function resubmit(Request $request, RegistrationStudent $entry): RedirectResponse
    {
        $this->student($request);
        $this->registrations->resubmit($entry, $request->user());

        return redirect()->route('saya.sesi')->with('success', 'Pendaftaran diajukan ulang. Petugas akan memeriksanya kembali.');
    }

    /** Akun non-siswa tidak diberi tahu bahwa rute ini ada. */
    private function student(Request $request): ?Student
    {
        abort_unless($request->user()->isStudent(), 404);

        return $request->user()->student()->first();
    }

    /** @return array<string, mixed> */
    private function entry(RegistrationStudent $entry): array
    {
        $changeable = $entry->schedule->allowsStudentChanges();

        return [
            'id' => $entry->id,
            'status' => $entry->status->value,
            'note' => $entry->note,
            'can_cancel' => $changeable && in_array($entry->status, [RegistrationStatus::MenungguVerifikasi, RegistrationStatus::PerluPerbaikan, RegistrationStatus::Terdaftar], true),
            'can_resubmit' => $changeable && $entry->status === RegistrationStatus::PerluPerbaikan,
            'schedule' => [
                'id' => $entry->schedule->id,
                'date' => $entry->schedule->date->toDateString(),
                'start_time' => ScheduleController::time($entry->schedule->start_time),
                'end_time' => ScheduleController::time($entry->schedule->end_time),
                'location' => $entry->schedule->location,
            ],
        ];
    }
}
