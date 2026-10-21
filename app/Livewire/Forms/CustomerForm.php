<?php

namespace App\Livewire\Forms;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CustomerForm extends Form
{
    public ?Customer $customer;

    public ?string $name = '';

    public ?string $email = '';

    public ?string $phone = '';

    public ?string $birthdate = '';

    public ?string $notes = '';

    public ?string $legal_name = '';

    public ?string $tax_id = '';

    public ?string $address = '';

    public ?string $city = '';

    public ?string $state = '';

    public ?string $postal_code = '';

    public ?string $country = '';

    public function rules()
    {
        $emailRule = Rule::unique('customers', 'email')
            ->where(fn ($q) => $q->where('team_id', Auth::user()->currentTeam->id));

        $taxIdRule = Rule::unique('customers', 'tax_id')
            ->where(fn ($q) => $q->where('team_id', Auth::user()->currentTeam->id));

        if (isset($this->customer) && $this->customer) {
            $emailRule->ignore($this->customer->id);
            $taxIdRule->ignore($this->customer->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                $emailRule,
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => [
                'nullable',
                'string',
                'max:32',
                $taxIdRule,
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
        ];
    }

    public function setCustomer(Customer $customer)
    {
        $this->customer = $customer;

        $this->name = $customer->name;

        $this->email = $customer->email;

        $this->phone = $customer->phone;

        $this->birthdate = $customer->birthdate?->format('Y-m-d');

        $this->notes = $customer->notes;

        $this->legal_name = $customer->legal_name;

        $this->tax_id = $customer->tax_id;

        $this->address = $customer->address;

        $this->city = $customer->city;

        $this->state = $customer->state;

        $this->postal_code = $customer->postal_code;

        $this->country = $customer->country;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->customers()->create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birthdate' => empty($this->birthdate) ? null : $this->birthdate,
            'notes' => $this->notes,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id ? strtoupper($this->tax_id) : null,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country ? strtoupper($this->country) : null,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->customer->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birthdate' => empty($this->birthdate) ? null : $this->birthdate,
            'notes' => $this->notes,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id ? strtoupper($this->tax_id) : null,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country ? strtoupper($this->country) : null,
        ]);
    }
}
