<?php

use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\LupaPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\GmailOAuthController;
use App\Http\Controllers\GmailSettingsController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

// Route Login
Route::get('/', [LoginController::class, 'index'])->name('login.index');
// Alias so the framework's default `auth` middleware (which redirects guests
// to the route named "login") has somewhere valid to send them.
Route::get('/', [LoginController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');

// Route Lupa Password
Route::get('/lupa-password', [LupaPasswordController::class, 'showHalamanLupaPassword'])->name('lupa-password.index');
Route::post('/lupa-password', [LupaPasswordController::class, 'kirimLinkReset'])->name('lupa-password.kirim');

// Route Reset Password
Route::get('/reset-password/{token}/{email}', [LupaPasswordController::class, 'showHalamanResetPassword'])->name('reset-password.index');
Route::post('/reset-password', [LupaPasswordController::class, 'resetPassword'])->name('reset-password.reset');

// Route Logout
Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

// Everything below belongs to a logged-in user: Inbox/Dashboard now scope
// their data to the Gmail account(s) that user connected, so a request
// without an authenticated user has no sensible data to show.
Route::middleware('auth')->group(function () {
    // Route Profil
    Route::prefix('profil')->group(function () {
        Route::get('/', [ProfilController::class, 'index'])->name('profil.index');
        Route::post('/update', [ProfilController::class, 'update'])->name('profil.update');
        Route::post('/password', [ProfilController::class, 'updatePassword'])->name('profil.update.password');
    });

    // Route Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Rute Inbox
    Route::prefix('inbox')->name('inbox.')->group(function () {
        // Daftar inbox (dengan filter status)
        Route::get('/', [InboxController::class, 'index'])->name('index');

        // Endpoint AJAX polling untuk auto-refresh daftar inbox
        Route::get('/poll', [InboxController::class, 'poll'])->name('poll');

        // Sub-menu channel WhatsApp (belum terintegrasi, halaman "coming soon").
        // Harus didaftarkan sebelum /{conversation} agar tidak tertangkap wildcard.
        Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp');

        // Detail thread percakapan (permalink lama, redirect ke panel kanan Inbox)
        Route::get('/{conversation}', [InboxController::class, 'show'])->name('show');

        // Ubah status percakapan (pending_review / replied / closed)
        Route::put('/{conversation}/status', [InboxController::class, 'updateStatus'])->name('status.update');

        // Toggle bintang (starred) pada percakapan
        Route::put('/{conversation}/star', [InboxController::class, 'toggleStar'])->name('star');

        // Unduh attachment (fetch on-demand dari Gmail API)
        Route::get('/messages/{message}/attachments/{attachmentId}', [InboxController::class, 'downloadAttachment'])
            ->name('messages.attachments.download');

        // Review, approve, reject draft AI
        Route::put('/drafts/{draft}', [DraftController::class, 'update'])->name('drafts.update');
        Route::post('/drafts/{draft}/approve', [DraftController::class, 'approve'])->name('drafts.approve');
        Route::post('/drafts/{draft}/reject', [DraftController::class, 'reject'])->name('drafts.reject');
    });

    // Route Settings & koneksi Gmail (token OAuth per user)
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');

        Route::prefix('gmail')->name('gmail.')->group(function () {
            Route::get('/connect', [GmailOAuthController::class, 'redirect'])->name('connect');
            Route::get('/callback', [GmailOAuthController::class, 'callback'])->name('callback');
            Route::delete('/{gmailAccount}', [GmailOAuthController::class, 'disconnect'])->name('disconnect');
        });

        // Global Gmail OAuth configuration (client id/secret/redirect uri),
        // admin-only: this replaces editing .env for Gmail credentials.
        Route::middleware('admin')->prefix('gmail-config')->name('gmail-config.')->group(function () {
            Route::get('/', [GmailSettingsController::class, 'index'])->name('index');
            Route::put('/', [GmailSettingsController::class, 'update'])->name('update');
            Route::post('/test-connection', [GmailSettingsController::class, 'testConnection'])->name('test-connection');
        });

        // Global AI provider configuration (provider/api key/base url/model/
        // temperature/max tokens/timeout), admin-only: this replaces editing
        // .env and config/openai.php for AI credentials.
        Route::middleware('admin')->prefix('ai-config')->name('ai-config.')->group(function () {
            Route::get('/', [AiSettingsController::class, 'index'])->name('index');
            Route::put('/', [AiSettingsController::class, 'update'])->name('update');
            Route::post('/test-connection', [AiSettingsController::class, 'testConnection'])->name('test-connection');
        });
    });
});
