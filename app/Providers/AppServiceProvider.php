<?php

namespace App\Providers;

use App\Models\Team;
use App\Services\RawPhotoService;
use Illuminate\Support\Collection;
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
        $this->app->singleton(RawPhotoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Team::class);

        Blade::if('subscribed', function (Team $team) {
            return $team && $team->subscribed();
        });

        Collection::macro('naturalSortBy', function ($attribute = 'name') {
            /** @var \Illuminate\Support\Collection $this */
            return $this->sort(function ($a, $b) use ($attribute) {
                return strnatcmp($a->$attribute, $b->$attribute);
            })->values();
        });
    }
}
