<?php

use App\Livewire\Forms\ContractTemplateForm;
use App\Models\ContractTemplate;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ContractTemplate $contractTemplate;

    public ContractTemplateForm $form;

    public function mount(ContractTemplate $contractTemplate)
    {
        $this->authorize('view', $contractTemplate);

        $this->contractTemplate = $contractTemplate;

        $this->form->setContractTemplate($this->contractTemplate);
    }

    public function save()
    {
        $this->form->update();

        $this->contractTemplate = $this->contractTemplate->fresh();

        Flux::modal('edit')->close();
    }

    public function delete()
    {
        $this->contractTemplate->delete();

        return $this->redirect(route('contract-templates'));
    }
}; ?>

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

    <div
        class="prose prose-sm dark:prose-invert lg:mx-auto lg:flex lg:min-h-[1123px] lg:min-w-[794px] lg:flex-col lg:items-stretch lg:justify-start lg:overflow-auto lg:border lg:border-zinc-200 lg:p-6 lg:shadow-sm lg:dark:border-zinc-700"
    >
        {!! $contractTemplate->formatted_markdown_body !!}
    </div>

    <flux:modal name="edit" class="w-full max-w-[794px]">
        <form wire:submit="save" class="-mb-6 space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit template') }}</flux:heading>
                <flux:subheading>{{ __('Enter template details.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.title" :label="__('Title')" type="text" />

            <flux:field
                class="dark:**:[.trix-button]:bg-white! **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden dark:**:[trix-editor]:border-white/10! dark:**:[trix-editor]:bg-white/10! **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white dark:**:[trix-toolbar]:bg-zinc-800"
            >
                <flux:label>{{ __('Terms') }}</flux:label>

                <trix-editor
                    input="trix"
                    x-init="$nextTick(() => $el.editor.loadHTML($wire.form.body))"
                    x-on:trix-change="$wire.form.body = $event.target.value"
                    class="prose prose-sm dark:prose-invert mt-2 min-h-96! min-w-full"
                ></trix-editor>

                <input wire:model="form.body" id="trix" type="text" value="{{ $form->body }}" class="hidden" />

                <flux:error name="form.body" />
            </flux:field>

            <div class="sticky right-0 -bottom-6 left-0 bg-white dark:bg-zinc-800">
                <flux:separator />
                <div class="flex py-6">
                    <flux:spacer />

                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>

@assets
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css" />
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
@endassets
