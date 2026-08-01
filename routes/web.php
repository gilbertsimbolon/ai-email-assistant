<?php

use App\Http\Controllers\AiCenter\AiCenterAiLogController;
use App\Http\Controllers\AiCenter\AiCenterAiModelController;
use App\Http\Controllers\AiCenter\AiCenterAiParameterController;
use App\Http\Controllers\AiCenter\AiCenterCategoryController;
use App\Http\Controllers\AiCenter\AiCenterDashboardController;
use App\Http\Controllers\AiCenter\AiCenterForbiddenActionController;
use App\Http\Controllers\AiCenter\AiCenterIntentController;
use App\Http\Controllers\AiCenter\AiCenterKnowledgeBaseController;
use App\Http\Controllers\AiCenter\AiCenterPlaygroundController;
use App\Http\Controllers\AiCenter\AiCenterPromptPreviewController;
use App\Http\Controllers\AiCenter\AiCenterReplyTemplateController;
use App\Http\Controllers\AiCenter\AiCenterSettingsController;
use App\Http\Controllers\AiCenter\AiCenterSopController;
use App\Http\Controllers\AiCenter\AiCenterSopRuleController;
use App\Http\Controllers\AiCenter\AiCenterWorkflowController;
use App\Http\Controllers\AiCenter\AiCenterWorkflowNodeController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\LupaPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\GmailOAuthController;
use App\Http\Controllers\GmailSettingsController;
use App\Http\Controllers\Inbox\InboxAiToolsController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\Reports\ReportsAiUsageController;
use App\Http\Controllers\Reports\ReportsContentController;
use App\Http\Controllers\Reports\ReportsCustomerController;
use App\Http\Controllers\Reports\ReportsExportController;
use App\Http\Controllers\Reports\ReportsGmailController;
use App\Http\Controllers\Reports\ReportsOverviewController;
use App\Http\Controllers\Reports\ReportsTimelineController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Route Login
Route::get('/', [LoginController::class, 'index'])->name('login.index');
// Alias so the framework's default `auth` middleware (which redirects guests
// to the route named "login") has somewhere valid to send them.
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

    // Rute Inbox — the "inbox" permission (Admin via Gate::before, Agent
    // explicitly, per claude.txt) gates entry to the whole workspace; a few
    // routes below layer a more specific permission on top.
    Route::middleware('permission:inbox')->prefix('inbox')->name('inbox.')->group(function () {
        // Daftar inbox (dengan filter status)
        Route::get('/', [InboxController::class, 'index'])->name('index');

        // Detail thread percakapan (permalink lama, redirect ke panel kanan Inbox)
        Route::get('/{conversation}', [InboxController::class, 'show'])->name('show');

        // Ubah status percakapan (pending_review / replied / closed)
        Route::put('/{conversation}/status', [InboxController::class, 'updateStatus'])->name('status.update');

        // Toggle bintang (starred) pada percakapan
        Route::put('/{conversation}/star', [InboxController::class, 'toggleStar'])->name('star');

        // Toggle status baca (tombol "Mark Read" di toolbar)
        Route::put('/{conversation}/read', [InboxController::class, 'toggleRead'])->name('read.toggle');

        // Unduh attachment (fetch on-demand dari Gmail API)
        Route::get('/messages/{message}/attachments/{attachmentId}', [InboxController::class, 'downloadAttachment'])
            ->name('messages.attachments.download');

        // Preview attachment inline (thumbnail gambar di bubble chat)
        Route::get('/messages/{message}/attachments/{attachmentId}/preview', [InboxController::class, 'previewAttachment'])
            ->name('messages.attachments.preview');

        // Review, approve, reject draft AI
        Route::put('/drafts/{draft}', [DraftController::class, 'update'])->name('drafts.update')
            ->middleware('permission:edit draft');
        Route::post('/drafts/{draft}/approve', [DraftController::class, 'approve'])->name('drafts.approve')
            ->middleware('permission:approve draft');
        Route::post('/drafts/{draft}/reject', [DraftController::class, 'reject'])->name('drafts.reject')
            ->middleware('permission:approve draft');

        // Tombol "Generate AI Reply" / "Regenerate" — satu-satunya tempat AI benar-benar dipanggil
        Route::post('/{conversation}/drafts/generate', [DraftController::class, 'generate'])->name('drafts.generate')
            ->middleware('permission:generate ai|regenerate ai');

        // Tombol "Send" pada composer (dengan atau tanpa draft AI)
        Route::post('/{conversation}/drafts/send', [DraftController::class, 'send'])->name('drafts.send')
            ->middleware('permission:send email');

        // Toolbar AI di atas composer: Summarize/Translate/Detect Intent/
        // Extract Info/Sentiment — semuanya manual (klik tombol), tidak
        // pernah otomatis, per claude.txt.
        Route::prefix('{conversation}/ai-tools')->name('ai-tools.')->group(function () {
            Route::post('/summarize', [InboxAiToolsController::class, 'summarize'])->name('summarize')
                ->middleware('permission:summarize thread');
            Route::post('/translate', [InboxAiToolsController::class, 'translate'])->name('translate')
                ->middleware('permission:translate email');
            Route::post('/detect-intent', [InboxAiToolsController::class, 'detectIntent'])->name('detect-intent');
            Route::post('/extract-info', [InboxAiToolsController::class, 'extractInfo'])->name('extract-info');
            Route::post('/sentiment', [InboxAiToolsController::class, 'sentiment'])->name('sentiment');
        });
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
        Route::middleware('permission:manage gmail')->prefix('gmail-config')->name('gmail-config.')->group(function () {
            Route::get('/', [GmailSettingsController::class, 'index'])->name('index');
            Route::put('/', [GmailSettingsController::class, 'update'])->name('update');
            Route::post('/test-connection', [GmailSettingsController::class, 'testConnection'])->name('test-connection');
        });

        // Global AI provider configuration (provider/api key/base url/model/
        // temperature/max tokens/timeout), admin-only: this replaces editing
        // .env and config/openai.php for AI credentials.
        Route::middleware('permission:manage models')->prefix('ai-config')->name('ai-config.')->group(function () {
            Route::get('/', [AiSettingsController::class, 'index'])->name('index');
            Route::put('/', [AiSettingsController::class, 'update'])->name('update');
            Route::post('/test-connection', [AiSettingsController::class, 'testConnection'])->name('test-connection');
        });
    });

    // AI Center: the orchestration "brain" (Intent/SOP/Rule/Workflow/
    // Knowledge Base/Reply Template/AI Models/AI Parameters/Prompt Preview/
    // Playground/AI Logs/Dashboard/Settings). Gated per sub-resource so the
    // three named permissions from claude.txt ("manage Models"/"manage
    // Prompt"/"manage Workflow") are meaningful, everything else falls back
    // to the general "manage ai center" permission.
    Route::middleware('permission:manage ai center')->prefix('ai-center')->name('ai-center.')->group(function () {
        Route::get('/', [AiCenterDashboardController::class, 'index'])->name('dashboard');

        Route::post('categories', [AiCenterCategoryController::class, 'store'])->name('categories.store');

        Route::resource('intents', AiCenterIntentController::class);
        Route::resource('forbidden-actions', AiCenterForbiddenActionController::class)->only(['index', 'store', 'destroy']);
        Route::resource('knowledge-bases', AiCenterKnowledgeBaseController::class);
        Route::resource('reply-templates', AiCenterReplyTemplateController::class);

        Route::resource('sops', AiCenterSopController::class);
        Route::resource('sops.rules', AiCenterSopRuleController::class)->shallow()->except(['show']);

        Route::get('ai-logs', [AiCenterAiLogController::class, 'index'])->name('ai-logs.index');
        Route::get('ai-logs/{aiLog}', [AiCenterAiLogController::class, 'show'])->name('ai-logs.show');

        Route::get('settings', [AiCenterSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [AiCenterSettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:manage models')->prefix('ai-center')->name('ai-center.')->group(function () {
        Route::resource('ai-models', AiCenterAiModelController::class);
        Route::post('ai-models/{aiModel}/set-default', [AiCenterAiModelController::class, 'setDefault'])->name('ai-models.set-default');
        Route::post('ai-models/{aiModel}/test-connection', [AiCenterAiModelController::class, 'testConnection'])->name('ai-models.test-connection');

        Route::get('ai-parameters', [AiCenterAiParameterController::class, 'edit'])->name('ai-parameters.edit');
        Route::put('ai-parameters', [AiCenterAiParameterController::class, 'update'])->name('ai-parameters.update');
    });

    Route::middleware('permission:manage prompt')->prefix('ai-center')->name('ai-center.')->group(function () {
        Route::get('prompt-preview', [AiCenterPromptPreviewController::class, 'index'])->name('prompt-preview.index');

        Route::get('playground', [AiCenterPlaygroundController::class, 'index'])->name('playground.index');
        Route::post('playground', [AiCenterPlaygroundController::class, 'run'])->name('playground.run');
    });

    Route::middleware('permission:manage workflow')->prefix('ai-center')->name('ai-center.')->group(function () {
        Route::resource('workflows', AiCenterWorkflowController::class);
        Route::post('workflows/{workflow}/nodes', [AiCenterWorkflowNodeController::class, 'store'])->name('workflows.nodes.store');
        Route::delete('workflows/{workflow}/nodes/{node}', [AiCenterWorkflowNodeController::class, 'destroy'])->name('workflows.nodes.destroy');
    });

    // Reports: system-wide analytics dashboard. Admin sees everything via
    // "manage reports" (or the Gate::before "manage everything" bypass),
    // Agent gets read access via its own "view reports" permission.
    Route::middleware('permission:manage reports|view reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsOverviewController::class, 'index'])->name('index');
        Route::get('ai-usage', [ReportsAiUsageController::class, 'index'])->name('ai-usage');
        Route::get('content', [ReportsContentController::class, 'index'])->name('content');
        Route::get('customers', [ReportsCustomerController::class, 'index'])->name('customers');
        Route::get('gmail-accounts', [ReportsGmailController::class, 'index'])->name('gmail-accounts');
        Route::get('timeline', [ReportsTimelineController::class, 'index'])->name('timeline');
        Route::get('{report}/export/{format}', [ReportsExportController::class, 'export'])->name('export');
    });

    // Users: Admin-only management of accounts and their Admin/Agent role.
    Route::middleware('permission:manage users')
        ->resource('users', UserController::class)
        ->except(['show']);
});
