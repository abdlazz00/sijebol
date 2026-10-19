<?php

namespace App\Domains\Schedules\Models;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property string $location
 * @property int $quota
 * @property Carbon|null $cancelled_at
 * @property int $taken_count Hanya ada pada query ber-withCount (kursi tertahan).
 * @property int $waiting_count Hanya ada pada query ber-withCount (menunggu verifikasi).
 * @property int $present_count Hanya ada pada query ber-withCount (sudah rekaman).
 * @property int $created_by
 */
#[UseFactory(ScheduleFactory::class)]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory, ScopedBySchool;

    private const ZONE = 'Asia/Jakarta';

    protected $fillable = ['school_id', 'date', 'start_time', 'end_time', 'location', 'quota', 'cancelled_at', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'cancelled_at' => 'datetime'];
    }

    /** Tanggal hari ini di zona Asia/Jakarta (Y-m-d): satu sumber untuk model, repository, dan validasi. */
    public static function today(): string
    {
        return Carbon::now(self::ZONE)->toDateString();
    }

    /** Tanggal jadwal sudah terlewat (kemarin atau sebelumnya). */
    public function isPast(): bool
    {
        return $this->date->toDateString() < self::today();
    }

    /** Hari kegiatan sudah tiba (hari ini atau sebelumnya): penandaan boleh dilakukan. */
    public function isOpenForMarking(): bool
    {
        return $this->date->toDateString() <= self::today();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Hari terakhir (inklusif) siswa boleh mendaftar: tanggal sesi dikurangi `sijebol.registration_close_days`. */
    public function lastRegistrationDay(): string
    {
        return $this->date->copy()->subDays((int) config('sijebol.registration_close_days'))->toDateString();
    }

    public function isOpenForRegistration(): bool
    {
        return ! $this->isCancelled() && self::today() <= $this->lastRegistrationDay();
    }

    /** Siswa boleh membatalkan atau mengajukan ulang sampai hari sebelum sesi. */
    public function allowsStudentChanges(): bool
    {
        return ! $this->isCancelled() && self::today() < $this->date->toDateString();
    }

    /**
     * Batas perbaikan jika diminta pada `$now`: yang lebih awal antara `fix_window_hours` jam sejak `$now`
     * dan akhir hari sebelum sesi (23.59.59 H-1).
     */
    public function fixDeadlineFrom(CarbonInterface $now): Carbon
    {
        $window = Carbon::instance($now)->addHours((int) config('sijebol.fix_window_hours'));
        $cap = $this->date->copy()->subDay()->endOfDay();

        return $window->lessThan($cap) ? $window : $cap;
    }

    /**
     * Rentang waktu tiket boleh dipindai: pada tanggal sesi, dari jam mulai dikurangi toleransi sampai jam selesai ditambah toleransi.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function scanWindow(): array
    {
        $date = $this->date->toDateString();
        $tolerance = (int) config('sijebol.scan_tolerance_minutes');

        return [
            Carbon::parse("{$date} {$this->start_time}", self::ZONE)->subMinutes($tolerance),
            Carbon::parse("{$date} {$this->end_time}", self::ZONE)->addMinutes($tolerance),
        ];
    }

    /** Jumlah kursi yang sedang ditahan pendaftaran (lihat RegistrationStatus::holdingSeat). */
    public function seatsTaken(): int
    {
        // Tanpa scope pemilik: siswa yang mendaftar harus ikut melihat kursi yang ditahan siswa lain.
        return $this->entries()->withoutGlobalScope('owner')->whereIn('status', RegistrationStatus::values(RegistrationStatus::holdingSeat()))->count();
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<RegistrationStudent, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(RegistrationStudent::class);
    }
}
