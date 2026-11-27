<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Machine-readable plan and quota data for external API clients.
 *
 * `AccountSummaryService` formats localized labels for the web UI; this
 * service returns raw values (bytes, nullable limits and ISO dates) so a
 * desktop or mobile client can render its own counters without parsing text.
 */
class AccountLimitsService
{
    public function __construct(
        private readonly PlanCatalog $catalog,
        private readonly ContractPlanLimitService $contracts,
        private readonly InvoicePlanLimitService $invoices,
        private readonly PaymentPlanLimitService $payments,
    ) {}

    /**
     * @return array{
     *     plan: array{
     *         key: string|null,
     *         name: string|null,
     *         is_free: bool,
     *         is_lifetime: bool,
     *         billing_interval: string|null,
     *         renews_at: string|null
     *     },
     *     limits: array{
     *         storage: array{limit_bytes: int|null, used_bytes: int, remaining_bytes: int|null, unlimited: bool},
     *         gallery_retention: array{unlimited: bool, max_years: int, default_expiration_months: int},
     *         contracts_per_month: array{limit: int|null, used: int, remaining: int|null, unlimited: bool},
     *         invoices_per_month: array{limit: int|null, used: int, remaining: int|null, unlimited: bool},
     *         payments_per_month: array{limit: int|null, used: int, remaining: int|null, unlimited: bool}
     *     }
     * }
     */
    public function forTeam(Team $team): array
    {
        $subscription = $team->subscription('default');
        $activeSubscription = $subscription?->valid() ? $subscription : null;
        $isLifetime = $team->lifetime_at !== null;
        $storageLimit = $team->custom_storage_limit;
        $renewsAt = $activeSubscription?->current_period_end;
        $tierKey = $isLifetime || $activeSubscription !== null
            ? $this->catalog->tierForStorageLimit($storageLimit)
            : 'free';

        return [
            'plan' => [
                'key' => $tierKey,
                'name' => $this->planName($tierKey),
                'is_free' => $tierKey === 'free',
                'is_lifetime' => $isLifetime,
                'billing_interval' => $activeSubscription?->billing_interval,
                'renews_at' => $renewsAt === null ? null : Carbon::parse($renewsAt)->toIso8601String(),
            ],
            'limits' => [
                'storage' => [
                    'limit_bytes' => $storageLimit,
                    'used_bytes' => $team->calculateStorageUsed(),
                    'remaining_bytes' => $team->remainingStorageBytes(),
                    'unlimited' => $storageLimit === null,
                ],
                'gallery_retention' => [
                    'unlimited' => $team->hasUnlimitedRetention(),
                    'max_years' => 1,
                    'default_expiration_months' => 1,
                ],
                'contracts_per_month' => $this->monthlyLimit($team, $this->contracts, 'monthly_contract_limit'),
                'invoices_per_month' => $this->monthlyLimit($team, $this->invoices, 'monthly_invoice_limit'),
                'payments_per_month' => $this->monthlyLimit($team, $this->payments, 'monthly_payment_limit'),
            ],
        ];
    }

    /**
     * Display name of the plan, or null when a paid team has a custom storage
     * limit that does not match any published tier.
     */
    private function planName(?string $tierKey): ?string
    {
        if ($tierKey === null) {
            return null;
        }

        if ($tierKey === 'free') {
            return 'Free';
        }

        return $this->catalog->tiers()[$tierKey]['name'] ?? null;
    }

    /**
     * @return array{limit: int|null, used: int, remaining: int|null, unlimited: bool}
     */
    private function monthlyLimit(Team $team, MonthlyPlanLimitService $service, string $limitField): array
    {
        $remaining = $service->remaining($team);

        return [
            'limit' => $remaining === null ? null : (int) $team->{$limitField},
            'used' => $service->countThisMonth($team),
            'remaining' => $remaining,
            'unlimited' => $remaining === null,
        ];
    }
}
