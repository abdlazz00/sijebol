<?php

use App\Domains\Students\Http\Controllers\SelfStudentController;
use App\Domains\Students\Http\Controllers\StudentController;
use App\Domains\Students\Http\Controllers\StudentDocumentController;
use App\Domains\Students\Http\Controllers\StudentSignupController;
use App\Domains\Students\Support\StudentRules;
use Illuminate\Support\Facades\Route;

// Publik: pendaftaran akun siswa lewat link sekolah.
Route::get('daftar/{token}', [StudentSignupController::class, 'show'])->name('daftar.show');
Route::post('daftar/{token}', [StudentSignupController::class, 'store'])->middleware('throttle:5,1')->name('daftar.store');

Route::middleware('auth')->group(function () {
    Route::get('saya', [SelfStudentController::class, 'show'])->middleware('can:profil.lihat')->name('saya.show');
    Route::put('saya', [SelfStudentController::class, 'save'])->middleware(['can:profil.ubah', 'throttle:60,1'])->name('saya.save');

    Route::get('siswa', [StudentController::class, 'index'])->middleware('can:siswa.lihat')->name('siswa.index');

    Route::get('siswa/{student}', [StudentController::class, 'show'])->middleware('can:siswa.lihat')->name('siswa.show');
    Route::get('siswa/{student}/ubah', [StudentController::class, 'edit'])->middleware('can:siswa.ubah')->name('siswa.edit');
    Route::put('siswa/{student}', [StudentController::class, 'update'])->middleware('can:siswa.ubah')->name('siswa.update');
    Route::delete('siswa/{student}', [StudentController::class, 'destroy'])->middleware('can:siswa.hapus')->name('siswa.destroy');

    Route::prefix('siswa/{student}/dokumen/{type}')->whereIn('type', StudentRules::DOCUMENT_TYPES)->group(function () {
        Route::post('/', [StudentDocumentController::class, 'store'])->middleware(['can:dokumen.unggah', 'throttle:60,1'])->name('siswa.dokumen.store');
        Route::get('/', [StudentDocumentController::class, 'download'])->middleware('can:dokumen.lihat')->name('siswa.dokumen.download');
        Route::get('/preview', [StudentDocumentController::class, 'preview'])->middleware('can:dokumen.lihat')->name('siswa.dokumen.preview');
        Route::delete('/', [StudentDocumentController::class, 'destroy'])->middleware('can:dokumen.hapus')->name('siswa.dokumen.destroy');
    });
});
