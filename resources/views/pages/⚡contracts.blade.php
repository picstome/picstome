<?php

use App\Livewire\Forms\ContractForm;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Traits\InteractsWithTallStackModals;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use InteractsWithTallStackModals;
    use WithPagination;

    public ContractForm $form;

    public bool $usingPublicTemplate = false;

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
        $this->usingPublicTemplate = $template->isPublic();

        $this->closeModal('templates');

        $this->dispatch('template-selected');
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
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            return ContractTemplate::query()->whereRaw('1 = 0')->get();
        }

        return ContractTemplate::query()
            ->availableTo($team)
            ->orderBy('title')
            ->get();
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-ts-heading level="1" size="xl">{{ __('Contracts') }}</x-ts-heading>
            <x-ts-subheading>{{ __('View, create, and manage your contracts.') }}</x-ts-subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ts-button
                :href="route('contract-templates')"
                icon="clipboard-document-list"
                wire:navigate
                variant="primary"
                class="btn-ink"
            >
                {{ __('Templates') }}
            </x-ts-button>
            <x-ts-modal-trigger :name="auth()->check() ? 'create-contract' : 'login'">
                <x-ts-button variant="primary">{{ __('Create contract') }}</x-ts-button>
            </x-ts-modal-trigger>
        </div>
    </div>

    @if ($this->contracts?->isNotEmpty())
        <x-data-table id="table" class="mt-8">
            <x-data-table.columns>
                <x-data-table.column class="w-full">{{ __('Title') }}</x-data-table.column>
                <x-data-table.column>{{ __('Location') }}</x-data-table.column>
                <x-data-table.column>{{ __('Shooting date') }}</x-data-table.column>
                <x-data-table.column>{{ __('Signatures') }}</x-data-table.column>
                <x-data-table.column class="group/right-align">{{ __('Actions') }}</x-data-table.column>
            </x-data-table.columns>

            <x-data-table.rows>
                @foreach ($this->contracts as $contract)
                    <x-data-table.row>
                        <x-data-table.cell variant="strong" class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <div class="flex items-end gap-2">
                                {{ $contract->title }}

                                @if ($contract->isExecuted())
                                    <x-ts-badge color="lime" size="sm">{{ __('Executed') }}</x-ts-badge>
                                @else
                                    <x-ts-badge size="sm">{{ __('Waiting signatures') }}</x-ts-badge>
                                @endif
                            </div>
                            <x-ts-text>{{ $contract->description }}</x-ts-text>
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->location }}
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->formatted_shooting_date }}
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->signatures()->signed()->count() }}/{{ $contract->signatures()->count() }}
                        </x-data-table.cell>
                        <x-data-table.cell class="text-end">
                            @if ($contract->isExecuted() && $contract->pdf_file_path)
                                <x-ts-button
                                    size="sm"
                                    variant="ghost"
                                    icon="document-arrow-down"
                                    :href="route('contracts.download', ['contract' => $contract])"
                                    :aria-label="__('Download contract')"
                                />
                            @endif
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
        >
            <x-ts-pagination :paginator="$this->contracts" />
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="document-text" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No contracts') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __("We couldn't find any contracts. Create one to get started.") }}
            </x-ts-subheading>
            <x-ts-modal-trigger :name="auth()->check() ? 'create-contract' : 'login'">
                <x-ts-button variant="primary">
                    {{ __('Create contract') }}
                </x-ts-button>
            </x-ts-modal-trigger>
        </div>
    @endif

    <x-ts-modal name="create-contract" class="w-full max-w-[794px]">
        <form wire:submit="save" class="-mb-6 space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Create a new contract') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Enter the contract details.') }}</x-ts-subheading>
            </div>

            @cannot('create', App\Models\Contract::class)
                <x-ts-callout icon="bolt" variant="secondary">
                    <x-ts-callout-heading>{{ __('Plan limit reached') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('Your current plan does not support creating more contracts. Upgrade your plan to create additional contracts.') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Upgrade') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @endcannot

            <x-ts-input wire:model="form.title" :label="__('Title')" type="text" />

            <x-ts-input wire:model="form.description" :label="__('Description')" type="text" />

            <div class="grid grid-cols-2 gap-4">
                <x-ts-input wire:model="form.location" :label="__('Location')" type="text" />
                <x-ts-input wire:model="form.shootingDate" :label="__('Shooting date')" type="date" />
            </div>

            <x-ts-input wire:model="form.signature_quantity" :label="__('Signatures required')" type="number" />

            <x-ts-field
                class="dark:**:[.trix-button]:bg-white! **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden dark:**:[trix-editor]:border-white/10! dark:**:[trix-editor]:bg-white/10! **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white dark:**:[trix-toolbar]:bg-zinc-800"
            >
                <div class="flex items-center justify-between">
                    <x-ts-label>{{ __('Terms') }}</x-ts-label>

                    <x-ts-modal-trigger name="templates">
                        <x-ts-button size="sm" variant="primary" class="btn-ink">{{ __('Use template') }}</x-ts-button>
                    </x-ts-modal-trigger>
                </div>

                @if ($this->usingPublicTemplate)
                    <x-ts-callout variant="warning" icon="exclamation-triangle" class="mt-3">
                        <x-ts-callout-text>
                            {{ __('You are using a Picstome template. Review and adapt it before saving. It is not legal advice.') }}
                        </x-ts-callout-text>
                    </x-ts-callout>
                @endif

                <trix-editor
                    class="prose prose-sm dark:prose-invert mt-2 min-h-96! min-w-full"
                    x-on:trix-change="$wire.form.body = $event.target.value"
                    x-on:template-selected.window="
                        element = document.querySelector('trix-editor')
                        element.editor.insertHTML($wire.form.body)
                    "
                    input="trix"
                ></trix-editor>

                <input wire:model="form.body" id="trix" type="text" class="hidden" />

                <x-ts-error name="form.body" />
            </x-ts-field>

            <div class="sticky right-0 -bottom-6 left-0 bg-white dark:bg-zinc-800">
                <x-ts-separator />
                <div class="flex py-6">
                    <x-ts-spacer />
                    <x-ts-button
                        type="submit"
                        variant="primary"
                        :disabled="auth()->user()->cannot('create', App\Models\Contract::class)"
                    >
                        {{ __('Save') }}
                    </x-ts-button>
                </div>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="templates" class="w-full sm:max-w-lg">
        <x-contract-template-picker :templates="$this->templates" />
    </x-ts-modal>
</div>
@assets
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css" />
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
@endassets
