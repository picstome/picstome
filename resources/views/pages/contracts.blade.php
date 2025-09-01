<?php

use App\Livewire\Forms\ContractForm;
use App\Models\Contract;
use App\Models\ContractTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('contracts');
middleware(['auth', 'verified']);

new class extends Component
{
    use WithPagination;

    public ContractForm $form;

    public function save()
    {
        $this->authorize('create', Contract::class);

        tap($this->form->store(), function ($contract) {
            $this->redirect(route('contracts.show', ['contract' => $contract]));
        });
    }

    public function useTemplate(ContractTemplate $template)
    {
        $this->authorize('view', $template);

        $this->form->body = $template->formatted_markdown_body;

        $this->modal('templates')->close();
    }

    #[Computed]
    public function contracts()
    {
        return Auth::user()?->currentTeam
            ->contracts()
            ->latest()
            ->paginate(25);
    }

    #[Computed]
    public function templates()
    {
        return Auth::user()?->currentTeam
            ->contractTemplates()
            ->orderBy('title')
            ->get();
    }
}; ?>

<x-app-layout>
    @volt('pages.contracts')
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Contracts') }}</x-heading>
                    <x-subheading>{{ __('View, create, and manage your contracts.') }}</x-subheading>
                </div>
                <div>
                    <flux:modal.trigger :name="auth()->check() ? 'create-contract' : 'login'">
                        <flux:button variant="primary">{{ __('Create contract') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            @if ($this->contracts?->isNotEmpty())
                <x-table id="table" class="mt-8">
                    <x-table.columns>
                        <x-table.column class="w-full">{{ __('Title') }}</x-table.column>
                        <x-table.column>{{ __('Location') }}</x-table.column>
                        <x-table.column>{{ __('Shooting date') }}</x-table.column>
                        <x-table.column>{{ __('Signatures') }}</x-table.column>
                    </x-table.columns>

                    <x-table.rows>
                        @foreach ($this->contracts as $contract)
                            <x-table.row>
                                <x-table.cell variant="strong" class="relative">
                                    <a
                                        href="/contracts/{{ $contract->id }}"
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
                                        href="/contracts/{{ $contract->id }}"
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    {{ $contract->location }}
                                </x-table.cell>
                                <x-table.cell class="relative">
                                    <a
                                        href="/contracts/{{ $contract->id }}"
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    {{ $contract->shooting_date }}
                                </x-table.cell>
                                <x-table.cell class="relative">
                                    <a
                                        href="/contracts/{{ $contract->id }}"
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>
                                    {{ $contract->signatures()->signed()->count() }}/{{ $contract->signatures()->count() }}
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
                        }">
                    <flux:pagination :paginator="$this->contracts" />
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.document-text class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No contracts') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn’t find any contracts. Create one to get started.') }}
                    </flux:subheading>
                    <flux:modal.trigger :name="auth()->check() ? 'create-contract' : 'login'">
                        <flux:button variant="primary">
                            {{ __('Create contract') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <flux:modal name="create-contract" class="w-full sm:max-w-lg">
                <form wire:submit="save" class="-mb-6 space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Create a new contract') }}</flux:heading>
                        <flux:subheading>{{ __('Enter the contract details.') }}</flux:subheading>
                    </div>

                    @cannot('create', App\Models\Contract::class)
                        <flux:callout icon="bolt" variant="secondary">
                            <flux:callout.heading>{{ __('Plan limit reached') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ __('Your current plan does not support creating more contracts. Upgrade your plan to create additional contracts.') }}
                            </flux:callout.text>
                            <x-slot name="actions">
                                <flux:button :href="route('subscribe')" variant="primary">{{ __('Upgrade') }}</flux:button>
                            </x-slot>
                        </flux:callout>
                    @endcannot

                    <flux:input wire:model="form.title" :label="__('Title')" type="text" />

                    <flux:input wire:model="form.description" :label="__('Description')" type="text" />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="form.location" :label="__('Location')" type="text" />
                        <flux:input wire:model="form.shootingDate" :label="__('Shooting date')" type="date" />
                    </div>

                    <flux:input wire:model="form.signature_quantity" :label="__('Signatures required')" type="number" />

                    <flux:field class="
                        **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white
                        **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden"
                    >
                        <div data-flux-label class="flex items-center justify-between">
                            <flux:label>{{ __('Terms') }}</flux:label>

                            <flux:modal.trigger name="templates">
                                <flux:button size="sm">{{ __('Use template') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        <trix-editor
                            class="prose prose-sm mt-2"
                            x-on:trix-change="$wire.form.body = $event.target.value"
                            input="trix"
                        ></trix-editor>

                        <input wire:model="form.body" id="trix" type="text" class="hidden" />

                        <flux:error name="form.body" />
                    </flux:field>

                    <div class="sticky right-0 -bottom-6 left-0 bg-white">
                        <flux:separator />
                        <div class="flex py-6">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary" :disabled="auth()->user()->cannot('create', App\Models\Contract::class)">{{ __('Save') }}</flux:button>
                        </div>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="templates" class="w-full sm:max-w-lg">
                <form
                    x-data="{ template: '' }"
                    x-on:submit.prevent="if (template) $wire.useTemplate(template)"
                    class="space-y-6"
                >
                    <div>
                        <flux:heading size="lg">{{ __('Select template') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Select a template to complete the contract terms.') }}
                        </flux:subheading>
                    </div>

                    <flux:select x-model="template" :label="__('Template')" :placeholder="__('Select a template')">
                        @if ($this->templates?->isNotEmpty())
                            @foreach ($this->templates as $template)
                                <option value="{{ $template->id }}">{{ $template->title }}</option>
                            @endforeach
                        @endif
                    </flux:select>

                    <div class="flex">
                        <flux:spacer />

                        <flux:button type="submit" variant="primary">{{ __('Use template') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </div>
        @assets
            <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css" />
            <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
        @endassets
    @endvolt
</x-app-layout>
