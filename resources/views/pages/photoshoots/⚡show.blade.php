<?php

use App\Livewire\Forms\ContractForm;
use App\Livewire\Forms\GalleryForm;
use App\Livewire\Forms\PaymentLinkForm;
use App\Livewire\Forms\PhotoshootForm;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Photoshoot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public PaymentLinkForm $paymentLinkForm;

    public Photoshoot $photoshoot;

    public PhotoshootForm $form;

    public GalleryForm $galleryForm;

    public ContractForm $contractForm;

    public ?Collection $templates;

    public ?string $generatedPaymentLink = null;

    #[Computed]
    public function payments()
    {
        return $this->photoshoot->payments()->orderByDesc('completed_at')->get();
    }

    public function mount(Photoshoot $photoshoot)
    {
        $this->authorize('view', $photoshoot);

        $this->photoshoot = $photoshoot;

        $this->form->setPhotoshoot($this->photoshoot);

        $this->galleryForm->setPhotoshoot($this->photoshoot);
        $this->galleryForm->expirationDate = now()->addMonth()->format('Y-m-d');

        $this->contractForm->setPhotoshoot($this->photoshoot);

        $this->templates = Auth::user()?->currentTeam->contractTemplates()->orderBy('title')->get();
    }

    public function update()
    {
        $this->form->update();

        $this->photoshoot = $this->photoshoot->fresh();

        $this->modal('edit')->close();
    }

    public function delete()
    {
        $this->photoshoot->deleteGalleries()->delete();

        $this->redirect(route('photoshoots'));
    }

    public function deletePreservingGalleries()
    {
        $this->photoshoot->delete();

        $this->redirect(route('photoshoots'));
    }

    public function addGallery()
    {
        tap($this->galleryForm->store(), function ($gallery) {
            $this->redirect(route('galleries.show', ['gallery' => $gallery]));
        });
    }

    public function addContract()
    {
        $this->authorize('create', Contract::class);

        tap($this->contractForm->store(), function ($contract) {
            $this->redirect(route('contracts.show', ['contract' => $contract]));
        });
    }

    public function openGeneratePaymentLinkModal()
    {
        $this->paymentLinkForm->setPhotoshoot($this->photoshoot);

        $this->modal('generate-payment-link')->show();
    }

    public function generatePaymentLink()
    {
        $this->generatedPaymentLink = $this->paymentLinkForm->generatePaymentLink();

        $this->modal('generate-payment-link')->close();

        $this->modal('show-payment-link')->show();
    }

    public function useTemplate(ContractTemplate $template)
    {
        $this->authorize('view', $template);

        $this->contractForm->body = $template->formatted_markdown_body;

        $this->modal('templates')->close();
    }

    #[Computed]
    public function customers()
    {
        return Auth::user()->currentTeam
            ->customers()
            ->orderBy('name')
            ->get();
    }
}; ?>

<div>
    <div class="max-lg:hidden">
        <flux:button :href="route('photoshoots')" icon="chevron-left" variant="subtle" inset>
            {{ __('Photoshoots') }}
        </flux:button>
    </div>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
        <div class="max-sm:w-full sm:flex-1">
            <x-heading level="1" size="xl">{{ $photoshoot->name }}</x-heading>
        </div>
        <div class="flex gap-4">
            <flux:dropdown>
                <flux:button icon-trailing="chevron-down" variant="subtle">{{ __('Delete') }}</flux:button>

                <flux:menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="{{ __('Are you sure?') }}"
                        variant="danger"
                    >
                        {{ __('Delete') }}
                    </flux:menu.item>
                    <flux:menu.item
                        wire:click="deletePreservingGalleries"
                        wire:confirm="{{ __('Are you sure?') }}"
                        variant="danger"
                    >
                        {{ __('Delete (keep galleries)') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            <flux:modal.trigger name="edit">
                <flux:button>{{ __('Edit') }}</flux:button>
            </flux:modal.trigger>

            <flux:button.group>
                <flux:modal.trigger name="create-gallery">
                    <flux:button variant="primary">{{ __('Create gallery') }}</flux:button>
                </flux:modal.trigger>
                <flux:dropdown align="end">
                    <flux:button variant="primary" icon="chevron-down"></flux:button>

                    <flux:menu>
                        <flux:modal.trigger name="create-contract">
                            <flux:menu.item>{{ __('Create contract') }}</flux:menu.item>
                        </flux:modal.trigger>
                        <flux:menu.item wire:click="openGeneratePaymentLinkModal">
                            {{ __('Generate Payment Link') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </flux:button.group>
        </div>
    </div>

    <div class="mt-12">
        <flux:heading level="2">{{ __('Summary') }}</flux:heading>
        <flux:separator class="mt-4" />
        <x-description.list>
            @if ($photoshoot->customer)
                <x-description.term>
                    {{ __('Customer') }}
                </x-description.term>
                <x-description.details>
                    <flux:link href="/customers/{{ $photoshoot->customer->id }}">
                        {{ $photoshoot->customer->name }}
                    </flux:link>
                </x-description.details>
            @endif

            <x-description.term>
                {{ __('Date') }}
            </x-description.term>
            <x-description.details>
                {{ $photoshoot->formatted_date }}
            </x-description.details>

            <x-description.term>
                {{ __('Price') }}
            </x-description.term>
            <x-description.details>
                {{ $photoshoot->price }}
            </x-description.details>

            <x-description.term>
                {{ __('Location') }}
            </x-description.term>
            <x-description.details>
                {{ $photoshoot->location }}
            </x-description.details>

            <x-description.term>
                {{ __('Comment') }}
            </x-description.term>
            <x-description.details>
                {{ $photoshoot->comment }}
            </x-description.details>

            @if ($photoshoot->getTotalPhotosCount() > 0)
                <x-description.term>
                    {{ __('Total photos') }}
                </x-description.term>
                <x-description.details>
                    {{ $photoshoot->getTotalPhotosCount() }}
                    {{ $photoshoot->getTotalPhotosCount() === 1 ? __('photo') : __('photos') }} •
                    {{ $photoshoot->getFormattedStorageSize() }} {{ __('total storage') }}
                </x-description.details>
            @endif
        </x-description.list>
    </div>

    @if ($this->payments?->isNotEmpty())
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

    <div class="mt-12">
        <flux:heading level="2">{{ __('Galleries') }}</flux:heading>
        <flux:separator class="mt-4" />
    </div>

    @if ($photoshoot->galleries?->isNotEmpty())
        <div class="mt-12">
            <div
                class="grid grid-flow-dense auto-rows-[263px] grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-x-4 gap-y-6"
            >
                @foreach ($photoshoot->galleries as $gallery)
                    <div
                        class="relative flex overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/10"
                    >
                        <a class="flex w-full" href="/galleries/{{ $gallery->id }}">
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
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <flux:heading size="lg" level="2">{{ __('No galleries') }}</flux:heading>
            <flux:subheading class="mb-6 max-w-72 text-center">
                {{ __('We couldn\'t find any galleries. Create one to get started.') }}
            </flux:subheading>
            <flux:modal.trigger name="create-gallery">
                <flux:button variant="primary">
                    {{ __('Create gallery') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    @endif

    @if ($photoshoot->contracts?->isNotEmpty())
        <x-table class="mt-12">
            <x-table.columns>
                <x-table.column class="w-full">{{ __('Contract') }}</x-table.column>
                <x-table.column>{{ __('Location') }}</x-table.column>
                <x-table.column>{{ __('Shooting date') }}</x-table.column>
                <x-table.column>{{ __('Signatures') }}</x-table.column>
            </x-table.columns>

            <x-table.rows>
                @foreach ($photoshoot->contracts as $contract)
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
                            {{ $contract->formatted_shooting_date }}
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
    @endif

    <flux:modal name="create-gallery" class="w-full sm:max-w-lg">
        <form wire:submit="addGallery" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create a new gallery') }}</flux:heading>
                <flux:subheading>{{ __('Enter your gallery details.') }}</flux:subheading>
            </div>

            <flux:input wire:model="galleryForm.name" :label="__('Gallery name')" type="text" />

            <flux:input
                wire:model="galleryForm.expirationDate"
                :label="__('Expiration date')"
                :badge="Auth::user()?->currentTeam?->subscribed() ? __('Optional') : null"
                type="date"
                :clearable="Auth::user()?->currentTeam?->subscribed()"
            />

            @if (! Auth::user()?->currentTeam?->subscribed())
                <flux:callout icon="bolt" variant="secondary">
                    <flux:callout.heading>{{ __('Subscribe for optional expiration') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Subscribe to make gallery expiration dates optional and clearable.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            <flux:switch
                wire:model="galleryForm.keepOriginalSize"
                :label="__('Keep photos at their original size')"
            />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="create-contract" class="w-full sm:max-w-lg">
        <form wire:submit="addContract" class="-mb-6 space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create a new contract') }}</flux:heading>
                <flux:subheading>{{ __('Enter contract details.') }}</flux:subheading>
            </div>

            <flux:input wire:model="contractForm.title" :label="__('Title')" type="text" />

            <flux:input wire:model="contractForm.description" :label="__('Description')" type="text" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="contractForm.location" :label="__('Location')" type="text" />
                <flux:input wire:model="contractForm.shootingDate" :label="__('Shooting date')" type="date" />
            </div>

            <flux:input
                wire:model="contractForm.signature_quantity"
                :label="__('Signatures required')"
                type="number"
            />

            <flux:field
                class="dark:**:[.trix-button]:bg-white! **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden dark:**:[trix-editor]:border-white/10! dark:**:[trix-editor]:bg-white/10! **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white dark:**:[trix-toolbar]:bg-zinc-800"
            >
                <div data-flux-label class="flex items-center justify-between">
                    <flux:label>{{ __('Terms') }}</flux:label>

                    <flux:modal.trigger name="templates">
                        <flux:button size="sm">{{ __('Use template') }}</flux:button>
                    </flux:modal.trigger>
                </div>

                <trix-editor
                    class="prose prose-sm dark:prose-invert mt-2"
                    x-on:trix-change="$wire.contractForm.body = $event.target.value"
                    input="trix"
                ></trix-editor>

                <input wire:model="contractForm.body" id="trix" type="text" class="hidden" />

                <flux:error name="contractForm.body" />
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

    <flux:modal name="templates" class="w-full sm:max-w-lg">
        <form
            x-data="{ template: '' }"
            x-on:submit.prevent="if (template) $wire.useTemplate(template)"
            class="space-y-6"
        >
            <div>
                <flux:heading size="lg">{{ __('Select template') }}</flux:heading>
                <flux:subheading>
                    {{ __('Select a template to complete contract terms.') }}
                </flux:subheading>
            </div>

            <flux:select x-model="template" :label="__('Template')" :placeholder="__('Select a template')">
                @if ($templates?->isNotEmpty())
                    @foreach ($templates as $template)
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

    <flux:modal name="edit" class="w-full sm:max-w-lg">
        <form wire:submit="update" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit photoshoot') }}</flux:heading>
                <flux:subheading>{{ __('Please enter your photoshoot details.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.name" :label="__('Photoshoot Name')" type="text" />

            @if ($this->customers)
                <flux:select
                    wire:model.live="form.customer"
                    :label="__('Customer')"
                    variant="listbox"
                    searchable
                >
                    <flux:select.option value="">{{ __('New customer') }}</flux:select.option>
                    @foreach ($this->customers as $customer)
                        <flux:select.option value="{{ $customer->id }}">
                            {{ $customer->name }}{{ $customer->email ? " ({$customer->email})" : '' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if (empty($this->form->customer))
                <flux:input wire:model="form.customerName" :label="__('Customer Name')" type="text" />
                <flux:input wire:model="form.customerEmail" :label="__('Customer Email')" type="email" />
            @endif

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
    <flux:modal name="generate-payment-link" class="w-full sm:max-w-lg">
        <form wire:submit="generatePaymentLink" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Generate a New Payment Link') }}</flux:heading>
                <flux:subheading>
                    {{ __('Fill out the details below to generate a payment link you can send to your client.') }}
                </flux:subheading>
            </div>
            <flux:input
                wire:model="paymentLinkForm.amount"
                type="number"
                :label="__('Amount (in :currency)', ['currency' => strtoupper(Auth::user()?->currentTeam?->stripe_currency ?? 'EUR')])"
                required
            />
            <flux:input
                wire:model="paymentLinkForm.description"
                :label="__('Description')"
                type="text"
                required
            />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="show-payment-link" class="w-full sm:max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Your payment link is ready!') }}</flux:heading>
            <flux:subheading>{{ __('Share this link with your client to request payment.') }}</flux:subheading>
            <flux:input icon="link" :value="$generatedPaymentLink" readonly copyable />
        </div>
    </flux:modal>
</div>
@assets
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css" />
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
@endassets
