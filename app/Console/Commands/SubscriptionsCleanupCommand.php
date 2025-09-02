<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Gallery;
use App\Notifications\SubscriptionExpiringSoon;
use App\Notifications\SubscriptionExpiredWarning;
use Illuminate\Console\Command;

class SubscriptionsCleanupCommand extends Command
{
    protected $signature = 'subscriptions:cleanup';

    protected $description = 'Clean up expired subscriptions and send notifications';

    public function handle()
    {
        $this->notifyExpiringSoon();
        $this->notifyExpired();
        $this->deleteExpiredData();

        return 0;
    }

    private function notifyExpiringSoon()
    {
        $dates = [
            now()->addDays(15),
            now()->addDays(7),
            now()->addDay(),
        ];

        foreach ($dates as $date) {
            Team::whereHas('subscriptions', function ($query) use ($date) {
                $query->where('ends_at', '>=', $date->startOfDay())
                      ->where('ends_at', '<', $date->copy()->endOfDay())
                      ->where('stripe_status', 'active');
            })->get()->each(function ($team) {
                $team->owner->notify(new SubscriptionExpiringSoon());
            });
        }
    }

    private function notifyExpired()
    {
        Team::whereHas('subscriptions', function ($query) {
            $query->where('ends_at', '>=', now()->subDay()->startOfDay())
                  ->where('ends_at', '<', now()->subDay()->endOfDay())
                  ->where('stripe_status', 'canceled');
        })->get()->each(function ($team) {
            $team->owner->notify(new SubscriptionExpiredWarning());
        });
    }

    private function deleteExpiredData()
    {
        Team::whereHas('subscriptions', function ($query) {
            $query->where('ends_at', '<', now()->subDays(30))
                  ->where('stripe_status', 'canceled');
        })->get()->each(function ($team) {
            $team->galleries->each(function ($gallery) {
                $gallery->deletePhotos()->delete();
            });
        });
    }
}