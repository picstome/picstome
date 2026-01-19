<?php

namespace App\Policies;

use App\Models\MoodboardPhoto;
use App\Models\User;

class MoodboardPhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, MoodboardPhoto $moodboardPhoto): bool
    {
        return $moodboardPhoto->moodboard->team->is($user->currentTeam);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MoodboardPhoto $moodboardPhoto): bool
    {
        return false;
    }

    public function delete(User $user, MoodboardPhoto $moodboardPhoto): bool
    {
        return $moodboardPhoto->moodboard->team->is($user->currentTeam);
    }

    public function restore(User $user, MoodboardPhoto $moodboardPhoto): bool
    {
        return false;
    }

    public function forceDelete(User $user, MoodboardPhoto $moodboardPhoto): bool
    {
        return false;
    }
}
