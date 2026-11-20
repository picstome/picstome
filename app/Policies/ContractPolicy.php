<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use App\Policies\Concerns\BelongsToCurrentTeam;
use App\Services\ContractPlanLimitService;

class ContractPolicy
{
    use BelongsToCurrentTeam;

    public function __construct(private readonly ContractPlanLimitService $limits) {}

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
        return $this->belongsToCurrentTeam($user, $contract->team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->limits->canUse($user->currentTeam);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Contract $contract): bool
    {
        return $this->belongsToCurrentTeam($user, $contract->team);
    }

    /**
     * Determine whether the user can execute the model once signed.
     */
    public function execute(User $user, Contract $contract): bool
    {
        return $this->belongsToCurrentTeam($user, $contract->team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Contract $contract): bool
    {
        return $this->belongsToCurrentTeam($user, $contract->team);
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
