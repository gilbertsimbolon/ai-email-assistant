<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\LupaPasswordController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

// Route Login
Route::get('/', [LoginController::class, 'index'])->name('login.index');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');

// Route Lupa Password
Route::get('/lupa-password', [LupaPasswordController::class, 'showHalamanLupaPassword'])->name('lupa-password.index');
Route::post('/lupa-password', [LupaPasswordController::class, 'kirimLinkReset'])->name('lupa-password.kirim');

// Route Reset Password
Route::get('/reset-password/{token}/{email}', [LupaPasswordController::class, 'showHalamanResetPassword'])->name('reset-password.index');
Route::post('/reset-password', [LupaPasswordController::class, 'resetPassword'])->name('reset-password.reset');

// Route Logout
Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

// Route Profil
Route::prefix('profil')->group(function () {
    // Halaman Profil
    Route::get('/', [ProfilController::class, 'index'])->name('profil.index');
    // Edit Data Profil
    Route::post('/update', [ProfilController::class, 'update'])->name('profil.update');
    Route::post('/password', [ProfilController::class, 'updatePassword'])->name('profil.update.password');
});

// Route Dashboard
Route::get('/dashboard', function () {
    return view('layouts.app');
})->name('dashboard');

// Route Inbox
Route::middleware('auth')->prefix('inbox')->name('inbox.')->group(function () {
    Route::get('/', [InboxController::class, 'index'])->name('index');
    Route::get('/{conversation}', [InboxController::class, 'show'])->name('show');
    Route::put('/drafts/{draft}', [InboxController::class, 'updateDraft'])->name('drafts.update');
    Route::post('/drafts/{draft}/approve', [InboxController::class, 'approveDraft'])->name('drafts.approve');
    Route::put('/{conversation}/status', [InboxController::class, 'updateStatus'])->name('status.update');
});
