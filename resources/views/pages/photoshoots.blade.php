<?php

use App\Livewire\Forms\PhotoshootForm;
use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

use function Laravel\Folio\name;

name('photoshoots');

new class extends Component
{
    public PhotoshootForm $form;

    public function save()
    {
        $this->authorize('create', Photoshoot::class);

        $this->form->store();

        $this->modal('create-photoshoot')->close();
    }

    public function with()
    {
        return ['photoshoots' => Auth::user()?->currentTeam->photoshoots];
    }
}; ?>

<x-app-layout>
    @volt('pages.photoshoots')
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Photoshoots') }}</x-heading>
                    <x-subheading>{{ __('View, create, and manage your photoshoots.') }}</x-subheading>
                </div>
                <div>
                    <flux:modal.trigger :name="auth()->check() ? 'create-photoshoot' : 'login'">
                        <flux:button variant="primary">{{ __('Create photoshoot') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            @if ($photoshoots?->isNotEmpty())
                <x-table class="mt-8">
                    <x-table.columns>
                        <x-table.column>Name</x-table.column>
                        <x-table.column>Location</x-table.column>
                    </x-table.columns>

                    <x-table.rows>
                        @foreach ($photoshoots as $photoshoot)
                            <x-table.row>
                                <x-table.cell variant="strong" class="relative">
                                    <a
                                        href="/photoshoots/{{ $photoshoot->id }}"
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    <p>{{ $photoshoot->name }}</p>
                                    <flux:text>{{ $photoshoot->customer_name }}</flux:text>
                                </x-table.cell>
                                <x-table.cell class="relative">
                                    <a
                                        href="/photoshoots/{{ $photoshoot->id }}"
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    <p>{{ $photoshoot->date }}, {{ $photoshoot->location }}</p>
                                </x-table.cell>
                            </x-table.row>
                        @endforeach
                    </x-table.rows>
                </x-table>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.camera class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No photoshoots') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn’t find any photoshoots. Create one to get started.') }}
                    </flux:subheading>
                    <flux:modal.trigger :name="auth()->check() ? 'create-photoshoot' : 'login'">
                        <flux:button variant="primary">
                            {{ __('Create photoshoot') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <flux:modal name="create-photoshoot" class="w-full sm:max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Create a new photoshoot') }}</flux:heading>
                        <flux:subheading>{{ __('Please enter your photoshoot details.') }}</flux:subheading>
                    </div>

                    <flux:input wire:model="form.name" :label="__('Photoshoot Name')" type="text" />
                    <flux:input wire:model="form.customerName" :label="__('Customer Name')" type="text" />
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="form.date" :label="__('Date')" type="date" />
                        <flux:input wire:model="form.location" :label="__('Location')" type="text" />
                    </div>
                    <flux:input wire:model="form.price" :label="__('Price')" type="text" />
                    <flux:textarea wire:model="form.comment" :label="__('Comment')" rows="3" />

                    <div class="flex">
                        <flux:spacer />

                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
