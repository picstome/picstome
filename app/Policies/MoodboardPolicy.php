<?php

namespace App\Policies;

use App\Models\Moodboard;
use App\Models\User;

class MoodboardPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Moodboard $moodboard): bool
    {
        return $moodboard->team->is($user->currentTeam);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Moodboard $moodboard): bool
    {
        return $moodboard->team->is($user->currentTeam);
    }

    public function delete(User $user, Moodboard $moodboard): bool
    {
        return $moodboard->team->is($user->currentTeam);
    }

    public function restore(User $user, Moodboard $moodboard): bool
    {
        return false;
    }

    public function forceDelete(User $user, Moodboard $moodboard): bool
    {
        return false;
    }
}
