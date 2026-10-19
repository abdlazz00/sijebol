<?php

use App\Domains\Schedules\Http\Controllers\RecordingController;
use App\Domains\Schedules\Http\Controllers\ScanController;
use App\Domains\Schedules\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('jadwal', [ScheduleController::class, 'index'])->middleware('can:jadwal.lihat')->name('jadwal.index');
    Route::get('jadwal/buat', [ScheduleController::class, 'create'])->middleware('can:jadwal.buat')->name('jadwal.create');
    Route::post('jadwal', [ScheduleController::class, 'store'])->middleware(['can:jadwal.buat', 'throttle:60,1'])->name('jadwal.store');
    Route::get('jadwal/{schedule}', [ScheduleController::class, 'show'])->middleware('can:jadwal.lihat')->name('jadwal.show');
    Route::get('jadwal/{schedule}/ubah', [ScheduleController::class, 'edit'])->middleware('can:jadwal.ubah')->name('jadwal.edit');
    Route::put('jadwal/{schedule}', [ScheduleController::class, 'update'])->middleware('can:jadwal.ubah')->name('jadwal.update');

    Route::post('jadwal/{schedule}/batalkan', [ScheduleController::class, 'cancel'])->middleware(['can:jadwal.ubah', 'throttle:30,1'])->name('jadwal.batalkan');
    Route::post('jadwal/{schedule}/peserta/{entry}/batalkan', [ScheduleController::class, 'cancelEntry'])
        ->middleware(['can:verifikasi.putuskan', 'throttle:30,1'])->scopeBindings()->name('jadwal.peserta.batalkan');
    Route::delete('jadwal/{schedule}', [ScheduleController::class, 'destroy'])->middleware(['can:jadwal.ubah', 'throttle:60,1'])->name('jadwal.destroy');

    Route::get('jadwal/{schedule}/rekaman', [RecordingController::class, 'show'])->middleware('can:rekaman.lihat')->name('jadwal.rekaman.show');
    Route::post('jadwal/{schedule}/rekaman', [RecordingController::class, 'store'])->middleware(['can:rekaman.tandai', 'throttle:60,1'])->name('jadwal.rekaman.store');
    Route::post('jadwal/{schedule}/rekaman/{entry}/batalkan', [RecordingController::class, 'undo'])
        ->middleware(['can:rekaman.tandai', 'throttle:60,1'])->scopeBindings()->name('jadwal.rekaman.batalkan');

    Route::get('jadwal/{schedule}/pindai', [ScanController::class, 'show'])->middleware('can:rekaman.pindai')->name('jadwal.pindai.show');
    Route::post('jadwal/{schedule}/pindai', [ScanController::class, 'store'])->middleware(['can:rekaman.pindai', 'throttle:120,1'])->name('jadwal.pindai.store');
});
