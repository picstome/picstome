<?php

use App\Livewire\Forms\CustomerForm;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('customers');

middleware(['auth', 'verified']);

new class extends Component
{
    use WithPagination;

    public CustomerForm $form;

    public function save()
    {
        $this->authorize('create', Customer::class);

        $this->form->store();

        $this->modal('create-customer')->close();
    }

    #[Computed]
    public function customers()
    {
        return Auth::user()?->currentTeam
            ->customers()
            ->latest('created_at')
            ->paginate(25);
    }
}; ?>

<x-app-layout>
    @volt('pages.customers')
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Customers') }}</x-heading>
                    <x-subheading>{{ __('View, create, and manage your customers.') }}</x-subheading>
                </div>
                <div>
                    <flux:modal.trigger :name="auth()->check() ? 'create-customer' : 'login'">
                        <flux:button variant="primary">{{ __('Add customer') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            @if ($this->customers?->isNotEmpty())
                <x-table id="table" class="mt-8">
                    <x-table.columns>
                        <x-table.column>Name</x-table.column>
                        <x-table.column>Email</x-table.column>
                        <x-table.column>Phone</x-table.column>
                        <x-table.column>Birthdate</x-table.column>
                    </x-table.columns>

                    <x-table.rows>
                        @foreach ($this->customers as $customer)
                            <x-table.row>
                                <x-table.cell variant="strong" class="relative">
                                    <a
                                        href="/customers/{{ $customer->id }}"
                                        wire:navigate
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    <p>{{ $customer->name }}</p>
                                </x-table.cell>
                                <x-table.cell class="relative">
                                    <a
                                        href="/customers/{{ $customer->id }}"
                                        wire:navigate
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    {{ $customer->email }}
                                </x-table.cell>
                                <x-table.cell class="relative">
                                    <a
                                        href="/customers/{{ $customer->id }}"
                                        wire:navigate
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    {{ $customer->phone }}
                                </x-table.cell>
                                <x-table.cell class="relative">
                                    <a
                                        href="/customers/{{ $customer->id }}"
                                        wire:navigate
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    {{ $customer->formatted_birthdate }}
                                </x-table.cell>
                            </x-table.row>
                        @endforeach
                    </x-table.rows>
                </x-table>

                <div
                    x-data
                    x-on:click="
                        let el = $event.target
                        while (el && el !== $el) {
                            if (el.hasAttribute('wire:click')) {
                                document.getElementById('table')?.scrollIntoView({ behavior: 'smooth' })
                                break
                            }
                            el = el.parentElement
                        }
                    "
                    class="mt-6"
                >
                    <flux:pagination :paginator="$this->customers" />
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.user class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No customers') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn’t find any customers. Add one to get started.') }}
                    </flux:subheading>
                    <flux:modal.trigger :name="auth()->check() ? 'create-customer' : 'login'">
                        <flux:button variant="primary">
                            {{ __('Add customer') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <flux:modal name="create-customer" class="w-full sm:max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add a new customer') }}</flux:heading>
                        <flux:subheading>{{ __('Please enter your customer details.') }}</flux:subheading>
                    </div>
                    <flux:input wire:model="form.name" :label="__('Name')" type="text" />
                    <flux:input wire:model="form.email" :label="__('Email')" type="email" />
                    <flux:input wire:model="form.phone" :label="__('Phone')" type="text" />
                    <flux:input wire:model="form.birthdate" :label="__('Birthdate')" type="date" />
                    <flux:textarea wire:model="form.notes" :label="__('Notes')" rows="3" />
                    <div class="flex">
                        <flux:spacer />
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
