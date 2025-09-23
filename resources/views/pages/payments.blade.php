<?php

use App\Livewire\Forms\PaymentForm;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Facades\App\Services\StripeConnectService;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('payments');
middleware(['auth', 'verified']);

new class extends Component
{
    public array $currencies = [];

    public PaymentForm $form;

    public function mount()
    {
        $this->currencies = StripeConnectService::supportedCurrencies();
    }

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
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Payments') }}</x-heading>
                    <x-subheading>{{ __('View, create, and manage your team payments.') }}</x-subheading>
                </div>
                <div>
                    <flux:modal.trigger :name="auth()->check() ? 'create-payment' : 'login'">
                        <flux:button variant="primary">{{ __('Create payment') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            @if ($this->payments?->isNotEmpty())
                <x-table id="table" class="mt-8">
                    <x-table.columns>
                        <x-table.column>Description</x-table.column>
                        <x-table.column>Amount</x-table.column>
                        <x-table.column>Currency</x-table.column>
                        <x-table.column>Customer Email</x-table.column>
                        <x-table.column>Status</x-table.column>
                        <x-table.column>Created At</x-table.column>
                    </x-table.columns>
                    <x-table.rows>
                        @foreach ($this->payments as $payment)
                            <x-table.row>
                                <x-table.cell variant="strong">{{ $payment->description }}</x-table.cell>
                                <x-table.cell>${{ number_format($payment->amount / 100, 2) }}</x-table.cell>
                                <x-table.cell>{{ strtoupper($payment->currency) }}</x-table.cell>
                                <x-table.cell>{{ $payment->customer_email }}</x-table.cell>
                                <x-table.cell>
                                    @if ($payment->completed_at)
                                        <span class="text-green-600">Paid</span>
                                    @else
                                        <span class="text-yellow-600">Unpaid</span>
                                    @endif
                                </x-table.cell>
                                <x-table.cell>{{ $payment->created_at->format('F j, Y H:i') }}</x-table.cell>
                            </x-table.row>
                        @endforeach
                    </x-table.rows>
                </x-table>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.credit-card class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No payments') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn’t find any payments. Create one to get started.') }}
                    </flux:subheading>
                    <flux:modal.trigger :name="auth()->check() ? 'create-payment' : 'login'">
                        <flux:button variant="primary">
                            {{ __('Create payment') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <flux:modal name="create-payment" class="w-full sm:max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Create a new payment') }}</flux:heading>
                        <flux:subheading>{{ __('Please enter your payment details.') }}</flux:subheading>
                    </div>
                    <flux:input wire:model="form.amount" :label="__('Amount')" mask:dynamic="$money($input)" required />
                    <flux:select wire:model="form.currency" :label="__('Currency')" required>
                        @foreach ($this->currencies as $currency)
                            <flux:select.option value="{{ strtolower($currency) }}">{{ strtoupper($currency) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="form.description" :label="__('Description')" type="text" required />
                    <flux:input wire:model="form.customer_email" :label="__('Customer Email (optional)')" type="email" />
                    <div class="flex">
                        <flux:spacer />
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
