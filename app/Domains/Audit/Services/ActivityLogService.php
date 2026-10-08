<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/**
 * @phpstan-type ActivityRow array{id: int, created_at: string, causer: ?array{id: int, name: string}, action: string, subject: array{type: string, label: string}, changes: list<array{field: string, old: mixed, new: mixed}>}
 */
class ActivityLogService
{
    public function __construct(private readonly ActivityLogRepositoryInterface $activities) {}

    /** @return LengthAwarePaginator<int, ActivityRow> */
    public function list(): LengthAwarePaginator
    {
        return $this->activities->paginate()->through(fn (Activity $a) => $this->row($a));
    }

    /** @return list<array{value: string, label: string}> */
    public function typeOptions(): array
    {
        return collect(trans('activity.types'))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()->all();
    }

    /** @return list<array{id: int, name: string}> */
    public function userOptions(): array
    {
        return $this->activities->causers()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
    }

    /** @return list<ActivityRow> */
    public function recentFor(User $user): array
    {
        return $this->activities->recentFor($user)->map(fn (Activity $a) => $this->row($a))->values()->all();
    }

    /** @return ActivityRow */
    private function row(Activity $a): array
    {
        // activitylog v5 menyimpan perubahan model di kolom attribute_changes, bukan properties.
        $new = ActivityPropertyMasker::apply((array) ($a->attribute_changes['attributes'] ?? []));
        $old = ActivityPropertyMasker::apply((array) ($a->attribute_changes['old'] ?? []));
        $changes = [];
        foreach ($new as $field => $value) {
            $changes[] = ['field' => (string) $field, 'old' => $old[$field] ?? null, 'new' => $value];
        }

        return [
            'id' => $a->id,
            'created_at' => $a->created_at->toIso8601String(),
            'causer' => $a->causer instanceof User ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null,
            'action' => $this->label('actions', (string) $a->description),
            'subject' => [
                'type' => $a->subject_type === null ? '—' : $this->label('subjects', class_basename($a->subject_type)),
                'label' => $this->subjectLabel($a),
            ],
            'changes' => $changes,
        ];
    }

    private function subjectLabel(Activity $a): string
    {
        if ($a->subject_type === null) {
            return '—';
        }

        $subject = $a->subject;

        return (string) ($subject?->getAttribute('name') ?? $subject?->getAttribute('title') ?? '#'.$a->subject_id);
    }

    /** Kunci aksi memuat titik, jadi dibaca dari array (bukan dot-notation). */
    private function label(string $group, string $key): string
    {
        return trans("activity.$group")[$key] ?? $key;
    }
}
