<?php

use App\Domains\Registrations\Http\Controllers\SelfRegistrationController;
use App\Domains\Registrations\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('saya/sesi', [SelfRegistrationController::class, 'index'])->middleware('can:pendaftaran.lihat')->name('saya.sesi');
    Route::post('saya/sesi/{schedule}/daftar', [SelfRegistrationController::class, 'register'])->middleware(['can:pendaftaran.buat', 'throttle:10,1'])->name('saya.sesi.daftar');
    Route::post('saya/pendaftaran/{entry}/batal', [SelfRegistrationController::class, 'cancel'])->middleware(['can:pendaftaran.buat', 'throttle:30,1'])->name('saya.pendaftaran.batal');
    Route::post('saya/pendaftaran/{entry}/ajukan-ulang', [SelfRegistrationController::class, 'resubmit'])->middleware(['can:pendaftaran.buat', 'throttle:30,1'])->name('saya.pendaftaran.ajukan-ulang');

    Route::get('verifikasi', [VerificationController::class, 'index'])->middleware('can:verifikasi.lihat')->name('verifikasi.index');
    Route::post('verifikasi/setujui', [VerificationController::class, 'approveMany'])->middleware(['can:verifikasi.putuskan', 'throttle:30,1'])->name('verifikasi.setujui');
    Route::get('verifikasi/{entry}', [VerificationController::class, 'show'])->middleware('can:verifikasi.lihat')->name('verifikasi.show');
    Route::post('verifikasi/{entry}/putuskan', [VerificationController::class, 'decide'])->middleware(['can:verifikasi.putuskan', 'throttle:60,1'])->name('verifikasi.decide');
});
