<?php

namespace App\Policies;

use App\Models\BioLink;
use App\Models\User;

class BioLinkPolicy
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
    public function view(User $user, BioLink $bioLink): bool
    {
        return $bioLink->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BioLink $bioLink): bool
    {
        return $bioLink->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BioLink $bioLink): bool
    {
        return $bioLink->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BioLink $bioLink): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BioLink $bioLink): bool
    {
        return false;
    }
}