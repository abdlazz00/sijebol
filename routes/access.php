<?php

use App\Domains\Access\Http\Controllers\ActivationController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::get('aktivasi/{token}', [ActivationController::class, 'show'])->name('aktivasi.show');
    Route::post('aktivasi/{token}', [ActivationController::class, 'store'])->name('aktivasi.store');
});
