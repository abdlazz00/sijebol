<?php

use App\Domains\Schedules\Http\Controllers\RecordingController;
use App\Domains\Schedules\Http\Controllers\ScanController;
use App\Domains\Schedules\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('schedules', [ScheduleController::class, 'index'])->middleware('can:jadwal.lihat')->name('schedules.index');
    Route::get('schedules/create', [ScheduleController::class, 'create'])->middleware('can:jadwal.buat')->name('schedules.create');
    Route::post('schedules', [ScheduleController::class, 'store'])->middleware(['can:jadwal.buat', 'throttle:60,1'])->name('schedules.store');
    Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])->middleware('can:jadwal.lihat')->name('schedules.show');
    Route::get('schedules/{schedule}/edit', [ScheduleController::class, 'edit'])->middleware('can:jadwal.ubah')->name('schedules.edit');
    Route::put('schedules/{schedule}', [ScheduleController::class, 'update'])->middleware('can:jadwal.ubah')->name('schedules.update');

    Route::post('schedules/{schedule}/cancel', [ScheduleController::class, 'cancel'])->middleware(['can:jadwal.ubah', 'throttle:30,1'])->name('schedules.cancel');
    Route::post('schedules/{schedule}/entries/{entry}/cancel', [ScheduleController::class, 'cancelEntry'])
        ->middleware(['can:verifikasi.putuskan', 'throttle:30,1'])->scopeBindings()->name('schedules.entries.cancel');
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->middleware(['can:jadwal.ubah', 'throttle:60,1'])->name('schedules.destroy');

    Route::get('schedules/{schedule}/recording', [RecordingController::class, 'show'])->middleware('can:rekaman.lihat')->name('schedules.recording.show');
    Route::post('schedules/{schedule}/recording', [RecordingController::class, 'store'])->middleware(['can:rekaman.tandai', 'throttle:60,1'])->name('schedules.recording.store');
    Route::post('schedules/{schedule}/recording/{entry}/cancel', [RecordingController::class, 'undo'])
        ->middleware(['can:rekaman.tandai', 'throttle:60,1'])->scopeBindings()->name('schedules.recording.cancel');

    Route::get('schedules/{schedule}/scan', [ScanController::class, 'show'])->middleware('can:rekaman.pindai')->name('schedules.scan.show');
    Route::post('schedules/{schedule}/scan', [ScanController::class, 'store'])->middleware(['can:rekaman.pindai', 'throttle:120,1'])->name('schedules.scan.store');
});
