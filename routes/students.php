<?php

use App\Domains\Students\Http\Controllers\SelfStudentController;
use App\Domains\Students\Http\Controllers\StudentController;
use App\Domains\Students\Http\Controllers\StudentDocumentController;
use App\Domains\Students\Http\Controllers\StudentSignupController;
use App\Domains\Students\Support\StudentRules;
use Illuminate\Support\Facades\Route;

// Publik: pendaftaran akun siswa lewat link sekolah.
Route::get('register/{token}', [StudentSignupController::class, 'show'])->name('register.show');
Route::post('register/{token}', [StudentSignupController::class, 'store'])->middleware('throttle:5,1')->name('register.store');

Route::middleware('auth')->group(function () {
    Route::get('me', [SelfStudentController::class, 'show'])->middleware('can:profil.lihat')->name('me.show');
    Route::put('me', [SelfStudentController::class, 'save'])->middleware(['can:profil.ubah', 'throttle:60,1'])->name('me.update');

    Route::get('students', [StudentController::class, 'index'])->middleware('can:siswa.lihat')->name('students.index');

    Route::get('students/{student}', [StudentController::class, 'show'])->middleware('can:siswa.lihat')->name('students.show');
    Route::get('students/{student}/edit', [StudentController::class, 'edit'])->middleware('can:siswa.ubah')->name('students.edit');
    Route::put('students/{student}', [StudentController::class, 'update'])->middleware('can:siswa.ubah')->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->middleware('can:siswa.hapus')->name('students.destroy');

    Route::prefix('students/{student}/documents/{type}')->whereIn('type', StudentRules::DOCUMENT_TYPES)->group(function () {
        Route::post('/', [StudentDocumentController::class, 'store'])->middleware(['can:dokumen.unggah', 'throttle:60,1'])->name('students.documents.store');
        Route::get('/', [StudentDocumentController::class, 'download'])->middleware('can:dokumen.lihat')->name('students.documents.download');
        Route::get('/preview', [StudentDocumentController::class, 'preview'])->middleware('can:dokumen.lihat')->name('students.documents.preview');
        Route::delete('/', [StudentDocumentController::class, 'destroy'])->middleware('can:dokumen.hapus')->name('students.documents.destroy');
    });
});
