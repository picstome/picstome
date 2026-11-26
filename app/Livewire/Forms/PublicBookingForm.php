<?php

namespace App\Livewire\Forms;

use Livewire\Form;

/**
 * Client details on the public booking page.
 *
 * The phone is required only when the photographer configured it that way, and
 * the cancellation policy only when one is configured, so a photographer who
 * has not written one is not blocked from receiving bookings.
 */
class PublicBookingForm extends Form
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $notes = '';

    public bool $acceptedPolicy = false;

    public bool $phoneRequired = false;

    public bool $hasPolicy = false;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => [$this->phoneRequired ? 'required' : 'nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'acceptedPolicy' => [$this->hasPolicy ? 'accepted' : 'boolean'],
        ];
    }
}
