<?php

use App\Domains\Schedules\Http\Controllers\RecordingController;
use App\Domains\Schedules\Http\Controllers\ScheduleController;
use App\Domains\Schedules\Http\Controllers\ScheduleStudentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('jadwal', [ScheduleController::class, 'index'])->middleware('can:jadwal.lihat')->name('jadwal.index');
    Route::get('jadwal/buat', [ScheduleController::class, 'create'])->middleware('can:jadwal.buat')->name('jadwal.create');
    Route::post('jadwal', [ScheduleController::class, 'store'])->middleware(['can:jadwal.buat', 'throttle:60,1'])->name('jadwal.store');
    Route::get('jadwal/{schedule}', [ScheduleController::class, 'show'])->middleware('can:jadwal.lihat')->name('jadwal.show');
    Route::get('jadwal/{schedule}/ubah', [ScheduleController::class, 'edit'])->middleware('can:jadwal.ubah')->name('jadwal.edit');
    Route::put('jadwal/{schedule}', [ScheduleController::class, 'update'])->middleware('can:jadwal.ubah')->name('jadwal.update');

    Route::post('jadwal/{schedule}/siswa', [ScheduleStudentController::class, 'store'])->middleware(['can:jadwal.ubah', 'throttle:60,1'])->name('jadwal.siswa.store');
    // scopeBindings: entri harus milik jadwal pada URL, selain itu 404.
    Route::delete('jadwal/{schedule}/siswa/{entry}', [ScheduleStudentController::class, 'destroy'])
        ->middleware(['can:jadwal.ubah', 'throttle:60,1'])->scopeBindings()->name('jadwal.siswa.destroy');

    Route::get('jadwal/{schedule}/rekaman', [RecordingController::class, 'show'])->middleware('can:rekaman.lihat')->name('jadwal.rekaman.show');
    Route::post('jadwal/{schedule}/rekaman', [RecordingController::class, 'store'])->middleware(['can:rekaman.tandai', 'throttle:60,1'])->name('jadwal.rekaman.store');
});
