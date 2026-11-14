<?php

namespace App\Services;

use App\Models\Team;
use Carbon\Carbon;

/**
 * The compact account summary shared by the sidebar card and the account menu.
 *
 * It keeps plan naming and billing links in one place so both entry points
 * always show the same plan and send the user to the same place.
 */
class AccountSummaryService
{
    public function __construct(private readonly PlanCatalog $catalog) {}

    /**
     * @return array{
     *     plan_label: string,
     *     plan_name: string|null,
     *     interval_label: string|null,
     *     is_free: bool,
     *     is_lifetime: bool,
     *     renews_at: Carbon|null,
     *     renews_at_label: string|null,
     *     manage_url: string,
     *     subscription_url: string,
     *     more_storage_url: string,
     *     storage_used: string,
     *     storage_total: string,
     *     storage_percent: string,
     *     has_unlimited_storage: bool
     * }
     */
    public function forTeam(Team $team): array
    {
        $subscription = $team->subscription('default');
        $isLifetime = $team->lifetime_at !== null;
        $isFree = ! $isLifetime && ! $team->subscribed();
        $tier = $this->catalog->tierForStorageLimit($team->custom_storage_limit);
        $planName = $tier ? $this->catalog->tier($tier)['name'] : null;
        $intervalLabel = $this->intervalLabel($subscription?->billing_interval);

        $planLabel = match (true) {
            $isLifetime => trim(($planName ? $planName.' · ' : '').__('One-time'), ' ·'),
            $isFree => __('Free plan'),
            default => trim(($planName ? $planName.' · ' : '').($intervalLabel ?? __('Active plan')), ' ·'),
        };

        $renewsAt = $subscription?->current_period_end;
        $renewsAtLabel = $renewsAt
            ? __('Renews :date', [
                'date' => $renewsAt->copy()
                    ->timezone(config('app.timezone', 'UTC'))
                    ->format('d/m/Y'),
            ])
            : null;

        return [
            'plan_label' => $planLabel,
            'plan_name' => $planName,
            'interval_label' => $intervalLabel,
            'is_free' => $isFree,
            'is_lifetime' => $isLifetime,
            'renews_at' => $renewsAt,
            'renews_at_label' => $renewsAtLabel,
            'manage_url' => $isFree ? route('subscribe') : route('billing-portal'),
            'subscription_url' => route('subscribe'),
            'more_storage_url' => $isFree ? route('subscribe') : route('billing-portal'),
            'storage_used' => $team->storage_used_gb,
            'storage_total' => $team->hasUnlimitedStorage ? __('Unlimited') : $team->storage_limit_gb,
            'storage_percent' => (string) ($team->storage_used_percent ?? '0'),
            'has_unlimited_storage' => $team->hasUnlimitedStorage,
        ];
    }

    private function intervalLabel(?string $interval): ?string
    {
        return match ($interval) {
            'month' => __('Monthly'),
            'year' => __('Annual'),
            default => null,
        };
    }
}
