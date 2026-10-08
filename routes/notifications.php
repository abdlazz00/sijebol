<?php

use App\Domains\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('notifikasi', [NotificationController::class, 'index'])->name('notifikasi.index');
    Route::post('notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifikasi.read-all');
    Route::post('notifikasi/{id}/baca', [NotificationController::class, 'read'])->name('notifikasi.read');
});
