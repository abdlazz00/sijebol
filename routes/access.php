<?php

use App\Domains\Access\Http\Controllers\ActivationController;
use App\Domains\Access\Http\Controllers\RoleController;
use App\Domains\Access\Http\Controllers\UserController;
use App\Domains\Schools\Http\Controllers\SchoolController;
use App\Domains\Schools\Http\Controllers\SchoolLinkController;
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

    Route::get('tautan-pendaftaran', [SchoolLinkController::class, 'index'])->middleware('can:sekolah.tautan')->name('tautan.index');
    Route::post('tautan-pendaftaran/{school}/reset', [SchoolLinkController::class, 'reset'])->middleware(['can:sekolah.reset-tautan', 'throttle:20,1'])->name('tautan.reset');

    Route::get('pengguna', [UserController::class, 'index'])->middleware('can:user.lihat')->name('pengguna.index');
    Route::get('pengguna/buat', [UserController::class, 'create'])->middleware('can:user.buat')->name('pengguna.create');
    Route::post('pengguna', [UserController::class, 'store'])->middleware('can:user.buat')->name('pengguna.store');
    Route::get('pengguna/{user}', [UserController::class, 'show'])->middleware('can:user.lihat')->name('pengguna.show');
    Route::get('pengguna/{user}/berhasil', [UserController::class, 'done'])->middleware('can:user.lihat')->name('pengguna.done');
    Route::get('pengguna/{user}/ubah', [UserController::class, 'edit'])->middleware('can:user.ubah')->name('pengguna.edit');
    Route::put('pengguna/{user}', [UserController::class, 'update'])->middleware('can:user.ubah')->name('pengguna.update');
    Route::post('pengguna/{user}/nonaktifkan', [UserController::class, 'deactivate'])->middleware('can:user.nonaktifkan')->name('pengguna.deactivate');
    Route::post('pengguna/{user}/aktifkan', [UserController::class, 'activate'])->middleware('can:user.nonaktifkan')->name('pengguna.activate');
    Route::post('pengguna/{user}/kirim-ulang-aktivasi', [UserController::class, 'resend'])->middleware('can:user.kirim-ulang-aktivasi')->name('pengguna.resend');

    Route::get('role', [RoleController::class, 'index'])->middleware('can:role.lihat')->name('role.index');
    Route::get('role/buat', [RoleController::class, 'create'])->middleware('can:role.buat')->name('role.create');
    Route::post('role', [RoleController::class, 'store'])->middleware('can:role.buat')->name('role.store');
    Route::get('role/{role}/ubah', [RoleController::class, 'edit'])->middleware('can:role.ubah')->name('role.edit');
    Route::put('role/{role}', [RoleController::class, 'update'])->middleware('can:role.ubah')->name('role.update');
    Route::delete('role/{role}', [RoleController::class, 'destroy'])->middleware('can:role.hapus')->name('role.destroy');
});
