<?php

use App\Livewire\Forms\PaymentForm;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('payments');
middleware(['auth', 'verified']);

new class extends Component
{
    public PaymentForm $form;

    public function save()
    {
        $this->form->store();
    }

    #[Computed]
    public function payments()
    {
        return Auth::user()?->currentTeam
            ->payments()
            ->get();
    }
}; ?>

<x-app-layout>
    @volt('pages.payments')
        <div>
            <form wire:submit="save">
    <input wire:model="form.amount" type="number" step="0.01" placeholder="Amount" required />
    <input wire:model="form.currency" type="text" placeholder="Currency" required />
    <input wire:model="form.description" type="text" placeholder="Description" required />
    <input wire:model="form.customer_email" type="email" placeholder="Customer Email (optional)" />
    <button type="submit">Save</button>
</form>
        </div>
    @endvolt
</x-app-layout>
