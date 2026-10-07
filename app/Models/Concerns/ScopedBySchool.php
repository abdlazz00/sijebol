<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Membatasi query ke sekolah pengguna yang sedang login (jika terikat sekolah). */
trait ScopedBySchool
{
    protected static function bootScopedBySchool(): void
    {
        static::addGlobalScope('school', function (Builder $query) {
            // hasUser() mencegah rekursi: me-resolve user login memakai model ini juga.
            $schoolId = Auth::hasUser() ? Auth::user()->school_id : null;

            if ($schoolId !== null) {
                $query->where($query->getModel()->qualifyColumn('school_id'), $schoolId);
            }
        });
    }
}
