<?php

use App\Domains\Audit\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:log.lihat'])
    ->get('log', [ActivityLogController::class, 'index'])
    ->name('log.index');
