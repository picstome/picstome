<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;
use App\Policies\Concerns\BelongsToCurrentTeam;

class PhotoPolicy
{
    use BelongsToCurrentTeam;

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
    public function view(User $user, Photo $photo): bool
    {
        return $this->belongsToCurrentTeam($user, $photo->gallery->team);
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
    public function update(User $user, Photo $photo): bool
    {
        return $this->belongsToCurrentTeam($user, $photo->gallery->team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Photo $photo): bool
    {
        return $this->belongsToCurrentTeam($user, $photo->gallery->team);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Photo $photo): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Photo $photo): bool
    {
        return false;
    }
}
