<?php

namespace App\Services;

use App\Models\Team;

/**
 * Shared "monthly plan limit" rules for invoices, payments and contracts.
 *
 * A null limit means unlimited, and paid or lifetime teams are never limited.
 */
abstract class MonthlyPlanLimitService
{
    abstract protected function limitField(): string;

    abstract public function countThisMonth(Team $team): int;

    protected function hasUnlimited(Team $team): bool
    {
        return $team->subscribed() || is_null($team->{$this->limitField()});
    }

    public function canUse(Team $team): bool
    {
        if ($this->hasUnlimited($team)) {
            return true;
        }

        if ($team->{$this->limitField()} <= 0) {
            return false;
        }

        return $this->countThisMonth($team) < $team->{$this->limitField()};
    }

    /**
     * Items the team may still create this month, or null when unlimited.
     */
    public function remaining(Team $team): ?int
    {
        if ($this->hasUnlimited($team)) {
            return null;
        }

        return max(0, $team->{$this->limitField()} - $this->countThisMonth($team));
    }
}
