<?php

use App\Domains\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('laporan', [ReportController::class, 'index'])->middleware('can:laporan.lihat')->name('laporan.index');
    Route::get('laporan/ekspor', [ReportController::class, 'export'])->middleware(['can:laporan.lihat', 'throttle:20,1'])->name('laporan.ekspor');
});
