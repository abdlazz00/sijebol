<?php

namespace App\Domains\Registrations\Models;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Students\Models\Student;
use App\Models\Concerns\ScopedBySchool;
use Carbon\Carbon;
use Database\Factories\RegistrationStudentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $registration_id
 * @property int $school_id
 * @property int $student_id
 * @property RegistrationStatus $status
 * @property string|null $note
 * @property int|null $decided_by
 * @property int|null $schedule_id
 * @property Carbon|null $decided_at
 * @property Carbon $updated_at
 */
#[UseFactory(RegistrationStudentFactory::class)]
class RegistrationStudent extends Model
{
    /** @use HasFactory<RegistrationStudentFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = ['registration_id', 'school_id', 'student_id', 'status', 'note', 'decided_by', 'decided_at', 'schedule_id'];

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class, 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Registration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** @return HasMany<RegistrationStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(RegistrationStatusHistory::class)->orderBy('id');
    }
}
