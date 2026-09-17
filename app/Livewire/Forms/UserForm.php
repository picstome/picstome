<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Livewire\Form;

class UserForm extends Form
{
    public ?User $user = null;

    public ?float $custom_storage_limit = null;

    public ?int $monthly_contract_limit = null;

    public bool $lifetime = false;

    public function setUser(User $user)
    {
        $this->user = $user;

        $team = $user->personalTeam();

        $this->custom_storage_limit = ! is_null($team?->custom_storage_limit)
            ? round($team->storage_limit / 1073741824, 2) // Convert bytes to GB
            : null;

        $this->monthly_contract_limit = $team?->monthly_contract_limit;
        $this->lifetime = $team?->lifetime_at !== null;
    }

    public function update()
    {
        $this->validate([
            'custom_storage_limit' => 'nullable|numeric|min:0',
            'monthly_contract_limit' => 'nullable|integer|min:0',
        ]);

        if (! $team = $this->user->personalTeam()) {
            return;
        }

        $bytes = $this->custom_storage_limit !== null
            ? (int) $this->custom_storage_limit * (1024 ** 3) // Convert GB to bytes
            : null;

        $team->update([
            'custom_storage_limit' => $bytes,
            'monthly_contract_limit' => $this->monthly_contract_limit,
            'lifetime_at' => $this->lifetime ? now() : null,
        ]);
    }
}
