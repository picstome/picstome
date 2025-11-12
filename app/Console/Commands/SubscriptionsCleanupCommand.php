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

        Team::has('subscriptions')->cursor()->each(function ($team) use ($warningDays) {
            $subscription = $team->subscription();

            if ($subscription && $subscription->stripe_status === 'active' && $subscription->ends_at) {
                foreach ($warningDays as $days) {
                    $targetDate = now()->addDays($days);
                    if ($subscription->ends_at->isSameDay($targetDate)) {
                        $team->owner->notify(new SubscriptionExpiringSoon($days));
                        break; // Send only one notification per team
                    }
                }
            }
        });
    }

    private function notifyExpired()
    {
        $expiredWarningDays = config('picstome.subscription_expired_warning_days', 1);
        $gracePeriodDays = config('picstome.subscription_grace_period_days', 7);
        $daysLeft = $gracePeriodDays - $expiredWarningDays;
        $targetDate = now()->subDays($expiredWarningDays);

        Team::has('subscriptions')->cursor()->each(function ($team) use ($targetDate, $daysLeft) {
            $subscription = $team->subscription();

            if ($subscription &&
                $subscription->stripe_status === 'canceled' &&
                $subscription->ends_at &&
                $subscription->ends_at->isSameDay($targetDate)) {
                $team->owner->notify(new SubscriptionExpiredWarning($daysLeft));
            }
        });
    }

    private function deleteExpiredData()
    {
        $gracePeriodDays = config('picstome.subscription_grace_period_days', 7);

        Team::has('subscriptions')->cursor()->each(function ($team) use ($gracePeriodDays) {
            $subscription = $team->subscription();

            if ($subscription &&
                $subscription->stripe_status === 'canceled' &&
                $subscription->ends_at &&
                $subscription->ends_at->lt(now()->subDays($gracePeriodDays))) {
                $team->galleries->each(function ($gallery) {
                    $gallery->deletePhotos()->delete();
                });
            }
        });
    }
}