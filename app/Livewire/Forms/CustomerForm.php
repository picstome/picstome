<?php

namespace App\Livewire\Forms;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CustomerForm extends Form
{
    public ?string $name = '';

    public ?string $email = '';

    public ?string $phone = '';

    public ?string $birthdate = '';

    public ?string $notes = '';

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->where(fn ($q) => $q->where('team_id', Auth::user()->currentTeam->id)),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function store()
    {
        $this->validate();

        $customer = Auth::user()->currentTeam->customers()->create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate,
            'notes' => $this->notes,
        ]);

        return $customer;
    }
}
