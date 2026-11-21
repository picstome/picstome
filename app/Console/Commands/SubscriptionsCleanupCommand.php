<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Notifications\SubscriptionExpiredWarning;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;

class SubscriptionsCleanupCommand extends Command
{
    protected $signature = 'subscriptions:cleanup {--dry-run} {--force}';

    protected $description = 'Clean up expired subscriptions and send notifications';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        // A dry run only reports: it must not email customers either.
        if ($dryRun) {
            $this->line('Dry run: no notifications were sent and expired data was not deleted.');

            return self::SUCCESS;
        }

        $this->notifyExpiringSoon();
        $this->notifyExpired();

        if (! $force) {
            $this->warn('Skipped deleting expired data. Use --force to delete it.');

            return self::SUCCESS;
        }

        $this->deleteExpiredData();

        return self::SUCCESS;
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
