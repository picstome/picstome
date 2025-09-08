<?php

use App\Livewire\Forms\PhotoshootForm;
use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('photoshoots');

middleware(['auth', 'verified']);

new class extends Component
{
    use WithPagination;

    public PhotoshootForm $form;

    public function save()
    {
        $this->authorize('create', Photoshoot::class);

        $this->form->store();

        $this->modal('create-photoshoot')->close();
    }

    #[Computed]
    public function photoshoots()
    {
        return Auth::user()?->currentTeam
            ->photoshoots()
            ->latest('date')
            ->paginate(25);
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

            @if ($this->photoshoots?->isNotEmpty())
                <x-table id="table" class="mt-8">
                    <x-table.columns>
                        <x-table.column>Name</x-table.column>
                        <x-table.column>Location</x-table.column>
                    </x-table.columns>

                    <x-table.rows>
                        @foreach ($this->photoshoots as $photoshoot)
                            <x-table.row>

                                <x-table.cell variant="strong" class="relative">
                                    <a
                                        href="/photoshoots/{{ $photoshoot->id }}"
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    <p>{{ $photoshoot->name }}</p>
                                    <flux:text>
                                        {{ $photoshoot->customer_name }}

                                        @if ($photoshoot->customer_email)
                                            ({{ $photoshoot->customer_email }})
                                        @endif
                                    </flux:text>
                                </x-table.cell>
                                <x-table.cell class="relative">
                                    <a
                                        href="/photoshoots/{{ $photoshoot->id }}"
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    <p>{{ $photoshoot->date?->format('F j, Y') }}, {{ $photoshoot->location }}</p>
                                </x-table.cell>
                            </x-table.row>
                        @endforeach
                    </x-table.rows>
                </x-table>

                <div x-data
                    x-on:click="
                        let el = $event.target;
                        while (el && el !== $el) {
                            if (el.hasAttribute('wire:click')) {
                                document.getElementById('table')?.scrollIntoView({ behavior: 'smooth' });
                                break;
                            }

                            el = el.parentElement;
                        }"
                class="mt-6">
                    <flux:pagination :paginator="$this->photoshoots" />
                </div>
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
                    <flux:input wire:model="form.customerEmail" :label="__('Customer Email')" type="email" />
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
