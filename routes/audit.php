<?php

use App\Domains\Audit\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:log.lihat'])
    ->get('audit-logs', [ActivityLogController::class, 'index'])
    ->name('audit-logs.index');
