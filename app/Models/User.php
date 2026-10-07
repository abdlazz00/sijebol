<?php

namespace App\Models;

use App\Domains\Access\Enums\UserStatus;
use App\Domains\Access\Models\AccountActivation;
use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'school_id', 'is_active', 'activated_at', 'created_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, ScopedBySchool;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<AccountActivation, $this> */
    public function activations(): HasMany
    {
        return $this->hasMany(AccountActivation::class);
    }

    /** @param Builder<User> $query */
    public function scopeWhereStatus(Builder $query, UserStatus $status): void
    {
        $pending = fn ($q) => $q->pending();

        match ($status) {
            UserStatus::Nonaktif => $query->where('is_active', false),
            UserStatus::Aktif => $query->where('is_active', true)->whereNotNull('activated_at'),
            UserStatus::Menunggu => $query->where('is_active', true)->whereNull('activated_at')->whereHas('activations', $pending),
            UserStatus::Kedaluwarsa => $query->where('is_active', true)->whereNull('activated_at')->whereDoesntHave('activations', $pending),
        };
    }

    protected function status(): Attribute
    {
        return Attribute::get(function (): UserStatus {
            if (! $this->is_active) {
                return UserStatus::Nonaktif;
            }
            if ($this->activated_at !== null) {
                return UserStatus::Aktif;
            }
            $pending = $this->attributes['has_pending_activation'] ?? $this->activations()->pending()->exists();

            return $pending ? UserStatus::Menunggu : UserStatus::Kedaluwarsa;
        });
    }
}
