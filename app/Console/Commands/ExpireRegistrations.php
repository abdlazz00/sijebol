<?php

namespace App\Console\Commands;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExpireRegistrations extends Command
{
    protected $signature = 'sijebol:kedaluwarsakan';

    protected $description = 'Kedaluwarsakan pendaftaran yang lewat batas perbaikan atau sesinya sudah berakhir sebelum diputuskan (kursi dilepas)';

    public function handle(StatusTransitioner $transitions, NotificationService $notifications): int
    {
        $today = Schedule::today();
        $expired = 0;
        $failed = 0;

        // Tanpa scope global: perintah ini berjalan tanpa pengguna login. Diproses per pendaftaran agar satu kegagalan tidak menghentikan yang lain.
        RegistrationStudent::withoutGlobalScopes()
            ->with(['student', 'schedule'])
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w->where('status', RegistrationStatus::PerluPerbaikan->value)->where('fix_deadline_at', '<', now()))
                ->orWhere(fn (Builder $w) => $w
                    ->whereIn('status', RegistrationStatus::values([RegistrationStatus::MenungguVerifikasi, RegistrationStatus::PerluPerbaikan]))
                    ->whereHas('schedule', fn (Builder $s) => $s->withoutGlobalScopes()->where('date', '<', $today))))
            ->orderBy('id')
            ->chunkById(100, function (Collection $entries) use ($transitions, $notifications, $today, &$expired, &$failed) {
                foreach ($entries as $entry) {
                    $reason = null;

                    try {
                        // Syarat diperiksa ulang pada baris yang terkunci: siswa mungkin baru saja mengajukan ulang
                        // atau petugas memutuskan di antara pemilihan dan pemrosesan.
                        DB::transaction(function () use ($entry, $transitions, $today, &$reason) {
                            $fresh = RegistrationStudent::withoutGlobalScopes()->with('schedule')->lockForUpdate()->find($entry->id);
                            $reason = $fresh ? $this->reasonFor($fresh, $today) : null;

                            if ($reason !== null) {
                                $transitions->move($fresh, RegistrationStatus::Kedaluwarsa, null, $reason);
                            }
                        });
                    } catch (AccessRuleException) {
                        continue; // Sudah diputuskan pihak lain di antara pemeriksaan dan pembaruan.
                    } catch (Throwable $e) {
                        report($e);
                        $failed++;

                        continue;
                    }

                    if ($reason === null) {
                        continue;
                    }

                    $notifications->toStudent($entry->student, RegistrationNotification::seatReleased($reason));
                    $expired++;
                }
            });

        $this->info("{$expired} pendaftaran dikedaluwarsakan.");
        if ($failed > 0) {
            $this->error("{$failed} pendaftaran gagal diproses; lihat log.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Alasan kedaluwarsa menurut keadaan terbaru pendaftaran; null bila tidak (lagi) memenuhi syarat. */
    private function reasonFor(RegistrationStudent $entry, string $today): ?string
    {
        $pending = in_array($entry->status, [RegistrationStatus::MenungguVerifikasi, RegistrationStatus::PerluPerbaikan], true);

        if ($pending && $entry->schedule->date->toDateString() < $today) {
            return 'Sesi berakhir sebelum pendaftaran diputuskan.';
        }
        if ($entry->status === RegistrationStatus::PerluPerbaikan && $entry->fix_deadline_at?->isPast()) {
            return 'Batas waktu perbaikan terlewati.';
        }

        return null;
    }
}
