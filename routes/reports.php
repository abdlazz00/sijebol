<?php

use App\Domains\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->middleware('can:laporan.lihat')->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->middleware(['can:laporan.lihat', 'throttle:20,1'])->name('reports.export');
    Route::get('reports/export-sessions', [ReportController::class, 'exportSessions'])->middleware(['can:laporan.lihat', 'throttle:20,1'])->name('reports.export-sessions');
    Route::get('reports/students', [ReportController::class, 'students'])->middleware('can:laporan.rekaman')->name('reports.students');
    Route::get('reports/students/export', [ReportController::class, 'exportStudents'])->middleware(['can:laporan.rekaman', 'throttle:20,1'])->name('reports.students.export');
});
