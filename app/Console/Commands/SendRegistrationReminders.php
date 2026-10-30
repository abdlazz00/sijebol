<?php

namespace App\Console\Commands;

use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SendRegistrationReminders extends Command
{
    protected $signature = 'sijebol:kirim-pengingat';

    protected $description = 'Kirim pengingat batas perbaikan (tinggal 24 jam) dan pengingat sesi rekaman H-1 kepada siswa';

    public function handle(NotificationService $notifications): int
    {
        $now = Carbon::now('Asia/Jakarta');
        $fix = 0;
        $session = 0;

        // Penanda *_reminded_at diisi walau siswa tanpa akun, supaya entri itu tidak dipilih ulang setiap jam.
        RegistrationStudent::withoutGlobalScopes()->with('student')
            ->where('status', RegistrationStatus::PerluPerbaikan->value)
            ->whereNull('fix_reminded_at')
            ->where('fix_deadline_at', '>', $now)->where('fix_deadline_at', '<=', $now->copy()->addDay())
            ->orderBy('id')->each(function (RegistrationStudent $entry) use ($notifications, $now, &$fix) {
                $notifications->toStudent($entry->student, RegistrationNotification::fixReminder($entry->fix_deadline_at));
                $entry->update(['fix_reminded_at' => $now]);
                $fix++;
            });

        if ($now->hour >= (int) config('sijebol.reminder_hour')) {
            $tomorrow = Carbon::parse(Schedule::today())->addDay()->toDateString();

            RegistrationStudent::withoutGlobalScopes()->with(['student', 'schedule'])
                ->where('status', RegistrationStatus::Terdaftar->value)
                ->whereNull('session_reminded_at')
                ->whereHas('schedule', fn (Builder $s) => $s->withoutGlobalScopes()->where('date', $tomorrow)->whereNull('cancelled_at'))
                ->orderBy('id')->each(function (RegistrationStudent $entry) use ($notifications, $now, &$session) {
                    $schedule = $entry->schedule;
                    $notifications->toStudent($entry->student, RegistrationNotification::sessionReminder($schedule->date->toDateString(), $schedule->start_time, $schedule->end_time, $schedule->location));
                    $entry->update(['session_reminded_at' => $now]);
                    $session++;
                });
        }

        $this->info("{$fix} pengingat perbaikan, {$session} pengingat sesi dikirim.");

        return self::SUCCESS;
    }
}
