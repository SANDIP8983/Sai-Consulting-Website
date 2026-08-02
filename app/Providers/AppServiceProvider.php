<?php

namespace App\Providers;

use App\Services\HomepageService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.app', 'partials.header', 'partials.footer'], function ($view): void {
            $view->with('site', app(HomepageService::class)->publicSiteData());
        });
    }
}
