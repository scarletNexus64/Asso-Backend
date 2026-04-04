<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Shop;
use App\Models\DeviceToken;
use App\Observers\DeviceTokenObserver;

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
        // Register DeviceToken observer for automatic topic subscription
        DeviceToken::observe(DeviceTokenObserver::class);

        // Share pending shops count with all admin views
        View::composer('admin.layouts.app', function ($view) {
            $pendingShopsCount = Shop::pending()->count();
            $view->with('pendingShopsCount', $pendingShopsCount);
        });
    }
}
