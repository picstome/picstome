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

    public function rules()
    {
        $emailRule = Rule::unique('customers', 'email')
            ->where(fn ($q) => $q->where('team_id', Auth::user()->currentTeam->id));

        if (isset($this->customer) && $this->customer) {
            $emailRule->ignore($this->customer->id);
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
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->customers()->create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate,
            'notes' => $this->notes,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->customer->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate,
            'notes' => $this->notes,
        ]);
    }
}
