<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Any authenticated user can view their team's customers
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        // User can view if customer belongs to their current team
        return $user->currentTeam && $customer->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user with a current team can create a customer
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        // User can update if customer belongs to their current team
        return $user->currentTeam && $customer->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        // User can delete if customer belongs to their current team
        return $user->currentTeam && $customer->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        // User can restore if customer belongs to their current team
        return $user->currentTeam && $customer->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        // User can force delete if customer belongs to their current team
        return $user->currentTeam && $customer->team_id === $user->currentTeam->id;
    }
}
