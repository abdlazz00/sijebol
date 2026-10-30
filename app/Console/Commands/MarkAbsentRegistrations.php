<?php

namespace App\Console\Commands;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class MarkAbsentRegistrations extends Command
{
    protected $signature = 'sijebol:tandai-tidak-hadir';

    protected $description = 'Tandai Tidak hadir pendaftaran Terdaftar yang tidak terpindai sampai sesinya lewat (mulai hari setelah sesi; kursi dilepas)';

    public function handle(StatusTransitioner $transitions): int
    {
        $today = Schedule::today();
        $marked = 0;
        $failed = 0;

        // Tanpa scope global: perintah ini berjalan tanpa pengguna login. Diproses per pendaftaran agar satu kegagalan tidak menghentikan yang lain.
        RegistrationStudent::withoutGlobalScopes()
            ->where('status', RegistrationStatus::Terdaftar->value)
            ->whereHas('schedule', fn (Builder $s) => $s->withoutGlobalScopes()->where('date', '<', $today))
            ->orderBy('id')
            ->chunkById(100, function (Collection $entries) use ($transitions, &$marked, &$failed) {
                foreach ($entries as $entry) {
                    try {
                        // Status diperiksa ulang pada baris yang terkunci: petugas mungkin baru saja memindai atau menandainya.
                        $moved = DB::transaction(function () use ($entry, $transitions) {
                            $fresh = RegistrationStudent::withoutGlobalScopes()->lockForUpdate()->find($entry->id);
                            if ($fresh?->status !== RegistrationStatus::Terdaftar) {
                                return false;
                            }
                            $transitions->move($fresh, RegistrationStatus::TidakHadir, null, 'Tidak terpindai sampai sesi berakhir.');

                            return true;
                        });
                    } catch (AccessRuleException) {
                        continue; // Sudah diubah pihak lain di antara pemeriksaan dan pembaruan.
                    } catch (Throwable $e) {
                        report($e);
                        $failed++;

                        continue;
                    }

                    $marked += $moved ? 1 : 0;
                }
            });

        $this->info("{$marked} pendaftaran ditandai tidak hadir.");
        if ($failed > 0) {
            $this->error("{$failed} pendaftaran gagal diproses; lihat log.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
