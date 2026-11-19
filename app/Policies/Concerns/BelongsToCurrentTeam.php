<?php

namespace App\Policies\Concerns;

use App\Models\Team;
use App\Models\User;

/**
 * Shared team-membership checks for model policies.
 */
trait BelongsToCurrentTeam
{
    protected function belongsToCurrentTeam(User $user, ?Team $team): bool
    {
        return $team !== null && $team->is($user->currentTeam);
    }

    protected function belongsToCurrentTeamId(User $user, ?int $teamId): bool
    {
        return $teamId !== null && $teamId === $user->currentTeam?->id;
    }
}
