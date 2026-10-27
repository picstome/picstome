<?php

namespace App\Providers;

use App\Models\Team;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

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
        // Admin translation edits live in storage and are appended after
        // lang/, so they override the repository files at runtime.
        app('translation.loader')->addPath(config('translation_overrides.path'));

        Cashier::useCustomerModel(Team::class);

        Blade::if('subscribed', function (Team $team) {
            return $team && $team->subscribed();
        });
    }
}
