<?php

use App\Livewire\Forms\CustomerForm;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Customer $customer;

    public CustomerForm $form;

    public bool $editingNotes = false;

    public ?string $editedNotes = null;

    public function mount()
    {
        $this->authorize('view', $this->customer);

        $this->form->setCustomer($this->customer);
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    public function update()
    {
        $this->form->update();

        $this->customer = $this->customer->fresh();

        $this->modal('edit')->close();
    }

    public function startEditingNotes()
    {
        $this->editedNotes = $this->customer->notes;
        $this->editingNotes = true;
    }

    public function saveNotes()
    {
        $this->customer->notes = $this->editedNotes;
        $this->customer->save();
        $this->editingNotes = false;
        $this->customer = $this->customer->fresh();
    }

    public function cancelEditingNotes()
    {
        $this->editingNotes = false;
    }

    public function delete()
    {
        $this->customer->delete();
        $this->redirect(route('customers'));
    }

    #[Computed]
    public function galleries()
    {
        return $this->customer->photoshoots->flatMap->galleries;
    }

    #[Computed]
    public function contracts()
    {
        return $this->customer->photoshoots->flatMap->contracts;
    }

    #[Computed]
    public function payments()
    {
        return $this->customer->photoshoots->flatMap->payments->sortByDesc('completed_at');
    }

    #[Computed]
    public function stats()
    {
        $photoshoots = $this->customer->photoshoots;
        $galleries = $photoshoots->flatMap->galleries;
        $photos = $galleries->flatMap->photos;
        $payments = $photoshoots->flatMap->payments->whereNotNull('completed_at');
        $totalPayments = $payments->sum('amount');

        return [
            [
                'title' => __('Photoshoots'),
                'value' => $photoshoots->count(),
            ],
            [
                'title' => __('Galleries'),
                'value' => $galleries->count(),
            ],
            [
                'title' => __('Photos'),
                'value' => $photos->count(),
            ],
            [
                'title' => __('Payments'),
                'value' => Cashier::formatAmount(
                    $totalPayments,
                    $this->team->stripe_currency
                ),
            ],
        ];
    }
}; ?>

<div>
    <div class="max-lg:hidden">
        <flux:button :href="route('customers')" icon="chevron-left" variant="subtle" inset wire:navigate>
            {{ __('Customers') }}
        </flux:button>
    </div>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
        <div class="max-sm:w-full sm:flex-1">
            <x-heading level="1" size="xl">{{ $customer->name }}</x-heading>
        </div>
        <div class="flex gap-4">
            <flux:button wire:click="delete" variant="subtle" wire:confirm="{{ __('Are you sure?') }}">
                {{ __('Delete') }}
            </flux:button>
            <flux:modal.trigger name="edit">
                <flux:button>{{ __('Edit') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <div class="mt-6 mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ($this->stats as $stat)
            <div class="relative rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
                <flux:subheading>{{ $stat['title'] }}</flux:subheading>
                <flux:heading size="xl" class="mb-2">{{ $stat['value'] }}</flux:heading>
            </div>
        @endforeach
    </div>

    <div class="mt-12">
        <flux:heading level="2">{{ __('Customer Details') }}</flux:heading>
        <flux:separator class="mt-4" />
        <x-description.list>
            <x-description.term>{{ __('Name') }}</x-description.term>
            <x-description.details>{{ $customer->name }}</x-description.details>
            <x-description.term>{{ __('Email') }}</x-description.term>
            <x-description.details>
                @if ($customer->email)
                    <flux:link href="mailto:{{ $customer->email }}">{{ $customer->email }}</flux:link>
                @else
                    &mdash;
                @endif
            </x-description.details>
            <x-description.term>{{ __('Phone') }}</x-description.term>
            <x-description.details>
                <flux:link
                    href="https://wa.me/{{ $customer->formatted_whatsapp_phone }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ $customer->phone }}
                </flux:link>
            </x-description.details>
            <x-description.term>{{ __('Birthdate') }}</x-description.term>
            <x-description.details>{{ $customer->formatted_birthdate }}</x-description.details>
            <x-description.term>{{ __('Age') }}</x-description.term>
            <x-description.details>
                @if ($customer->age !== null)
                    {{ $customer->age }}
                @else
                    &mdash;
                @endif
            </x-description.details>
            <x-description.term>{{ __('Notes') }}</x-description.term>
            <x-description.details>
                @if ($editingNotes)
                    <div class="flex flex-col gap-2">
                        <flux:field>
                            <flux:textarea wire:model="editedNotes" rows="3" placeholder="{{ __('Notes') }}" />
                            <flux:error name="editedNotes" />
                        </flux:field>
                        <div class="flex gap-2">
                            <flux:button wire:click="saveNotes" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                            <flux:button wire:click="cancelEditingNotes" variant="subtle" size="sm">{{ __('Cancel') }}</flux:button>
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-2">
                        @if (empty($customer->notes))
                            <flux:text>{{ __('No notes yet') }}</flux:text>
                        @else
                            <div class="prose prose-sm dark:prose-invert">{!! $customer->formatted_notes !!}</div>
                        @endif
                        <flux:button wire:click="startEditingNotes" icon="pencil" icon:variant="micro" variant="subtle" size="sm" square class="shrink-0" inset="top" />
                    </div>
                @endif
            </x-description.details>
        </x-description.list>
    </div>

    @if ($customer->photoshoots->isNotEmpty())
        <x-table class="mt-12">
            <x-table.columns>
                <x-table.column>{{ __('Photoshoot') }}</x-table.column>
                <x-table.column>{{ __('Location') }}</x-table.column>
            </x-table.columns>
            <x-table.rows>
                @foreach ($customer->photoshoots as $photoshoot)
                    <x-table.row>
                        <x-table.cell variant="strong" class="relative">
                            <a
                                href="/photoshoots/{{ $photoshoot->id }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <p>{{ $photoshoot->name }}</p>
                        </x-table.cell>
                        <x-table.cell class="relative">
                            <a
                                href="/photoshoots/{{ $photoshoot->id }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <p>{{ $photoshoot->formatted_date }}, {{ $photoshoot->location }}</p>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table.rows>
        </x-table>
    @endif

    @if ($this->galleries->isNotEmpty())
        <div class="mt-12">
            <flux:heading level="2">{{ __('Galleries') }}</flux:heading>
            <flux:separator class="mt-4" />
        </div>

        <div class="mt-12">
            <div
                class="grid grid-flow-dense auto-rows-[263px] grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-x-4 gap-y-6"
            >
                @foreach ($this->galleries as $gallery)
                    <div
                        class="relative flex overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/10"
                    >
                        <a class="flex w-full" href="/galleries/{{ $gallery->id }}" wire:navigate>
                            <img
                                src="{{ $gallery->photos()->first()?->thumbnail_url }}"
                                alt=""
                                class="mx-auto object-contain"
                            />
                        </a>
                        <div
                            class="absolute inset-x-0 bottom-0 flex gap-2 border-t border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <flux:heading>{{ $gallery->name }}</flux:heading>
                            <flux:text>
                                {{ $gallery->photos()->count() }}
                                {{ $gallery->photos()->count() === 1 ? __('photo') : __('photos') }} •
                                {{ $gallery->getFormattedStorageSize() }} •
                                {{ $gallery->created_at->format('M j, Y') }}
                            </flux:text>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->contracts->isNotEmpty())
        <x-table class="mt-12">
            <x-table.columns>
                <x-table.column class="w-full">{{ __('Contract') }}</x-table.column>
                <x-table.column>{{ __('Location') }}</x-table.column>
                <x-table.column>{{ __('Shooting date') }}</x-table.column>
                <x-table.column>{{ __('Signatures') }}</x-table.column>
            </x-table.columns>
            <x-table.rows>
                @foreach ($this->contracts as $contract)
                    <x-table.row>
                        <x-table.cell variant="strong" class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <div class="flex items-end gap-2">
                                {{ $contract->title }}

                                @if ($contract->isExecuted())
                                    <flux:badge color="lime" size="sm">{{ __('Executed') }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ __('Waiting signatures') }}</flux:badge>
                                @endif
                            </div>
                            <flux:text>{{ $contract->description }}</flux:text>
                        </x-table.cell>
                        <x-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->location }}
                        </x-table.cell>
                        <x-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->formatted_shooting_date }}
                        </x-table.cell>
                        <x-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->signatures()->signed()->count() }}/{{ $contract->signatures()->count() }}
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table.rows>
        </x-table>
    @endif

    @if ($this->payments->isNotEmpty())
        <x-table class="mt-12">
            <x-table.columns>
                <x-table.column>{{ __('Payments') }}</x-table.column>
                <x-table.column>{{ __('Amount') }}</x-table.column>
                <x-table.column>{{ __('Payment Date') }}</x-table.column>
                <x-table.column>{{ __('Customer Email') }}</x-table.column>
            </x-table.columns>
            <x-table.rows>
                @foreach ($this->payments as $payment)
                    <x-table.row>
                        <x-table.cell>{{ $payment->description }}</x-table.cell>
                        <x-table.cell>{{ $payment->formattedAmount }}</x-table.cell>
                        <x-table.cell>
                            {{ $payment->completed_at ? $payment->completed_at->format('F j, Y H:i') : '-' }}
                        </x-table.cell>
                        <x-table.cell>{{ $payment->customer_email }}</x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table.rows>
        </x-table>
    @endif

    <flux:modal name="edit" class="w-full sm:max-w-lg">
        <form wire:submit="update" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit customer') }}</flux:heading>
                <flux:subheading>{{ __('Please enter your customer details.') }}</flux:subheading>
            </div>
            <flux:input wire:model="form.name" :label="__('Name')" type="text" />
            <flux:input wire:model="form.email" :label="__('Email')" type="email" :required="false" />
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
