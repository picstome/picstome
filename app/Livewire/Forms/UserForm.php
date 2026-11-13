<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Livewire\Form;

class UserForm extends Form
{
    public ?User $user = null;

    public ?float $custom_storage_limit = null;

    public ?int $monthly_contract_limit = null;

    public ?int $monthly_invoice_limit = null;

    public ?int $monthly_payment_limit = null;

    public bool $lifetime = false;

    /**
     * Admin-editable email verification state. It maps to
     * `users.email_verified_at` and lets an admin mark an address as verified
     * without sending the confirmation email.
     */
    public bool $emailVerified = false;

    public function setUser(User $user)
    {
        $this->user = $user;

        $team = $user->personalTeam();

        $this->custom_storage_limit = ! is_null($team?->custom_storage_limit)
            ? round($team->storage_limit / 1073741824, 2) // Convert bytes to GB
            : null;

        $this->monthly_contract_limit = $team?->monthly_contract_limit;
        $this->monthly_invoice_limit = $team?->monthly_invoice_limit;
        $this->monthly_payment_limit = $team?->monthly_payment_limit;
        $this->lifetime = $team?->lifetime_at !== null;
        $this->emailVerified = $user->hasVerifiedEmail();
    }

    public function update()
    {
        $this->validate([
            'custom_storage_limit' => 'nullable|numeric|min:0',
            'monthly_contract_limit' => 'nullable|integer|min:0',
            'monthly_invoice_limit' => 'nullable|integer|min:0',
            'monthly_payment_limit' => 'nullable|integer|min:0',
            'emailVerified' => 'boolean',
        ]);

        // The verification flag is saved even when the account has no team.
        $this->user->forceFill([
            'email_verified_at' => $this->emailVerified
                ? ($this->user->email_verified_at ?? now())
                : null,
        ])->save();

        if (! $team = $this->user->personalTeam()) {
            return;
        }

        $bytes = $this->custom_storage_limit !== null
            ? (int) $this->custom_storage_limit * (1024 ** 3) // Convert GB to bytes
            : null;

        $team->update([
            'custom_storage_limit' => $bytes,
            'monthly_contract_limit' => $this->monthly_contract_limit,
            'monthly_invoice_limit' => $this->monthly_invoice_limit,
            'monthly_payment_limit' => $this->monthly_payment_limit,
            'lifetime_at' => $this->lifetime ? now() : null,
        ]);
    }
}
