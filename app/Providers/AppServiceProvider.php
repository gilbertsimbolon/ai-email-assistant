<?php

namespace App\Providers;

use App\Enums\ChannelType;
use App\Models\Conversation;
use App\Services\Gmail\GmailConfigurationService;
use Illuminate\Pagination\Paginator;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

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
    }
}
