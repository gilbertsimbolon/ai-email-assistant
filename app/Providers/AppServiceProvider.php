<?php

namespace App\Providers;

use App\Enums\ChannelType;
use App\Models\Conversation;
use App\Services\AI\AiConfigurationService;
use App\Services\AI\AiClientService;
use App\Services\AI\Contracts\AiClientInterface;
use App\Services\Gmail\GmailConfigurationService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so the gmail_settings row is read from the database at
        // most once per request/job instead of once per Gmail API call.
        $this->app->singleton(GmailConfigurationService::class);

        // Singleton so the ai_settings row is read from the database at
        // most once per request/job instead of once per AI call.
        $this->app->singleton(AiConfigurationService::class);

        // AnalysisService/DraftService depend on the AiClientInterface
        // abstraction only, never on a provider adapter directly.
        $this->app->bind(AiClientInterface::class, AiClientService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // "Can manage Everything" (claude.txt) acts as a literal Admin
        // bypass so every @can/permission-middleware check passes for
        // Admin without duplicating every Agent permission onto the role.
        // Guarded because hasPermissionTo() throws if the permission row
        // doesn't exist yet (fresh install before RolePermissionSeeder
        // runs) — Gate::before fires on every authorization check
        // app-wide, so that exception must never bubble up from here.
        Gate::before(function ($user, string $ability) {
            if (! $user) {
                return null;
            }

            try {
                return $user->hasPermissionTo('manage everything') ? true : null;
            } catch (\Throwable $e) {
                return null;
            }
        });

        // Memaksa skema URL menjadi HTTPS jika aplikasi mendeteksi menggunakan HTTPS (seperti via Ngrok)
        if (config('app.env') !== 'local' || request()->server('HTTP_X_FORWARDED_PROTO') === 'https') {
            URL::forceScheme('https');
        }

        // Sidebar is included on every authenticated page, so it needs its
        // own composer to know the Email unread badge count.
        View::composer('partials.sidebar', function ($view) {
            $emailUnreadCount = auth()->check()
                ? Conversation::whereHas('gmailAccount', fn ($query) => $query->where('user_id', auth()->id()))
                    ->where('channel', ChannelType::Email)
                    ->where('is_read', false)
                    ->count()
                : 0;

            $view->with('emailUnreadCount', $emailUnreadCount);
        });

        // Topbar shows the currently connected Gmail account, if any.
        View::composer('partials.navbar', function ($view) {
            $view->with(
                'navbarGmailAccount',
                auth()->check() ? auth()->user()->gmailAccounts()->first() : null
            );
        });
    }
}
