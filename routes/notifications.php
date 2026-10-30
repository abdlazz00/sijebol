<?php

use App\Domains\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/mark-all-read', [NotificationController::class, 'readAll'])->name('notifications.mark-all-read');
    Route::post('notifications/{id}/mark-read', [NotificationController::class, 'read'])->name('notifications.mark-read');
});
