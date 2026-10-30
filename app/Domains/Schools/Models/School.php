<?php

namespace App\Domains\Schools\Models;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[UseFactory(SchoolFactory::class)]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'npsn', 'level', 'address'];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Student, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** @return HasMany<RegistrationStudent, $this> */
    public function registrationEntries(): HasMany
    {
        return $this->hasMany(RegistrationStudent::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('akses')
            ->logOnly(['name', 'npsn', 'level', 'address'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
