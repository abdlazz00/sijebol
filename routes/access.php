<?php

use App\Domains\Access\Http\Controllers\ActivationController;
use App\Domains\Access\Http\Controllers\RoleController;
use App\Domains\Access\Http\Controllers\UserController;
use App\Domains\Schools\Http\Controllers\SchoolController;
use App\Domains\Schools\Http\Controllers\SchoolLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::get('activation/{token}', [ActivationController::class, 'show'])->name('activation.show');
    Route::post('activation/{token}', [ActivationController::class, 'store'])->name('activation.store');
});

Route::middleware('auth')->group(function () {
    Route::get('schools', [SchoolController::class, 'index'])->middleware('can:sekolah.lihat')->name('schools.index');
    Route::get('schools/create', [SchoolController::class, 'create'])->middleware('can:sekolah.buat')->name('schools.create');
    Route::post('schools', [SchoolController::class, 'store'])->middleware('can:sekolah.buat')->name('schools.store');
    Route::get('schools/{school}/edit', [SchoolController::class, 'edit'])->middleware('can:sekolah.ubah')->name('schools.edit');
    Route::put('schools/{school}', [SchoolController::class, 'update'])->middleware('can:sekolah.ubah')->name('schools.update');
    Route::delete('schools/{school}', [SchoolController::class, 'destroy'])->middleware('can:sekolah.hapus')->name('schools.destroy');

    Route::get('registration-links', [SchoolLinkController::class, 'index'])->middleware('can:sekolah.tautan')->name('registration-links.index');
    Route::post('registration-links/{school}/reset', [SchoolLinkController::class, 'reset'])->middleware(['can:sekolah.reset-tautan', 'throttle:20,1'])->name('registration-links.reset');

    Route::get('users', [UserController::class, 'index'])->middleware('can:user.lihat')->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->middleware('can:user.buat')->name('users.create');
    Route::post('users', [UserController::class, 'store'])->middleware('can:user.buat')->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:user.lihat')->name('users.show');
    Route::get('users/{user}/done', [UserController::class, 'done'])->middleware('can:user.lihat')->name('users.done');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('can:user.ubah')->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:user.ubah')->name('users.update');
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('can:user.nonaktifkan')->name('users.deactivate');
    Route::post('users/{user}/activate', [UserController::class, 'activate'])->middleware('can:user.nonaktifkan')->name('users.activate');
    Route::post('users/{user}/resend-activation', [UserController::class, 'resend'])->middleware('can:user.kirim-ulang-aktivasi')->name('users.resend-activation');

    Route::get('roles', [RoleController::class, 'index'])->middleware('can:role.lihat')->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->middleware('can:role.buat')->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->middleware('can:role.buat')->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('can:role.ubah')->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('can:role.ubah')->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('can:role.hapus')->name('roles.destroy');
});
