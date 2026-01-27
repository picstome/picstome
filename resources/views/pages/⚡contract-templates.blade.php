<?php

use App\Livewire\Forms\ContractTemplateForm;
use App\Models\ContractTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ContractTemplateForm $form;

    public function save()
    {
        $this->authorize('create', ContractTemplate::class);

        tap($this->form->store(), function ($template) {
            $this->redirect(route('contract-templates.show', ['contractTemplate' => $template]));
        });
    }

    #[Computed]
    public function templates()
    {
        return Auth::user()?->currentTeam
            ->contractTemplates()
            ->orderBy('title')
            ->paginate(25);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-heading level="1" size="xl">{{ __('Contract templates') }}</x-heading>
            <x-subheading>{{ __('View, create, and manage your templates.') }}</x-subheading>
        </div>
        <div>
            <flux:modal.trigger :name="auth()->check() ? 'create-template' : 'login'">
                <flux:button variant="primary">{{ __('Create template') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($this->templates?->isNotEmpty())
        <x-table id="table" class="mt-8">
            <x-table.columns>
                <x-table.column class="w-full">{{ __('Title') }}</x-table.column>
                <x-table.column class="w-full">{{ __('Date modified') }}</x-table.column>
            </x-table.columns>

            <x-table.rows>
                @foreach ($this->templates as $template)
                    <x-table.row>
                        <x-table.cell variant="strong" class="relative">
                            <a
                                href="{{ route('contract-templates.show', ['contractTemplate' => $template]) }}"
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $template->title }}
                        </x-table.cell>

                        <x-table.cell class="relative">
                            <a
                                href="{{ route('contract-templates.show', ['contractTemplate' => $template]) }}"
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $template->formatted_updated_at }}
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
            <flux:pagination :paginator="$this->templates" />
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <flux:icon.clipboard-document-list class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <flux:heading size="lg" level="2">{{ __('No templates') }}</flux:heading>
            <flux:subheading class="mb-6 max-w-72 text-center">
                {{ __("We couldn't find any templates. Create one to get started.") }}
            </flux:subheading>
            <flux:modal.trigger :name="auth()->check() ? 'create-template' : 'login'">
                <flux:button variant="primary">
                    {{ __('Create template') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    @endif

    <flux:modal name="create-template" class="w-full max-w-[794px]">
        <form wire:submit="save" class="-mb-6 space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create a new template') }}</flux:heading>
                <flux:subheading>{{ __('Enter template details.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.title" :label="__('Title')" type="text" />

            <flux:field
                class="dark:**:[.trix-button]:bg-white! **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden dark:**:[trix-editor]:border-white/10! dark:**:[trix-editor]:bg-white/10! **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white dark:**:[trix-toolbar]:bg-zinc-800"
            >
                <flux:label>{{ __('Terms') }}</flux:label>

                <trix-editor
                    class="prose prose-sm dark:prose-invert mt-2 min-h-96! min-w-full"
                    x-on:trix-change="$wire.form.body = $event.target.value"
                ></trix-editor>

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
