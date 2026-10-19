<?php

namespace App\Domains\Students\Models;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $school_id
 * @property string $nik
 * @property string $nisn
 * @property string $name
 * @property Carbon $birth_date
 * @property string $gender
 */
#[UseFactory(StudentFactory::class)]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, LogsActivity, ScopedBySchool;

    public const LOCKED_MESSAGE = 'Data siswa terkunci karena pendaftarannya sedang diproses.';

    protected $fillable = ['school_id', 'nik', 'nisn', 'name', 'birth_date', 'gender', 'user_id'];

    public static function booted(): void
    {
        // Akun siswa hanya melihat barisnya sendiri. hasUser() mencegah rekursi saat user login sedang di-resolve.
        static::addGlobalScope('owner', function (Builder $query) {
            if (Auth::hasUser() && Auth::user()->isStudent()) {
                $query->where($query->getModel()->qualifyColumn('user_id'), Auth::id());
            }
        });
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StudentDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    /** @return HasMany<RegistrationStudent, $this> */
    public function registrationEntries(): HasMany
    {
        return $this->hasMany(RegistrationStudent::class);
    }

    /** Terkunci selama ada entri yang sedang diproses petugas (lihat RegistrationStatus::locking). */
    public function isLocked(): bool
    {
        return $this->registrationEntries()
            ->whereIn('status', RegistrationStatus::values(RegistrationStatus::locking()))
            ->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('siswa')
            ->logOnly(['nik', 'nisn', 'name', 'birth_date', 'gender', 'school_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
