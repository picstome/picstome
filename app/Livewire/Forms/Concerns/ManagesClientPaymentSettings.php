<?php

namespace App\Livewire\Forms\Concerns;

use App\Models\Team;

/**
 * Bizum and the phone requirement are shared by bookings and payment links,
 * so both settings forms read and write the same team fields.
 */
trait ManagesClientPaymentSettings
{
    public bool $bizum_enabled = false;

    public string $bizum_phone = '';

    public string $bizum_instructions = '';

    public bool $phone_required = false;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function clientPaymentRules(): array
    {
        return [
            'bizum_enabled' => ['boolean'],
            'bizum_phone' => ['nullable', 'required_if:bizum_enabled,true', 'string', 'max:32'],
            'bizum_instructions' => ['nullable', 'string', 'max:2000'],
            'phone_required' => ['boolean'],
        ];
    }

    protected function fillClientPayment(Team $team): void
    {
        $this->bizum_enabled = (bool) $team->bizum_enabled;
        $this->bizum_phone = (string) $team->bizum_phone;
        $this->bizum_instructions = (string) $team->bizum_instructions;
        $this->phone_required = (bool) $team->phone_required;
    }

    protected function updateTeamClientPayment(Team $team): void
    {
        $team->update([
            'bizum_enabled' => $this->bizum_enabled,
            'bizum_phone' => $this->bizum_phone !== '' ? $this->bizum_phone : null,
            'bizum_instructions' => $this->bizum_instructions !== '' ? $this->bizum_instructions : null,
            'phone_required' => $this->phone_required,
        ]);
    }
}
