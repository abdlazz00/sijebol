<?php

use App\Domains\Students\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('siswa', [StudentController::class, 'index'])->middleware('can:siswa.lihat')->name('siswa.index');
    Route::get('siswa/buat', [StudentController::class, 'create'])->middleware('can:siswa.buat')->name('siswa.create');
    Route::post('siswa', [StudentController::class, 'store'])->middleware(['can:siswa.buat', 'throttle:60,1'])->name('siswa.store');

    Route::get('siswa/{student}', [StudentController::class, 'show'])->middleware('can:siswa.lihat')->name('siswa.show');
    Route::get('siswa/{student}/ubah', [StudentController::class, 'edit'])->middleware('can:siswa.ubah')->name('siswa.edit');
    Route::put('siswa/{student}', [StudentController::class, 'update'])->middleware('can:siswa.ubah')->name('siswa.update');
    Route::delete('siswa/{student}', [StudentController::class, 'destroy'])->middleware('can:siswa.hapus')->name('siswa.destroy');
});
