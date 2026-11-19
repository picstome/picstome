<?php

namespace App\Services;

use App\Models\Team;

/**
 * Plan limits applied to the contracts created by a team.
 */
class ContractPlanLimitService extends MonthlyPlanLimitService
{
    public function countThisMonth(Team $team): int
    {
        return $team->contracts()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    protected function limitField(): string
    {
        return 'monthly_contract_limit';
    }

    protected function hasUnlimited(Team $team): bool
    {
        return $team->has_unlimited_contracts || parent::hasUnlimited($team);
    }
}
