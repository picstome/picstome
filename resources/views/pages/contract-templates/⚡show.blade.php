<?php

use App\Livewire\Forms\ContractTemplateForm;
use App\Models\ContractTemplate;
use App\Traits\InteractsWithTallStackModals;
use Livewire\Component;

new class extends Component
{
    use InteractsWithTallStackModals;

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
        $this->authorize('update', $this->contractTemplate);

        $this->form->update();

        $this->contractTemplate = $this->contractTemplate->fresh();

        $this->closeModal('edit');
    }

    public function delete()
    {
        $this->authorize('delete', $this->contractTemplate);

        $this->contractTemplate->delete();

        return $this->redirect(route('contract-templates'));
    }
}; ?>

<div>
    <div class="max-lg:hidden">
        <x-ts-button :href="route('contract-templates')" icon="chevron-left" variant="subtle" inset>
            {{ __('Templates') }}
        </x-ts-button>
    </div>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
        <div class="max-sm:w-full sm:flex-1">
            <div class="flex items-center gap-4">
                <x-ts-heading level="1" size="xl">{{ $contractTemplate->title }}</x-ts-heading>

                @if ($contractTemplate->isPublic())
                    <x-ts-badge size="sm">Picstome</x-ts-badge>
                    <x-ts-badge size="sm">{{ strtoupper($contractTemplate->locale ?? '') }}</x-ts-badge>
                @endif
            </div>
        </div>
        <div class="flex gap-4">
            @can('delete', $contractTemplate)
                <x-ts-modal-trigger name="delete-contract-template">
                    <x-ts-button variant="subtle">
                        {{ __('Delete') }}
                    </x-ts-button>
                </x-ts-modal-trigger>
            @endcan

            @can('update', $contractTemplate)
                <x-ts-modal-trigger name="edit">
                    <x-ts-button>{{ __('Edit') }}</x-ts-button>
                </x-ts-modal-trigger>
            @endcan
        </div>
    </div>

    <x-ts-separator class="mt-6 mb-10" />

    @if ($contractTemplate->isPublic())
        <x-ts-callout variant="warning" icon="exclamation-triangle" class="mb-6">
            <x-ts-callout-heading>{{ __('Picstome contract template') }}</x-ts-callout-heading>
            <x-ts-callout-text>
                {{ __('This template is a starting point, not legal advice. We are not lawyers and are not responsible for the contract content. Every country has different laws, so please review and adapt it before use.') }}
            </x-ts-callout-text>
        </x-ts-callout>
    @endif

    <div
        class="prose prose-sm dark:prose-invert lg:mx-auto lg:flex lg:min-h-[1123px] lg:min-w-[794px] lg:flex-col lg:items-stretch lg:justify-start lg:overflow-auto lg:border lg:border-zinc-200 lg:p-6 lg:shadow-sm lg:dark:border-zinc-700"
    >
        {!! $contractTemplate->formatted_markdown_body !!}
    </div>

    <x-ts-modal name="edit" class="w-full max-w-[794px]">
        <form wire:submit="save" class="-mb-6 space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Edit template') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Enter template details.') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="form.title" :label="__('Title')" type="text" />

            @if (auth()->user()?->is_admin)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ts-checkbox
                        wire:model="form.isPublic"
                        :label="__('Public template')"
                        :description="__('Visible to all users.')"
                    />

                    <x-ts-select wire:model="form.locale" :label="__('Template language')">
                        <option value="en">English</option>
                        <option value="es">Español</option>
                    </x-ts-select>
                </div>
            @endif

            <x-ts-field
                class="dark:**:[.trix-button]:bg-white! **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden dark:**:[trix-editor]:border-white/10! dark:**:[trix-editor]:bg-white/10! **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white dark:**:[trix-toolbar]:bg-zinc-800"
            >
                <x-ts-label>{{ __('Terms') }}</x-ts-label>

                <trix-editor
                    input="trix"
                    x-init="$nextTick(() => $el.editor.loadHTML($wire.form.body))"
                    x-on:trix-change="$wire.form.body = $event.target.value"
                    class="prose prose-sm dark:prose-invert mt-2 min-h-96! min-w-full"
                ></trix-editor>

                <input wire:model="form.body" id="trix" type="text" value="{{ $form->body }}" class="hidden" />

                <x-ts-error name="form.body" />
            </x-ts-field>

            <div class="sticky right-0 -bottom-6 left-0 bg-white dark:bg-zinc-800">
                <x-ts-separator />
                <div class="flex py-6">
                    <x-ts-spacer />

                    <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
                </div>
            </div>
        </form>
    </x-ts-modal>

    <x-confirm-modal
        name="delete-contract-template"
        :title="__('Delete template?')"
        :message="__('This action cannot be undone.')"
        method="delete"
    />
</div>

@assets
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css" />
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
@endassets
