<?php

use App\Traits\InteractsWithTallStackModals;
use App\Livewire\Forms\ContractForm;
use App\Livewire\Forms\GalleryForm;
use App\Livewire\Forms\PaymentLinkForm;
use App\Livewire\Forms\PhotoshootForm;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Invoice;
use App\Models\Photoshoot;
use App\Services\InvoiceFromPaymentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use InteractsWithTallStackModals;
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

    /**
     * Create the draft invoice of a payment and open it for review.
     */
    public function createInvoice(int $paymentId)
    {
        $this->authorize('create', Invoice::class);

        $payment = $this->photoshoot->payments()->findOrFail($paymentId);

        $invoice = app(InvoiceFromPaymentService::class)->createFromPayment($payment, Auth::user());

        $this->redirect(route('invoices.create', ['invoice' => $invoice->ulid]), navigate: true);
    }

    public function mount(Photoshoot $photoshoot)
    {
        $this->authorize('view', $photoshoot);

        $this->photoshoot = $photoshoot;

        $this->form->setPhotoshoot($this->photoshoot);

        $this->galleryForm->setPhotoshoot($this->photoshoot);
        $this->galleryForm->expirationDate = now()->addMonth()->format('Y-m-d');
        $this->galleryForm->keepOriginalSize = (bool) (Auth::user()?->galleryDefaults()['keep_original_size'] ?? true);

        $this->contractForm->setPhotoshoot($this->photoshoot);

        $this->templates = Auth::user()?->currentTeam->contractTemplates()->orderBy('title')->get();
    }

    public function update()
    {
        $this->form->update();

        $this->photoshoot = $this->photoshoot->fresh();

        $this->closeModal('edit');
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

        $this->openModal('generate-payment-link');
    }

    public function generatePaymentLink()
    {
        $this->generatedPaymentLink = $this->paymentLinkForm->generatePaymentLink();

        $this->closeModal('generate-payment-link');

        $this->openModal('show-payment-link');
    }

    public function useTemplate(ContractTemplate $template)
    {
        $this->authorize('view', $template);

        $this->contractForm->body = $template->formatted_markdown_body;

        $this->closeModal('templates');

        $this->dispatch('template-selected');
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
        <x-ts-button :href="route('photoshoots')" icon="chevron-left" variant="subtle" inset>
            {{ __('Photoshoots') }}
        </x-ts-button>
    </div>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
        <div class="max-sm:w-full sm:flex-1">
            <x-ts-heading level="1" size="xl">{{ $photoshoot->name }}</x-ts-heading>
        </div>
        <div class="flex gap-4">
            <x-dropdown position="bottom-end">
                <x-slot:action>
                    <x-ts-button icon-trailing="chevron-down" variant="subtle" x-on:click="show = ! show">{{ __('Delete') }}</x-ts-button>
                </x-slot:action>

                <x-dropdown.items
                    :text="__('Delete')"
                    x-on:click="show = false; $tsui.open.modal('delete-photoshoot')"
                    class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                />
                <x-dropdown.items
                    :text="__('Delete (keep galleries)')"
                    x-on:click="show = false; $tsui.open.modal('delete-photoshoot-keeping-galleries')"
                    class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                />
            </x-dropdown>

            <x-ts-modal-trigger name="edit">
                <x-ts-button variant="primary" class="btn-ink">{{ __('Edit') }}</x-ts-button>
            </x-ts-modal-trigger>

            <x-ts-button-group>
                <x-ts-modal-trigger name="create-gallery">
                    <x-ts-button variant="primary" class="rounded-e-none!">{{ __('Create gallery') }}</x-ts-button>
                </x-ts-modal-trigger>
                <x-dropdown position="bottom-end">
                    <x-slot:action>
                        <x-ts-button variant="primary" icon="chevron-down" class="-ms-px rounded-s-none!" x-on:click="show = ! show" />
                    </x-slot:action>

                    <x-dropdown.items
                        :text="__('Create contract')"
                        x-on:click="show = false; $tsui.open.modal('create-contract')"
                    />
                    <x-dropdown.items
                        :text="__('Generate Payment Link')"
                        wire:click="openGeneratePaymentLinkModal"
                    />
                </x-dropdown>
            </x-ts-button-group>
        </div>
    </div>

    <div class="mt-12">
        <x-ts-heading level="2">{{ __('Summary') }}</x-ts-heading>
        <x-ts-separator class="mt-4" />
        <x-description.list>
            @if ($photoshoot->customer)
                <x-description.term>
                    {{ __('Customer') }}
                </x-description.term>
                <x-description.details>
                    <x-ts-link href="/customers/{{ $photoshoot->customer->id }}">
                        {{ $photoshoot->customer->name }}
                    </x-ts-link>
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
        <x-data-table class="mt-12">
            <x-data-table.columns>
                <x-data-table.column>{{ __('Payments') }}</x-data-table.column>
                <x-data-table.column>{{ __('Amount') }}</x-data-table.column>
                <x-data-table.column>{{ __('Payment Date') }}</x-data-table.column>
                <x-data-table.column>{{ __('Customer Email') }}</x-data-table.column>
                <x-data-table.column align="end">{{ __('Actions') }}</x-data-table.column>
            </x-data-table.columns>
            <x-data-table.rows>
                @foreach ($this->payments as $payment)
                    <x-data-table.row>
                        <x-data-table.cell>{{ $payment->description }}</x-data-table.cell>
                        <x-data-table.cell>{{ $payment->formattedAmount }}</x-data-table.cell>
                        <x-data-table.cell>
                            {{ $payment->completed_at ? $payment->completed_at->format('F j, Y H:i') : '-' }}
                        </x-data-table.cell>
                        <x-data-table.cell>{{ $payment->customer_email }}</x-data-table.cell>
                        <x-data-table.cell align="end">
                            @if ($payment->invoice)
                                <x-ts-link
                                    :href="route('invoices.show', $payment->invoice)"
                                    wire:navigate
                                >
                                    {{ $payment->invoice->full_number }}
                                </x-ts-link>
                            @else
                                <x-ts-button
                                    size="sm"
                                    variant="ghost"
                                    icon="document-plus"
                                    :aria-label="__('Create invoice')"
                                    wire:click="createInvoice({{ $payment->id }})"
                                />
                            @endif
                        </x-data-table.cell>
                    </x-data-table.row>
                @endforeach
            </x-data-table.rows>
        </x-data-table>
    @endif

    <div class="mt-12">
        <x-ts-heading level="2">{{ __('Galleries') }}</x-ts-heading>
        <x-ts-separator class="mt-4" />
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
                                src="{{ $gallery->firstImagePhoto?->small_thumbnail_url }}"
                                alt=""
                                class="mx-auto object-contain"
                            />
                        </a>
                        <div
                            class="absolute inset-x-0 bottom-0 flex gap-2 border-t border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <x-ts-heading>{{ $gallery->name }}</x-ts-heading>
                            <x-ts-text>
                                {{ $gallery->photosCount() }}
                                {{ $gallery->photosCount() === 1 ? __('photo') : __('photos') }} •
                                {{ $gallery->getFormattedStorageSize() }} •
                                {{ $gallery->created_at->format('M j, Y') }}
                            </x-ts-text>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="photo" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No galleries') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __('We couldn\'t find any galleries. Create one to get started.') }}
            </x-ts-subheading>
            <x-ts-modal-trigger name="create-gallery">
                <x-ts-button variant="primary">
                    {{ __('Create gallery') }}
                </x-ts-button>
            </x-ts-modal-trigger>
        </div>
    @endif

    @if ($photoshoot->contracts?->isNotEmpty())
        <x-data-table class="mt-12">
            <x-data-table.columns>
                <x-data-table.column class="w-full">{{ __('Contract') }}</x-data-table.column>
                <x-data-table.column>{{ __('Location') }}</x-data-table.column>
                <x-data-table.column>{{ __('Shooting date') }}</x-data-table.column>
                <x-data-table.column>{{ __('Signatures') }}</x-data-table.column>
                <x-data-table.column class="group/right-align">{{ __('Actions') }}</x-data-table.column>
            </x-data-table.columns>

            <x-data-table.rows>
                @foreach ($photoshoot->contracts as $contract)
                    <x-data-table.row>
                        <x-data-table.cell variant="strong" class="relative">
                            <a href="/contracts/{{ $contract->id }}" class="absolute inset-0 focus:outline-hidden"></a>
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
                            <a href="/contracts/{{ $contract->id }}" class="absolute inset-0 focus:outline-hidden"></a>
                            {{ $contract->location }}
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a href="/contracts/{{ $contract->id }}" class="absolute inset-0 focus:outline-hidden"></a>
                            {{ $contract->formatted_shooting_date }}
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a href="/contracts/{{ $contract->id }}" class="absolute inset-0 focus:outline-hidden"></a>
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
    @endif

    <x-ts-modal name="create-gallery" class="w-full sm:max-w-lg">
        <form wire:submit="addGallery" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Create a new gallery') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Enter your gallery details.') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="galleryForm.name" :label="__('Gallery name')" type="text" />

            <x-ts-input
                wire:model="galleryForm.expirationDate"
                :label="__('Expiration date')"
                :badge="Auth::user()?->currentTeam?->subscribed() ? __('Optional') : null"
                type="date"
                :clearable="Auth::user()?->currentTeam?->subscribed()"
            />

            @if (! Auth::user()?->currentTeam?->subscribed())
                <x-ts-callout icon="bolt" variant="secondary">
                    <x-ts-callout-heading>{{ __('Subscribe for optional expiration') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('Subscribe to make gallery expiration dates optional and clearable.') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @endif

            <x-ts-switch
                wire:model="galleryForm.keepOriginalSize"
                :label="__('Keep photos at their original size')"
                :description="__('This setting is shared with the create gallery modal.')"
            />

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="create-contract" class="w-full sm:max-w-lg">
        <form wire:submit="addContract" class="-mb-6 space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Create a new contract') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Enter contract details.') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="contractForm.title" :label="__('Title')" type="text" />

            <x-ts-input wire:model="contractForm.description" :label="__('Description')" type="text" />

            <div class="grid grid-cols-2 gap-4">
                <x-ts-input wire:model="contractForm.location" :label="__('Location')" type="text" />
                <x-ts-input wire:model="contractForm.shootingDate" :label="__('Shooting date')" type="date" />
            </div>

            <x-ts-input wire:model="contractForm.signature_quantity" :label="__('Signatures required')" type="number" />

            <x-ts-field
                class="dark:**:[.trix-button]:bg-white! **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden dark:**:[trix-editor]:border-white/10! dark:**:[trix-editor]:bg-white/10! **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white dark:**:[trix-toolbar]:bg-zinc-800"
            >
                <div class="flex items-center justify-between">
                    <x-ts-label>{{ __('Terms') }}</x-ts-label>

                    <x-ts-modal-trigger name="templates">
                        <x-ts-button size="sm">{{ __('Use template') }}</x-ts-button>
                    </x-ts-modal-trigger>
                </div>

                <trix-editor
                    class="prose prose-sm dark:prose-invert mt-2"
                    x-on:trix-change="$wire.contractForm.body = $event.target.value"
                    x-on:template-selected.window="
                        element = document.querySelector('trix-editor')
                        element.editor.insertHTML($wire.contractForm.body)
                    "
                    input="trix"
                ></trix-editor>

                <input wire:model="contractForm.body" id="trix" type="text" class="hidden" />

                <x-ts-error name="contractForm.body" />
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

    <x-ts-modal name="templates" class="w-full sm:max-w-lg">
        <form
            x-data="{ template: '' }"
            x-on:submit.prevent="if (template) $wire.useTemplate(template)"
            class="space-y-6"
        >
            <div>
                <x-ts-heading size="lg">{{ __('Select template') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('Select a template to complete contract terms.') }}
                </x-ts-subheading>
            </div>

            <x-ts-select x-model="template" :label="__('Template')" :placeholder="__('Select a template')">
                @if ($templates?->isNotEmpty())
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->title }}</option>
                    @endforeach
                @endif
            </x-ts-select>

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Use template') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="edit" class="w-full sm:max-w-lg">
        <form wire:submit="update" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Edit photoshoot') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Please enter your photoshoot details.') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="form.name" :label="__('Photoshoot Name')" type="text" />

            @if ($this->customers)
                <x-ts-select wire:model.live="form.customer" :label="__('Customer')" variant="listbox" searchable>
                    <x-ts-select-option value="">{{ __('New customer') }}</x-ts-select-option>
                    @foreach ($this->customers as $customer)
                        <x-ts-select-option value="{{ $customer->id }}">
                            {{ $customer->name }}{{ $customer->email ? " ({$customer->email})" : '' }}
                        </x-ts-select-option>
                    @endforeach
                </x-ts-select>
            @endif

            @if (empty($this->form->customer))
                <x-ts-input wire:model="form.customerName" :label="__('Customer Name')" type="text" />
                <x-ts-input wire:model="form.customerEmail" :label="__('Customer Email')" type="email" />
            @endif

            <div class="grid grid-cols-2 gap-4">
                <x-ts-input wire:model="form.date" :label="__('Date')" type="date" />
                <x-ts-input wire:model="form.location" :label="__('Location')" type="text" />
            </div>
            <x-ts-input wire:model="form.price" :label="__('Price')" type="text" />
            <x-ts-textarea wire:model="form.comment" :label="__('Comment')" rows="3" />

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>
    <x-ts-modal name="generate-payment-link" class="w-full sm:max-w-lg">
        <form wire:submit="generatePaymentLink" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Generate a New Payment Link') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('Fill out the details below to generate a payment link you can send to your client.') }}
                </x-ts-subheading>
            </div>
            <x-ts-input
                wire:model="paymentLinkForm.amount"
                type="number"
                :label="__('Amount (in :currency)', ['currency' => strtoupper(Auth::user()?->currentTeam?->stripe_currency ?? 'EUR')])"
                required
            />
            <x-ts-input wire:model="paymentLinkForm.description" :label="__('Description')" type="text" required />
            <div class="flex">
                <x-ts-spacer />
                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="show-payment-link" class="w-full sm:max-w-lg">
        <div class="space-y-6">
            <x-ts-heading size="lg">{{ __('Your payment link is ready!') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Share this link with your client to request payment.') }}</x-ts-subheading>
            <x-ts-input icon="link" :value="$generatedPaymentLink" readonly copyable />
        </div>
    </x-ts-modal>

    <x-confirm-modal
        name="delete-photoshoot"
        :title="__('Delete photoshoot?')"
        :message="__('This action cannot be undone.')"
        method="delete"
    />

    <x-confirm-modal
        name="delete-photoshoot-keeping-galleries"
        :title="__('Delete photoshoot but keep galleries?')"
        :message="__('The galleries will be kept, only the photoshoot is deleted.')"
        :confirm-label="__('Delete (keep galleries)')"
        method="deletePreservingGalleries"
    />
</div>
@assets
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css" />
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
@endassets
