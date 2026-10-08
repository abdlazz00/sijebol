<?php

use App\Domains\Registrations\Http\Controllers\RegistrationController;
use App\Domains\Registrations\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('pendaftaran', [RegistrationController::class, 'index'])->middleware('can:pendaftaran.lihat')->name('pendaftaran.index');
    Route::get('pendaftaran/buat', [RegistrationController::class, 'create'])->middleware('can:pendaftaran.buat')->name('pendaftaran.create');
    Route::post('pendaftaran', [RegistrationController::class, 'store'])->middleware(['can:pendaftaran.buat', 'throttle:60,1'])->name('pendaftaran.store');
    Route::get('pendaftaran/{registration}', [RegistrationController::class, 'show'])->middleware('can:pendaftaran.lihat')->name('pendaftaran.show');
    Route::get('pendaftaran/{registration}/ubah', [RegistrationController::class, 'edit'])->middleware('can:pendaftaran.buat')->name('pendaftaran.edit');
    Route::put('pendaftaran/{registration}', [RegistrationController::class, 'update'])->middleware('can:pendaftaran.buat')->name('pendaftaran.update');
    Route::delete('pendaftaran/{registration}', [RegistrationController::class, 'destroy'])->middleware('can:pendaftaran.buat')->name('pendaftaran.destroy');
    Route::post('pendaftaran/{registration}/ajukan', [RegistrationController::class, 'submit'])->middleware(['can:pendaftaran.ajukan', 'throttle:60,1'])->name('pendaftaran.submit');
    // scopeBindings: entri harus milik pendaftaran pada URL, selain itu 404.
    Route::post('pendaftaran/{registration}/entri/{entry}/ajukan-ulang', [RegistrationController::class, 'resubmit'])
        ->middleware(['can:pendaftaran.ajukan', 'throttle:60,1'])->scopeBindings()->name('pendaftaran.resubmit');

    Route::get('verifikasi', [VerificationController::class, 'index'])->middleware('can:verifikasi.lihat')->name('verifikasi.index');
    Route::get('verifikasi/{entry}', [VerificationController::class, 'show'])->middleware('can:verifikasi.lihat')->name('verifikasi.show');
    Route::post('verifikasi/{entry}/putuskan', [VerificationController::class, 'decide'])->middleware(['can:verifikasi.putuskan', 'throttle:60,1'])->name('verifikasi.decide');
});
