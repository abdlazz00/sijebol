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
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[UseFactory(SchoolFactory::class)]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'npsn', 'level', 'address'];

    protected static function booted(): void
    {
        // Token tidak ada di $fillable: hanya sistem yang mengisi dan mengganti.
        static::creating(fn (School $school) => $school->registration_token ??= Str::random(40));
    }

    public function registrationUrl(): string
    {
        return route('daftar.show', $this->registration_token);
    }

    /** Token lama berhenti berlaku; akun siswa yang sudah ada tidak terpengaruh. */
    public function resetRegistrationToken(): void
    {
        $this->forceFill(['registration_token' => Str::random(40)])->save();
    }

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
