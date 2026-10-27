<?php

use App\Traits\InteractsWithTallStackModals;
use App\Livewire\Forms\CustomerForm;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use InteractsWithTallStackModals;
    use WithPagination;

    public CustomerForm $form;

    public function save()
    {
        $this->authorize('create', Customer::class);

        $this->form->store();

        $this->closeModal('create-customer');
    }

    #[Computed]
    public function customers()
    {
        return Auth::user()?->currentTeam
            ->customers()
            ->orderBy('name')
            ->paginate(25);
    }

    #[Computed]
    public function birthdaySoonCustomers()
    {
        return Auth::user()?->currentTeam
            ->customers()
            ->get()
            ->filter(fn ($customer) => $customer->isBirthdaySoon())
            ->sortBy(function ($customer) {
                $now = now();
                $thisYearBirthday = $customer->birthdate->copy()->year($now->year);

                if ($thisYearBirthday->lt($now)) {
                    $thisYearBirthday->addYear();
                }

                return $now->diffInDays($thisYearBirthday, false);
            });
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-ts-heading level="1" size="xl">{{ __('Customers') }}</x-ts-heading>
            <x-ts-subheading>{{ __('View, create, and manage your customers.') }}</x-ts-subheading>
        </div>
        <div>
            <x-ts-modal-trigger :name="auth()->check() ? 'create-customer' : 'login'">
                <x-ts-button variant="primary">{{ __('Add customer') }}</x-ts-button>
            </x-ts-modal-trigger>
        </div>
    </div>

    @if ($this->birthdaySoonCustomers?->isNotEmpty())
        <div class="mt-8 mb-8">
            <x-ts-heading level="2">{{ __('Upcoming Birthdays') }}</x-ts-heading>
            <x-ts-separator class="mt-3" />
            <x-data-table>
                <x-data-table.rows>
                    @foreach ($this->birthdaySoonCustomers as $customer)
                        <x-data-table.row>
                            <x-data-table.cell variant="strong" class="relative w-full">
                                <a
                                    href="/customers/{{ $customer->id }}"
                                    wire:navigate
                                    class="absolute inset-0 focus:outline-hidden"
                                ></a>
                                <div class="flex items-center gap-2">
                                    {{ $customer->name }}

                                    @if ($customer->age !== null)
                                        <x-ts-badge color="yellow" inset="top bottom" icon="cake" size="sm">
                                            {{ __('Birthday soon') }} ({{ $customer->age + 1 }})
                                        </x-ts-badge>
                                    @else
                                        <x-ts-badge color="yellow" inset="top bottom" icon="cake" size="sm">
                                            {{ __('Birthday soon') }}
                                        </x-ts-badge>
                                    @endif
                                </div>
                            </x-data-table.cell>
                            <x-data-table.cell class="relative" align="end">
                                <a
                                    href="/customers/{{ $customer->id }}"
                                    wire:navigate
                                    class="absolute inset-0 focus:outline-hidden"
                                ></a>
                                {{ $customer->email }}
                            </x-data-table.cell>
                            <x-data-table.cell class="relative" align="end">
                                <a
                                    href="/customers/{{ $customer->id }}"
                                    wire:navigate
                                    class="absolute inset-0 focus:outline-hidden"
                                ></a>
                                {{ $customer->phone }}
                            </x-data-table.cell>
                            <x-data-table.cell class="relative" align="end">
                                <a
                                    href="/customers/{{ $customer->id }}"
                                    wire:navigate
                                    class="absolute inset-0 focus:outline-hidden"
                                ></a>
                                {{ $customer->formatted_birthdate }}
                            </x-data-table.cell>
                        </x-data-table.row>
                    @endforeach
                </x-data-table.rows>
            </x-data-table>
        </div>
    @endif

    @if ($this->customers?->isNotEmpty())
        <x-data-table id="table" class="mt-8">
            <x-data-table.columns>
                <x-data-table.column>{{ __('Name') }}</x-data-table.column>
                <x-data-table.column>{{ __('Email') }}</x-data-table.column>
                <x-data-table.column>{{ __('Phone') }}</x-data-table.column>
                <x-data-table.column>{{ __('Birthdate') }}</x-data-table.column>
            </x-data-table.columns>

            <x-data-table.rows>
                @foreach ($this->customers as $customer)
                    <x-data-table.row>
                        <x-data-table.cell variant="strong" class="relative">
                            <a
                                href="/customers/{{ $customer->id }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <div class="flex items-center gap-2">
                                {{ $customer->name }}

                                @if ($customer->isBirthdaySoon())
                                    <x-ts-badge color="yellow" inset="top bottom" icon="cake" size="sm">
                                        {{ __('Birthday soon') }} ({{ $customer->age + 1 }})
                                    </x-ts-badge>
                                @endif
                            </div>
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="/customers/{{ $customer->id }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $customer->email }}
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="/customers/{{ $customer->id }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $customer->phone }}
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="/customers/{{ $customer->id }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $customer->formatted_birthdate }}
                        </x-data-table.cell>
                    </x-data-table.row>
                @endforeach
            </x-data-table.rows>
        </x-data-table>

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
            <x-ts-pagination :paginator="$this->customers" />
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="user" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No customers') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __("We couldn't find any customers. Add one to get started.") }}
            </x-ts-subheading>
            <x-ts-modal-trigger :name="auth()->check() ? 'create-customer' : 'login'">
                <x-ts-button variant="primary">
                    {{ __('Add customer') }}
                </x-ts-button>
            </x-ts-modal-trigger>
        </div>
    @endif

    <x-ts-modal name="create-customer" class="w-full sm:max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Add a new customer') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Please enter your customer details.') }}</x-ts-subheading>
            </div>
            <x-customer-form-fields />
            <div class="flex">
                <x-ts-spacer />
                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>
</div>
