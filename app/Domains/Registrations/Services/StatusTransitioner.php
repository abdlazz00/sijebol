<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StatusTransitioner
{
    /** Status tujuan yang berarti keputusan petugas (mengisi decided_by/decided_at). */
    private const DECISIONS = [RegistrationStatus::Terdaftar, RegistrationStatus::PerluPerbaikan, RegistrationStatus::Ditolak];

    /**
     * Satu-satunya pintu perubahan status entri (`$actor` null = aksi sistem/scheduler): memeriksa peta transisi di bawah kunci baris
     * (dua petugas tidak bisa sama-sama memutuskan status lama) dan menulis riwayat.
     * `$attributes` ikut diperbarui dalam transaksi yang sama (mis. `schedule_id`).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function move(RegistrationStudent $entry, RegistrationStatus $to, ?User $actor, ?string $note = null, array $attributes = []): RegistrationStudent
    {
        return DB::transaction(function () use ($entry, $to, $actor, $note, $attributes) {
            $fresh = RegistrationStudent::query()->lockForUpdate()->findOrFail($entry->id);
            $from = $fresh->status;

            if (! in_array($to, $from->next(), true)) {
                throw new AccessRuleException("Status tidak dapat diubah dari {$from->label()} ke {$to->label()}.");
            }

            $fresh->update([
                'status' => $to,
                'note' => $note,
                ...($actor !== null && in_array($to, self::DECISIONS, true) ? ['decided_by' => $actor->id, 'decided_at' => now()] : []),
                ...$attributes,
            ]);
            $fresh->histories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'by_user_id' => $actor?->id,
                'note' => $note,
            ]);

            return $fresh;
        });
    }
}
