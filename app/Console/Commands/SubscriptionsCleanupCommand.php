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
        $warningDays = config('picstome.subscription_warning_days', [15, 7, 1]);

        foreach ($warningDays as $days) {
            $date = now()->addDays($days);
            Team::whereHas('subscriptions', function ($query) use ($date) {
                $query->where('ends_at', '>=', $date->startOfDay())
                      ->where('ends_at', '<', $date->copy()->endOfDay())
                      ->where('stripe_status', 'active');
            })->get()->each(function ($team) use ($days) {
                $team->owner->notify(new SubscriptionExpiringSoon($days));
            });
        }
    }

    private function notifyExpired()
    {
        $expiredWarningDays = config('picstome.subscription_expired_warning_days', 1);
        $gracePeriodDays = config('picstome.subscription_grace_period_days', 7);
        $daysLeft = $gracePeriodDays - $expiredWarningDays;

        Team::whereHas('subscriptions', function ($query) use ($expiredWarningDays) {
            $query->where('ends_at', '>=', now()->subDays($expiredWarningDays)->startOfDay())
                  ->where('ends_at', '<', now()->subDays($expiredWarningDays)->endOfDay())
                  ->where('stripe_status', 'canceled');
        })->get()->each(function ($team) use ($daysLeft) {
            $team->owner->notify(new SubscriptionExpiredWarning($daysLeft));
        });
    }

    private function deleteExpiredData()
    {
        $gracePeriodDays = config('picstome.subscription_grace_period_days', 7);

        Team::whereHas('subscriptions', function ($query) use ($gracePeriodDays) {
            $query->where('ends_at', '<', now()->subDays($gracePeriodDays))
                  ->where('stripe_status', 'canceled');
        })->get()->each(function ($team) {
            $team->galleries->each(function ($gallery) {
                $gallery->deletePhotos()->delete();
            });
        });
    }
}