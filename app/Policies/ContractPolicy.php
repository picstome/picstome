<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Contract $contract): bool
    {
        return $contract->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        if ($team->hasUnlimitedContracts || $team->subscribed()) {
            return true;
        }

        $limit = $team->monthly_contract_limit ?? 5;
        $limit = is_numeric($limit) && (int) $limit >= 0 ? (int) $limit : 5;

        $contractsThisMonth = $team->contracts()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return $contractsThisMonth < $limit;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Contract $contract): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Contract $contract): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Contract $contract): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Contract $contract): bool
    {
        return false;
    }
}
