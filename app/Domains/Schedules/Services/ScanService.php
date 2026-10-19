<?php

namespace App\Domains\Schedules\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScanService
{
    public function __construct(private readonly StatusTransitioner $transitions) {}

    /**
     * Mencatat kehadiran dari pindaian QR: tiket Terdaftar pada sesi ini, di dalam jendela scan, menjadi Sudah rekaman.
     * Baris tiket dikunci sehingga dua pindaian serentak hanya menghasilkan satu perubahan.
     *
     * @throws AccessRuleException dengan pesan yang dapat dibaca petugas bila tiket ditolak
     */
    public function scan(Schedule $schedule, string $code, User $actor): RegistrationStudent
    {
        $rejection = null;

        // Penolakan tidak boleh mengembalikan transaksi: catatan audit-nya ditulis di dalam, pengecualian dilempar setelahnya.
        $entry = DB::transaction(function () use ($schedule, $code, $actor, &$rejection) {
            $entry = RegistrationStudent::withoutGlobalScopes()->with('student')->where('ticket_code', $code)->lockForUpdate()->first();
            $rejection = $this->rejection($schedule, $entry);

            if ($rejection !== null) {
                if ($entry) {
                    activity('jadwal')->performedOn($schedule)->causedBy($actor)
                        ->withProperties(['alasan' => $rejection, 'siswa_id' => $entry->student_id])->log('rekaman.pindai-ditolak');
                }

                return $entry;
            }

            $moved = $this->transitions->move($entry, RegistrationStatus::SudahRekaman, $actor, 'Dipindai petugas.', ['scanned_at' => now()]);
            activity('jadwal')->performedOn($schedule)->causedBy($actor)
                ->withProperties(['siswa_id' => $entry->student_id])->log('rekaman.dipindai');

            return $moved->setRelation('student', $entry->student);
        });

        if ($rejection !== null) {
            throw new AccessRuleException($rejection);
        }

        return $entry;
    }

    /** Alasan penolakan menurut urutan pemeriksaan; null bila tiket boleh diterima. */
    private function rejection(Schedule $schedule, ?RegistrationStudent $entry): ?string
    {
        if ($entry === null) {
            return 'Kode tiket tidak dikenal.';
        }
        if ($entry->schedule_id !== $schedule->id) {
            $other = $entry->schedule()->withoutGlobalScopes()->with('school')->firstOrFail();

            return "Tiket ini untuk sesi lain ({$other->school->name}, {$other->date->locale('id')->translatedFormat('l, j F Y')}).";
        }
        if ($schedule->isCancelled()) {
            return 'Sesi ini dibatalkan.';
        }
        if ($entry->status === RegistrationStatus::SudahRekaman) {
            return $entry->scanned_at
                ? 'Tiket sudah dipindai pukul '.$entry->scanned_at->setTimezone('Asia/Jakarta')->format('H.i').'.'
                : 'Siswa sudah ditandai sudah rekaman.';
        }
        if ($entry->status !== RegistrationStatus::Terdaftar) {
            return "Tiket tidak berlaku (status: {$entry->status->label()}).";
        }

        [$from, $to] = $schedule->scanWindow();
        if (now()->lt($from) || now()->gt($to)) {
            return "Di luar jam sesi. Pemindaian dibuka {$from->format('H.i')}–{$to->format('H.i')}.";
        }

        return null;
    }
}
