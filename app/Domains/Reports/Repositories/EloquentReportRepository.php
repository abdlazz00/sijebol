<?php

namespace App\Domains\Reports\Repositories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
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
            ->join('registrations', 'registrations.id', '=', 'registration_students.registration_id')
            ->where('registration_students.status', '!=', RegistrationStatus::Draf->value)
            ->when($from !== null, fn (Builder $q) => $q->where('registrations.submitted_at', '>=', "{$from} 00:00:00"))
            ->when($to !== null, fn (Builder $q) => $q->where('registrations.submitted_at', '<=', "{$to} 23:59:59"))
            ->selectRaw('registration_students.school_id, registration_students.status, count(*) as total')
            ->groupBy('registration_students.school_id', 'registration_students.status')
            ->toBase()->get()
            ->groupBy('school_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n)->all())
            ->all();
    }
}
