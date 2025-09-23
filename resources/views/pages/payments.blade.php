<?php

use App\Models\Payment;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('payments');
middleware(['auth', 'verified']);

new class extends Component
{
    public $form = [
        'amount' => null,
        'currency' => 'usd',
        'description' => '',
    ];

    public function save()
    {
        $team = Auth::user()->currentTeam;
        $team->payments()->create([
            'amount' => (int) round($this->form['amount'] * 100), // store as cents
            'currency' => $this->form['currency'],
            'description' => $this->form['description'],
        ]);
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
                <input wire:model="form.amount" type="number" step="0.01" placeholder="Amount" />
                <input wire:model="form.currency" type="text" placeholder="Currency" />
                <input wire:model="form.description" type="text" placeholder="Description" />
                <button type="submit">Save</button>
            </form>
        </div>
    @endvolt
</x-app-layout>
