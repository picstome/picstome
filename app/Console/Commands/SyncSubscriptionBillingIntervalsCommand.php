<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\Exception\InvalidRequestException;
use Throwable;

class SyncSubscriptionBillingIntervalsCommand extends Command
{
    protected $signature = 'subscriptions:sync-billing-intervals
                            {--dry-run : Show what would change without writing}
                            {--force : Write the changes}';

    protected $description = 'Backfill subscriptions.billing_interval from Stripe for existing subscriptions';

    public function handle(): int
    {
        if (! $this->option('dry-run') && ! $this->option('force')) {
            $this->error('Use --dry-run to inspect or --force to write.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        Subscription::query()
            ->whereNotNull('stripe_id')
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->chunkById(100, function ($subscriptions) use ($dryRun, &$updated, &$skipped, &$failed): void {
                foreach ($subscriptions as $subscription) {
                    try {
                        $stripeSubscription = $subscription->asStripeSubscription();
                        $interval = $stripeSubscription->items->data[0]->price->recurring->interval ?? null;
                        $periodEnd = $stripeSubscription->current_period_end
                            ?? $stripeSubscription->items->data[0]->current_period_end
                            ?? null;

                        if (! in_array($interval, ['month', 'year'], true)) {
                            $this->warn("Skipping {$subscription->stripe_id}: no recurring interval.");
                            $skipped++;

                            continue;
                        }

                        $periodEndAt = $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null;

                        $intervalChanged = $subscription->billing_interval !== $interval;
                        $periodChanged = $periodEndAt !== null
                            && (! $subscription->current_period_end
                                || ! $subscription->current_period_end->equalTo($periodEndAt));

                        if (! $intervalChanged && ! $periodChanged) {
                            $skipped++;

                            continue;
                        }

                        $this->line(sprintf(
                            '%s %s: interval %s -> %s, period end %s -> %s',
                            $dryRun ? '[dry-run]' : '[write]',
                            $subscription->stripe_id,
                            $subscription->billing_interval ?? 'null',
                            $interval,
                            $subscription->current_period_end?->toDateString() ?? 'null',
                            $periodEndAt?->toDateString() ?? 'null',
                        ));

                        if (! $dryRun) {
                            $updates = ['billing_interval' => $interval];

                            if ($periodEndAt !== null) {
                                $updates['current_period_end'] = $periodEndAt;
                            }

                            $subscription->forceFill($updates)->save();
                        }

                        $updated++;
                    } catch (InvalidRequestException $exception) {
                        if ($exception->getHttpStatus() === 404) {
                            $skipped++;
                            $this->warn("Skipping {$subscription->stripe_id}: not found in Stripe.");

                            continue;
                        }

                        $failed++;
                        $this->warn("Could not sync {$subscription->stripe_id}: {$exception->getMessage()}");
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->warn("Could not sync {$subscription->stripe_id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->info(sprintf(
            '%s %d subscription(s), skipped %d, failed %d.',
            $dryRun ? 'Would update' : 'Updated',
            $updated,
            $skipped,
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
