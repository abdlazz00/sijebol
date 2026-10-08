<?php

namespace App\Domains\Registrations\Support;

use App\Domains\Registrations\Models\RegistrationStatusHistory;
use App\Domains\Registrations\Models\RegistrationStudent;

final class EntryPresenter
{
    /**
     * Butuh relasi `student` dan `histories.user` sudah dimuat.
     *
     * @return array<string, mixed>
     */
    public static function row(RegistrationStudent $entry): array
    {
        return [
            'id' => $entry->id,
            'student' => ['id' => $entry->student->id, 'name' => $entry->student->name, 'nisn' => $entry->student->nisn],
            'status' => $entry->status->value,
            'note' => $entry->note,
            'decided_at' => $entry->decided_at?->toIso8601String(),
            'history' => $entry->histories->map(fn (RegistrationStatusHistory $h) => [
                'from' => $h->from_status->value,
                'to' => $h->to_status->value,
                'by' => $h->user->name,
                'note' => $h->note,
                'at' => $h->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
