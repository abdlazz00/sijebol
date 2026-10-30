<?php

use App\Domains\Registrations\Http\Controllers\SelfRegistrationController;
use App\Domains\Registrations\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('me/sessions', [SelfRegistrationController::class, 'index'])->middleware('can:pendaftaran.lihat')->name('me.sessions.index');
    Route::get('me/tickets/{entry}', [SelfRegistrationController::class, 'ticket'])->middleware('can:pendaftaran.lihat')->name('me.tickets.show');
    Route::post('me/sessions/{schedule}/register', [SelfRegistrationController::class, 'register'])->middleware(['can:pendaftaran.buat', 'throttle:10,1'])->name('me.sessions.register');
    Route::post('me/registrations/{entry}/cancel', [SelfRegistrationController::class, 'cancel'])->middleware(['can:pendaftaran.buat', 'throttle:30,1'])->name('me.registrations.cancel');
    Route::post('me/registrations/{entry}/resubmit', [SelfRegistrationController::class, 'resubmit'])->middleware(['can:pendaftaran.buat', 'throttle:30,1'])->name('me.registrations.resubmit');

    Route::get('verifications', [VerificationController::class, 'index'])->middleware('can:verifikasi.lihat')->name('verifications.index');
    Route::post('verifications/approve-batch', [VerificationController::class, 'approveMany'])->middleware(['can:verifikasi.putuskan', 'throttle:30,1'])->name('verifications.approve-batch');
    Route::get('verifications/{entry}', [VerificationController::class, 'show'])->middleware('can:verifikasi.lihat')->name('verifications.show');
    Route::post('verifications/{entry}/decide', [VerificationController::class, 'decide'])->middleware(['can:verifikasi.putuskan', 'throttle:60,1'])->name('verifications.decide');
});
