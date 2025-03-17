<?php

use App\Livewire\Forms\ContractTemplateForm;
use App\Models\Contract;
use App\Models\ContractTemplate;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('contract-templates.show');

middleware(['auth', 'can:view,contractTemplate']);

new class extends Component
{
    public ContractTemplate $contractTemplate;

    public ContractTemplateForm $form;

    public function mount()
    {
        $this->form->setContractTemplate($this->contractTemplate);
    }

    public function save()
    {
        $this->form->update();

        $this->contractTemplate = $this->contractTemplate->fresh();

        $this->modal('edit')->close();
    }

    public function delete()
    {
        $this->contractTemplate->delete();

        return $this->redirect('/contract-templates');
    }
}; ?>

<x-app-layout>
    @volt('pages.contract-templates.show')
        <div>
            <div class="max-lg:hidden">
                <flux:button :href="route('contract-templates')" icon="chevron-left" variant="subtle" inset>
                    {{ __('Templates') }}
                </flux:button>
            </div>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
                <div class="max-sm:w-full sm:flex-1">
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $contractTemplate->title }}</x-heading>
                    </div>
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

            <flux:separator class="mt-6 mb-10" />

            <div class="prose prose-sm">
                {!! $contractTemplate->formatted_markdown_body !!}
            </div>

            <flux:modal name="edit" class="w-full sm:max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit template') }}</flux:heading>
                        <flux:subheading>{{ __('Enter the template details.') }}</flux:subheading>
                    </div>

                    <flux:input wire:model="form.title" :label="__('Title')" type="text" />

                    <flux:field
                        class="**:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden"
                    >
                        <flux:label>{{ __('Terms') }}</flux:label>

                        <trix-editor
                            class="prose prose-sm mt-2"
                            x-on:trix-change="$wire.form.body = $event.target.value"
                            input="trix"
                        ></trix-editor>

                        <input wire:model="form.body" id="trix" type="text" value="{{ $form->body }}" class="hidden" />

                        <flux:error name="form.body" />
                    </flux:field>

                    <div class="flex">
                        <flux:spacer />

                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
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
