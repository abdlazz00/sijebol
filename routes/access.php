<?php

use App\Domains\Access\Http\Controllers\ActivationController;
use App\Domains\Access\Http\Controllers\RoleController;
use App\Domains\Schools\Http\Controllers\SchoolController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::get('aktivasi/{token}', [ActivationController::class, 'show'])->name('aktivasi.show');
    Route::post('aktivasi/{token}', [ActivationController::class, 'store'])->name('aktivasi.store');
});

Route::middleware('auth')->group(function () {
    Route::get('sekolah', [SchoolController::class, 'index'])->middleware('can:sekolah.lihat')->name('sekolah.index');
    Route::get('sekolah/buat', [SchoolController::class, 'create'])->middleware('can:sekolah.buat')->name('sekolah.create');
    Route::post('sekolah', [SchoolController::class, 'store'])->middleware('can:sekolah.buat')->name('sekolah.store');
    Route::get('sekolah/{school}/ubah', [SchoolController::class, 'edit'])->middleware('can:sekolah.ubah')->name('sekolah.edit');
    Route::put('sekolah/{school}', [SchoolController::class, 'update'])->middleware('can:sekolah.ubah')->name('sekolah.update');

    Route::get('role', [RoleController::class, 'index'])->middleware('can:role.lihat')->name('role.index');
    Route::get('role/buat', [RoleController::class, 'create'])->middleware('can:role.buat')->name('role.create');
    Route::post('role', [RoleController::class, 'store'])->middleware('can:role.buat')->name('role.store');
    Route::get('role/{role}/ubah', [RoleController::class, 'edit'])->middleware('can:role.ubah')->name('role.edit');
    Route::put('role/{role}', [RoleController::class, 'update'])->middleware('can:role.ubah')->name('role.update');
    Route::delete('role/{role}', [RoleController::class, 'destroy'])->middleware('can:role.hapus')->name('role.destroy');
});
