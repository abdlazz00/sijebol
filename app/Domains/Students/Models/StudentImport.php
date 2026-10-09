<?php

namespace App\Domains\Students\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $school_id
 * @property string $status
 * @property string $original_name
 * @property ?string $path
 * @property int $imported
 * @property ?list<array{row: int, reason: string}> $failed
 * @property ?string $error
 */
class StudentImport extends Model
{
    public const ANTRI = 'antri';

    public const BERJALAN = 'berjalan';

    public const SELESAI = 'selesai';

    public const GAGAL = 'gagal';

    protected $fillable = ['user_id', 'school_id', 'status', 'original_name', 'path', 'imported', 'failed', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['failed' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Hasil impor hanya untuk pembuatnya dan staf kecamatan (tidak terikat sekolah). */
    public function visibleTo(User $user): bool
    {
        return $user->school_id === null || $this->user_id === $user->id;
    }

    /** @return array{id: int, status: string, imported: int, failed: list<array{row: int, reason: string}>, error: ?string} */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'imported' => $this->imported,
            'failed' => $this->failed ?? [],
            'error' => $this->error,
        ];
    }
}
