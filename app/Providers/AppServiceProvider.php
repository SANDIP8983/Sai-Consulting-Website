<?php

namespace App\Providers;

use App\Contracts\WhatsAppChannelInterface;
use App\Models\User;
use App\Services\HomepageService;
use App\Services\Notifications\DisabledWhatsAppChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppChannelInterface::class, DisabledWhatsAppChannel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (config('permissions.keys') as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->is_active && $user->hasPermission($permission));
        }

        RateLimiter::for('public-request-submission', function (Request $request): Limit {
            $privacySafeKey = hash('sha256', (string) $request->ip());

            return Limit::perMinute(10)
                ->by($privacySafeKey)
                ->response(fn () => back()
                    ->withErrors(['request' => 'Too many requests were submitted in a short time. Please wait a minute and try again.'])
                    ->withInput($request->except(['documents'])));
        });

        View::composer(['layouts.app', 'partials.header', 'partials.footer'], function ($view): void {
            $view->with('site', app(HomepageService::class)->publicSiteData());
        });
    }
}
