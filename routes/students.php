<?php

use App\Domains\Students\Http\Controllers\StudentController;
use App\Domains\Students\Http\Controllers\StudentDocumentController;
use App\Domains\Students\Http\Controllers\StudentImportController;
use App\Domains\Students\Support\StudentRules;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('siswa', [StudentController::class, 'index'])->middleware('can:siswa.lihat')->name('siswa.index');
    Route::get('siswa/buat', [StudentController::class, 'create'])->middleware('can:siswa.buat')->name('siswa.create');
    Route::post('siswa', [StudentController::class, 'store'])->middleware(['can:siswa.buat', 'throttle:60,1'])->name('siswa.store');

    // Rute impor harus sebelum {student} agar tidak tertangkap parameter.
    Route::get('siswa/impor/template', [StudentImportController::class, 'template'])->middleware('can:siswa.impor')->name('siswa.impor.template');
    Route::post('siswa/impor', [StudentImportController::class, 'store'])->middleware(['can:siswa.impor', 'throttle:60,1'])->name('siswa.impor.store');

    Route::get('siswa/{student}', [StudentController::class, 'show'])->middleware('can:siswa.lihat')->name('siswa.show');
    Route::get('siswa/{student}/ubah', [StudentController::class, 'edit'])->middleware('can:siswa.ubah')->name('siswa.edit');
    Route::put('siswa/{student}', [StudentController::class, 'update'])->middleware('can:siswa.ubah')->name('siswa.update');
    Route::delete('siswa/{student}', [StudentController::class, 'destroy'])->middleware('can:siswa.hapus')->name('siswa.destroy');

    Route::prefix('siswa/{student}/dokumen/{type}')->whereIn('type', StudentRules::DOCUMENT_TYPES)->group(function () {
        Route::post('/', [StudentDocumentController::class, 'store'])->middleware(['can:dokumen.unggah', 'throttle:60,1'])->name('siswa.dokumen.store');
        Route::get('/', [StudentDocumentController::class, 'download'])->middleware('can:dokumen.lihat')->name('siswa.dokumen.download');
        Route::delete('/', [StudentDocumentController::class, 'destroy'])->middleware('can:dokumen.hapus')->name('siswa.dokumen.destroy');
    });
});
