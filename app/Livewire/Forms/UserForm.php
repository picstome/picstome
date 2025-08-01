<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Livewire\Form;

class UserForm extends Form
{
    public ?User $user = null;

    public ?float $custom_storage_limit = null;

    public function setUser(User $user)
    {
        $this->user = $user;

        $this->custom_storage_limit = $user->personalTeam()->custom_storage_limit
            ? round($user->personalTeam()->storage_limit / 1073741824, 2) // Convert bytes to GB
            : null;
    }

    public function update()
    {
        $this->validate([
            'custom_storage_limit' => 'nullable|numeric|min:0',
        ]);

        $bytes = $this->custom_storage_limit !== null
            ? (int) $this->custom_storage_limit * (1024 ** 3) // Convert GB to bytes
            : null;

        $this->user->personalTeam()->update([
            'custom_storage_limit' => $bytes,
        ]);
    }
}
