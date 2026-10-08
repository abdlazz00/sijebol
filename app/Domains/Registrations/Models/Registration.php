<?php

namespace App\Domains\Registrations\Models;

use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property string $title
 * @property int|null $expected_count
 * @property int $created_by
 * @property Carbon|null $submitted_at
 * @property Carbon $created_at
 */
#[UseFactory(RegistrationFactory::class)]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = ['school_id', 'title', 'expected_count', 'created_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function isDraft(): bool
    {
        return $this->submitted_at === null;
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
