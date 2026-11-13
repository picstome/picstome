<?php

namespace App\Policies;

use App\Models\ContractTemplate;
use App\Models\User;

class ContractTemplatePolicy
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
    public function view(User $user, ContractTemplate $contractTemplate): bool
    {
        if ($contractTemplate->isPublic()) {
            return true;
        }

        return $contractTemplate->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Team members can edit their own templates. Picstome templates are
     * global, so only admins may change them.
     */
    public function update(User $user, ContractTemplate $contractTemplate): bool
    {
        if ($contractTemplate->isPublic()) {
            return (bool) $user->is_admin;
        }

        return $contractTemplate->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ContractTemplate $contractTemplate): bool
    {
        if ($contractTemplate->isPublic()) {
            return (bool) $user->is_admin;
        }

        return $contractTemplate->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ContractTemplate $contractTemplate): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ContractTemplate $contractTemplate): bool
    {
        return false;
    }
}
