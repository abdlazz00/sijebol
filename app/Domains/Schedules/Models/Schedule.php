<?php

namespace App\Domains\Schedules\Models;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use App\Models\User;
use Carbon\Carbon;
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
 * @property string $location
 * @property int $created_by
 */
#[UseFactory(ScheduleFactory::class)]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory, ScopedBySchool;

    private const ZONE = 'Asia/Jakarta';

    protected $fillable = ['school_id', 'date', 'location', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
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
