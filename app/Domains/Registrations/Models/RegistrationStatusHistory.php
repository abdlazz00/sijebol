<?php

namespace App\Domains\Registrations\Models;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property RegistrationStatus|null $from_status
 * @property RegistrationStatus $to_status
 * @property int $by_user_id
 * @property string|null $note
 * @property Carbon $created_at
 */
class RegistrationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['registration_student_id', 'from_status', 'to_status', 'by_user_id', 'note'];

    protected function casts(): array
    {
        return ['from_status' => RegistrationStatus::class, 'to_status' => RegistrationStatus::class];
    }

    /** Tanpa scope sekolah: operator sekolah harus tetap melihat nama staf kecamatan yang memutuskan. */
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_user_id')->withoutGlobalScopes();
    }
}
