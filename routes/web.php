<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
require __DIR__.'/access.php';
require __DIR__.'/notifications.php';
require __DIR__.'/audit.php';
require __DIR__.'/students.php';
require __DIR__.'/registrations.php';
require __DIR__.'/schedules.php';

// Agar 404 lewat grup web (sesi dan props bersama termuat) dan tampil sebagai halaman Error.
Route::any('{path}', fn () => abort(404))->where('path', '.*')->fallback();
