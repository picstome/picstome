<?php

namespace App\Policies;

use App\Models\Photoshoot;
use App\Models\User;

class PhotoshootPolicy
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
    public function view(User $user, Photoshoot $photoshoot): bool
    {
        return $photoshoot->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Photoshoot $photoshoot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Photoshoot $photoshoot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Photoshoot $photoshoot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Photoshoot $photoshoot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can add a contract to the photoshoot.
     */
    public function addContract(User $user, Photoshoot $photoshoot): bool
    {
        return $photoshoot->team->is($user->currentTeam);
    }

}
