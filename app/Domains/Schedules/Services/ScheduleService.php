<?php

namespace App\Domains\Schedules\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    /** Pendaftaran yang belum sampai ke hari kehadiran; hanya ini yang ikut dibatalkan saat sesi dibatalkan. */
    private const CANCELLABLE = [RegistrationStatus::MenungguVerifikasi, RegistrationStatus::PerluPerbaikan, RegistrationStatus::Terdaftar];

    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly StatusTransitioner $transitions,
        private readonly NotificationService $notifications,
    ) {}

    /** @return LengthAwarePaginator<int, Schedule> */
    public function paginate(): LengthAwarePaginator
    {
        return $this->schedules->paginate();
    }

    /** @return Collection<int, Schedule> */
    public function upcoming(int $limit = 3): Collection
    {
        return $this->schedules->upcoming($limit);
    }

    /** @return list<array{id: int, date: string, school_name: string, participants: int, quota: int, waiting: int, present: int, location: string, status: string}> */
    public function upcomingTable(int $limit = 8): array
    {
        $today = Schedule::today();

        return $this->schedules->upcoming($limit)->map(function (Schedule $s) use ($today) {
            $isToday = $s->date->toDateString() === $today;

            return [
                'id' => $s->id,
                'date' => $s->date->toDateString(),
                'school_name' => $s->school->name,
                'participants' => $s->taken_count,
                'quota' => $s->quota,
                'waiting' => $s->waiting_count,
                'present' => $s->present_count,
                'location' => $s->location,
                'status' => $isToday ? 'Hari Ini' : 'Dijadwalkan',
            ];
        })->values()->all();
    }

    /** @return list<array{id: int, date: string, school_name: string, location: string, participants: int, status: string}> */
    public function calendarEvents(?int $year = null, ?int $month = null): array
    {
        $today = Schedule::today();
        $query = Schedule::query()->with('school')->whereNull('cancelled_at')
            ->withCount(['entries as taken_count' => fn ($q) => $q->whereIn('status', RegistrationStatus::values(RegistrationStatus::holdingSeat()))]);

        if ($year !== null && $month !== null) {
            $query->whereYear('date', $year)->whereMonth('date', $month);
        }

        return $query->orderBy('date')->get()->map(function (Schedule $s) use ($today) {
            $isToday = $s->date->toDateString() === $today;
            $isPast = $s->date->toDateString() < $today;

            return [
                'id' => $s->id,
                'date' => $s->date->toDateString(),
                'school_name' => $s->school->name,
                'location' => $s->location,
                'participants' => $s->taken_count,
                'status' => $isToday ? 'Hari Ini' : ($isPast ? 'Terlaksana' : 'Dijadwalkan'),
            ];
        })->values()->all();
    }

    /** @param array{school_id?: ?int, date: string, start_time: string, end_time: string, location: string, quota: int} $data */
    public function create(array $data, User $actor): Schedule
    {
        $schedule = $this->schedules->create([
            // Operator sekolah dikunci ke sekolahnya; staf kecamatan memilih sekolah.
            'school_id' => $actor->school_id ?? $data['school_id'],
            ...$this->attributes($data),
            'created_by' => $actor->id,
        ]);
        activity('jadwal')->performedOn($schedule)->causedBy($actor)->log('jadwal.dibuat');

        return $schedule;
    }

    /**
     * Tanggal terkunci bila sesi sudah punya pendaftar; kuota tidak boleh di bawah kursi yang sedang ditahan.
     * Pemeriksaan di bawah kunci baris sesi agar tidak berebut dengan pendaftaran siswa.
     *
     * @param  array{date: string, start_time: string, end_time: string, location: string, quota: int}  $data
     */
    public function update(Schedule $schedule, array $data, User $actor): Schedule
    {
        $locked = DB::transaction(function () use ($schedule, $data, $actor) {
            $locked = Schedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new AccessRuleException('Sesi yang dibatalkan tidak dapat diubah.');
            }
            if ($locked->isPast()) {
                throw new AccessRuleException('Sesi yang sudah lewat tidak dapat diubah.');
            }
            if ($data['date'] !== $locked->date->toDateString() && $locked->entries()->exists()) {
                throw ValidationException::withMessages(['date' => 'Tanggal tidak dapat diubah karena sesi sudah memiliki pendaftar. Batalkan sesi lalu buat sesi baru.']);
            }
            $taken = $locked->seatsTaken();
            if ($data['quota'] < $taken) {
                throw ValidationException::withMessages(['quota' => "Kuota tidak boleh di bawah jumlah kursi yang sedang ditahan ({$taken})."]);
            }

            $this->schedules->update($locked, $this->attributes($data));

            if ($locked->wasChanged(['date', 'start_time', 'end_time', 'location', 'quota'])) {
                activity('jadwal')->performedOn($locked)->causedBy($actor)->log('jadwal.diubah');
            }

            return $locked;
        });

        // Siswa diberi tahu hanya bila jam atau lokasi berubah (kuota dan penyimpanan tanpa perubahan tidak).
        if ($locked->wasChanged(['start_time', 'end_time', 'location'])) {
            $holders = $locked->entries()->whereIn('status', RegistrationStatus::values(self::CANCELLABLE))->with('student')->get();
            foreach ($holders as $entry) {
                $this->notifications->toStudent($entry->student, RegistrationNotification::sessionChanged($locked->date->toDateString(), $locked->start_time, $locked->end_time, $locked->location));
            }
        }

        return $locked;
    }

    /**
     * Membatalkan sesi: pendaftaran yang belum sampai hari kehadiran menjadi Dibatalkan, sisanya tidak disentuh.
     * Siswa yang pendaftarannya dibatalkan diberi tahu setelah transaksi.
     *
     * @return int jumlah pendaftaran yang dibatalkan
     */
    public function cancel(Schedule $schedule, string $note, User $actor): int
    {
        $result = DB::transaction(function () use ($schedule, $note, $actor) {
            $locked = Schedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new AccessRuleException('Sesi ini sudah dibatalkan.');
            }
            if ($locked->isPast()) {
                throw new AccessRuleException('Sesi sudah lewat dan tidak dapat dibatalkan.');
            }

            // Dikunci agar pembatalan oleh siswa yang bersamaan tidak membuat satu entri dipindah dua kali.
            $entries = $locked->entries()->whereIn('status', RegistrationStatus::values(self::CANCELLABLE))->orderBy('id')->lockForUpdate()->with('student')->get();
            foreach ($entries as $entry) {
                $this->transitions->move($entry, RegistrationStatus::Dibatalkan, $actor, $note);
            }

            $locked->update(['cancelled_at' => now()]);
            activity('jadwal')->performedOn($locked)->causedBy($actor)
                ->withProperties(['dibatalkan' => $entries->count(), 'catatan' => $note])->log('jadwal.dibatalkan');

            return ['locked' => $locked, 'entries' => $entries];
        });

        foreach ($result['entries'] as $entry) {
            $this->notifications->toStudent($entry->student, RegistrationNotification::sessionCancelled($result['locked']->date->toDateString(), $note));
        }

        return $result['entries']->count();
    }

    public function delete(Schedule $schedule, User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor) {
            // Kunci baris sesi sebelum memeriksa: siswa yang mendaftar bersamaan tidak lolos ke penghapusan (FK menolak dengan 500).
            Schedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            // Entri berstatus apa pun adalah jejak; sesi berriwayat tidak dapat dihapus.
            if ($schedule->entries()->withoutGlobalScope('owner')->exists()) {
                throw new AccessRuleException('Sesi masih memiliki pendaftar; batalkan sesi bila tidak jadi dilaksanakan.');
            }

            activity('jadwal')->performedOn($schedule)->causedBy($actor)->log('jadwal.dihapus');
            $this->schedules->delete($schedule);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        // Kolom time menyimpan detik; samakan bentuknya agar perubahan nyata terdeteksi dengan benar.
        return [
            'date' => $data['date'],
            'start_time' => $data['start_time'].':00',
            'end_time' => $data['end_time'].':00',
            'location' => $data['location'],
            'quota' => $data['quota'],
        ];
    }
}
