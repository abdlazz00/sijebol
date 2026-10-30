<?php

namespace App\Domains\Reports\Repositories;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use Illuminate\Database\Eloquent\Builder;

class EloquentReportRepository implements ReportRepositoryInterface
{
    public function statusCounts(): array
    {
        return RegistrationStudent::query()
            ->selectRaw('status, count(*) as total')->groupBy('status')
            ->pluck('total', 'status')->map(fn ($n) => (int) $n)->all();
    }

    public function schoolNames(): array
    {
        return School::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function schoolStatusCounts(?string $from, ?string $to): array
    {
        // app.timezone = Asia/Jakarta dan kolom timestamp disimpan dalam zona itu, jadi batas hari cukup string.
        return RegistrationStudent::query()
            ->when($from !== null, fn (Builder $q) => $q->where('registration_students.created_at', '>=', "{$from} 00:00:00"))
            ->when($to !== null, fn (Builder $q) => $q->where('registration_students.created_at', '<=', "{$to} 23:59:59"))
            ->selectRaw('registration_students.school_id, registration_students.status, count(*) as total')
            ->groupBy('registration_students.school_id', 'registration_students.status')
            ->toBase()->get()
            ->groupBy('school_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n)->all())
            ->all();
    }

    public function sessionStatusCounts(?string $from, ?string $to): array
    {
        $sessions = Schedule::query()->with('school')->whereNull('cancelled_at')
            ->when($from !== null, fn (Builder $q) => $q->where('date', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->where('date', '<=', $to))
            ->orderBy('date')->orderBy('id')->get();

        $counts = RegistrationStudent::query()->whereIn('schedule_id', $sessions->modelKeys())
            ->selectRaw('schedule_id, status, count(*) as total')->groupBy('schedule_id', 'status')
            ->toBase()->get()
            ->groupBy('schedule_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n)->all());

        return $sessions->map(fn (Schedule $s) => [
            'id' => $s->id,
            'date' => $s->date->toDateString(),
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'school' => $s->school->name,
            'location' => $s->location,
            'quota' => $s->quota,
            'counts' => $counts[$s->id] ?? [],
        ])->all();
    }
}
