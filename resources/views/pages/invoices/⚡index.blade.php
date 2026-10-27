<?php

use App\Traits\InteractsWithTallStackModals;
use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\InvoiceSettingsForm;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Services\InvoiceDuplicationService;
use App\Traits\FormatsMoney;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use InteractsWithTallStackModals;
    use Interactions;
    use FormatsMoney;
    use WithPagination;

    public InvoiceSettingsForm $form;

    public int $setupStep = 1;

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);

        $team = Auth::user()->currentTeam;

        $settings = InvoiceSetting::firstOrCreate(
            ['team_id' => $team->id],
            config('invoices.defaults')
        );

        $this->form->setSettings($settings);
    }

    #[Computed]
    public function invoices()
    {
        return Auth::user()->currentTeam
            ->invoices()
            ->with('customer')
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function settings(): ?InvoiceSetting
    {
        return Auth::user()->currentTeam->invoiceSetting;
    }

    /**
     * Whether the issuer data is still incomplete, so no invoice can be
     * issued yet.
     */
    #[Computed]
    public function needsSetup(): bool
    {
        return ! $this->settings?->hasCompleteIssuerData();
    }

    /**
     * Whether the guided setup was never completed.
     */
    #[Computed]
    public function setupPending(): bool
    {
        return $this->settings?->setup_completed_at === null;
    }

    public function openSetup(): void
    {
        $this->setupStep = 1;

        $this->openModal('invoice-setup');
    }

    public function nextSetupStep(): void
    {
        match ($this->setupStep) {
            1 => $this->validateSetupFields(['legal_name', 'tax_id']),
            2 => $this->validateSetupFields(['address', 'city', 'postal_code', 'country']),
            default => null,
        };

        $this->setupStep = min($this->setupStep + 1, 3);
    }

    public function previousSetupStep(): void
    {
        $this->setupStep = max($this->setupStep - 1, 1);
    }

    public function finishSetup(): void
    {
        $this->form->completeSetup();

        $this->closeModal('invoice-setup');

        $this->toast()->success(__('Your invoicing settings have been saved.'))->send();
    }

    public function skipSetup(): void
    {
        $this->closeModal('invoice-setup');
    }

    /**
     * Copy an issued invoice into a new draft.
     */
    public function duplicate(int $invoiceId)
    {
        $this->authorize('create', Invoice::class);

        $invoice = Auth::user()->currentTeam->invoices()->findOrFail($invoiceId);

        $draft = app(InvoiceDuplicationService::class)->duplicate($invoice, Auth::user());

        $this->redirect(route('invoices.create', ['invoice' => $draft->ulid]), navigate: true);
    }

    /**
     * Validate the fields of a step of the guided setup.
     *
     * @param  list<string>  $fields
     */
    protected function validateSetupFields(array $fields): void
    {
        foreach ($fields as $field) {
            $this->form->validateOnly($field);
        }
    }

    /**
     * The setup fields that are rendered in the current step.
     *
     * @return list<string>
     */
    public function visibleSetupFields(): array
    {
        return match ($this->setupStep) {
            1 => ['form.legal_name', 'form.tax_id'],
            2 => ['form.address', 'form.city', 'form.postal_code', 'form.country'],
            default => [
                'form.currency',
                'form.vat_percent',
                'form.irpf_percent',
                'form.series',
                'form.rectification_series',
                'form.payment_terms',
                'form.notes',
            ],
        };
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-ts-heading level="1" size="xl">{{ __('Invoices') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Create invoices and share them with your clients.') }}</x-ts-subheading>
        </div>
        <div class="flex items-center gap-2">
            <x-ts-button variant="primary" class="btn-ink" icon="cog" :href="route('invoices.settings')" wire:navigate>
                {{ __('Invoice settings') }}
            </x-ts-button>

            <x-ts-button variant="primary" :href="route('invoices.create')" wire:navigate>
                {{ __('New invoice') }}
            </x-ts-button>
        </div>
    </div>

    @if ($this->needsSetup)
        <x-ts-callout icon="information-circle" variant="outline" class="mt-6">
            <x-ts-callout-heading>{{ __('Complete your invoicing settings') }}</x-ts-callout-heading>
            <x-ts-callout-text>
                {{ __('Add your legal name, tax ID and address before issuing your first invoice.') }}
            </x-ts-callout-text>
            <x-slot name="actions">
                @if ($this->setupPending)
                    <x-ts-button variant="primary" wire:click="openSetup">{{ __('Set up now') }}</x-ts-button>
                @endif

                <x-ts-button variant="ghost" :href="route('invoices.settings')" wire:navigate>
                    {{ __('Invoice settings') }}
                </x-ts-button>
            </x-slot>
        </x-ts-callout>
    @endif

    @if ($this->invoices->isNotEmpty())
        <div class="mt-8 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs [&_table]:table-auto [&_td:first-child]:pl-3 [&_td:last-child]:pr-3 [&_th:first-child]:pl-3 [&_th:last-child]:pr-3 dark:border-white/10 dark:bg-white/5">
            <x-data-table id="table">
                <x-data-table.columns class="border-b border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/5">
                    <x-data-table.column>{{ __('Number') }}</x-data-table.column>
                    <x-data-table.column class="hidden w-full sm:table-cell">{{ __('Client') }}</x-data-table.column>
                    <x-data-table.column class="hidden xl:table-cell">{{ __('Issue date') }}</x-data-table.column>
                    <x-data-table.column class="group/right-align">{{ __('Total') }}</x-data-table.column>
                    <x-data-table.column class="group/right-align hidden xl:table-cell">{{ __('Status') }}</x-data-table.column>
                    <x-data-table.column class="group/right-align">{{ __('Actions') }}</x-data-table.column>
                </x-data-table.columns>

                <x-data-table.rows>
                    @foreach ($this->invoices as $invoice)
                        <x-data-table.row
                            wire:key="invoice-{{ $invoice->id }}"
                            class="transition hover:bg-zinc-50 dark:hover:bg-white/5"
                        >
                            <x-data-table.cell variant="strong" class="relative">
                                <a
                                    href="{{ route('invoices.show', $invoice) }}"
                                    wire:navigate
                                    class="absolute inset-0 focus:outline-hidden"
                                ></a>
                                <div class="flex flex-col">
                                    <span @class(['font-mono' => filled($invoice->full_number)])>
                                        {{ $invoice->full_number ?? __('Draft') }}
                                    </span>
                                    <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-normal text-zinc-500 xl:hidden">
                                        <span class="sm:hidden">{{ $invoice->customer?->name }}</span>
                                        @if ($invoice->issue_date)
                                            <span class="xl:hidden">· {{ $invoice->issue_date->format('Y-m-d') }}</span>
                                        @endif
                                        <x-ts-badge
                                            size="sm"
                                            :color="match ($invoice->status) {
                                                'issued' => 'green',
                                                'rectified' => 'orange',
                                                default => 'zinc',
                                            }"
                                            inset
                                        >
                                            {{ $invoice->status_label }}
                                        </x-ts-badge>
                                    </span>
                                </div>
                            </x-data-table.cell>
                            <x-data-table.cell variant="muted" class="hidden w-full sm:table-cell">
                                {{ $invoice->customer?->name ?? '—' }}
                            </x-data-table.cell>
                            <x-data-table.cell variant="muted" class="hidden xl:table-cell">
                                {{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}
                            </x-data-table.cell>
                            <x-data-table.cell variant="strong" class="whitespace-nowrap text-end">
                                {{ $this->money($invoice->total) }}
                                <span class="font-normal text-zinc-500">{{ $invoice->currency }}</span>
                            </x-data-table.cell>
                            <x-data-table.cell class="hidden text-end xl:table-cell">
                                <x-ts-badge
                                    :color="match ($invoice->status) {
                                        'issued' => 'green',
                                        'rectified' => 'orange',
                                        default => 'zinc',
                                    }"
                                    inset
                                >
                                    {{ $invoice->status_label }}
                                </x-ts-badge>
                            </x-data-table.cell>
                            <x-data-table.cell class="whitespace-nowrap text-end">
                                <div class="flex flex-col items-end gap-1 sm:flex-row sm:items-center sm:justify-end">
                                    @if ($invoice->pdf_path)
                                        <x-ts-button
                                            size="sm"
                                            variant="ghost"
                                            class="!px-2"
                                            icon="document-arrow-down"
                                            :href="route('invoices.pdf', $invoice)"
                                            :aria-label="__('Download PDF')"
                                        >
                                            <span class="hidden xl:inline">{{ __('PDF') }}</span>
                                        </x-ts-button>
                                    @endif

                                    @if (filled($invoice->public_token))
                                        <x-ts-button
                                            size="sm"
                                            variant="ghost"
                                            class="!px-2"
                                            icon="arrow-top-right-on-square"
                                            :href="route('invoices.public.show', $invoice->public_token)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            :aria-label="__('View invoice')"
                                        >
                                            <span class="hidden xl:inline">{{ __('View invoice') }}</span>
                                        </x-ts-button>
                                    @endif

                                    @if ($invoice->isIssued())
                                        <x-ts-button
                                            size="sm"
                                            variant="ghost"
                                            icon="document-duplicate"
                                            wire:click="duplicate({{ $invoice->id }})"
                                            :aria-label="__('Duplicate')"
                                        >
                                            <span class="hidden sm:inline">{{ __('Duplicate') }}</span>
                                        </x-ts-button>
                                    @endif
                                </div>
                            </x-data-table.cell>
                        </x-data-table.row>
                    @endforeach
                </x-data-table.rows>
            </x-data-table>
        </div>

        <div class="mt-6">
            <x-ts-pagination :paginator="$this->invoices" />
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="document-text" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No invoices') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __('Create your first invoice and share it with your client.') }}
            </x-ts-subheading>
            <div class="flex items-center gap-3">
                <x-ts-button variant="primary" class="btn-ink" icon="cog" :href="route('invoices.settings')" wire:navigate>
                    {{ __('Invoice settings') }}
                </x-ts-button>
                <x-ts-button variant="primary" :href="route('invoices.create')" wire:navigate>
                    {{ __('New invoice') }}
                </x-ts-button>
            </div>
        </div>
    @endif

    <x-ts-modal name="invoice-setup" class="w-full sm:max-w-2xl">
        <form wire:submit="finishSetup" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Set up your invoicing') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Step :step of 3', ['step' => $this->setupStep]) }}</x-ts-subheading>
            </div>

            @php
                $setupErrors = collect($this->getErrorBag()->getMessages())
                    ->filter(fn ($messages, $key) => ! in_array($key, $this->visibleSetupFields(), true))
                    ->flatten();
            @endphp

            @if ($setupErrors->isNotEmpty())
                <x-ts-callout icon="exclamation-triangle" variant="danger">
                    <x-ts-callout-text>
                        @foreach ($setupErrors as $setupError)
                            <div>{{ $setupError }}</div>
                        @endforeach
                    </x-ts-callout-text>
                </x-ts-callout>
            @endif

            @if ($this->setupStep === 1)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ts-input wire:model="form.legal_name" :label="__('Legal name')" />
                    <x-ts-input wire:model="form.tax_id" :label="__('Tax ID')" />
                </div>
            @elseif ($this->setupStep === 2)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ts-input wire:model="form.address" :label="__('Address')" class="sm:col-span-2" />
                    <x-ts-input wire:model="form.city" :label="__('City')" />
                    <x-ts-input wire:model="form.state" :label="__('State / Province')" />
                    <x-ts-input wire:model="form.postal_code" :label="__('Postal code')" />
                    <x-ts-input
                        wire:model="form.country"
                        :label="__('Country')"
                        :description="__('Two letter country code, for example ES.')"
                        maxlength="2"
                    />
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ts-select wire:model="form.currency" :label="__('Currency')">
                        @foreach (config('invoices.currencies') as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </x-ts-select>
                    <x-ts-select wire:model="form.vat_percent" :label="__('VAT rate')">
                        @foreach (config('invoices.vat_rates') as $rate)
                            <option value="{{ $rate }}"><x-vat-rate-label :rate="$rate" /></option>
                        @endforeach
                    </x-ts-select>
                    <x-ts-input wire:model="form.irpf_percent" :label="__('IRPF withholding')" type="number" step="0.01" min="0" max="100" />
                    <x-ts-input wire:model="form.series" :label="__('Series')" />
                    <x-ts-input wire:model="form.payment_terms" :label="__('Payment terms')" class="sm:col-span-2" />
                    <x-ts-input wire:model="form.notes" :label="__('Invoice footer')" class="sm:col-span-2" />
                </div>
            @endif

            <div class="flex items-center justify-between gap-3">
                <x-ts-button type="button" variant="ghost" wire:click="skipSetup">
                    {{ __('Skip for now') }}
                </x-ts-button>

                <div class="flex items-center gap-3">
                    @if ($this->setupStep > 1)
                        <x-ts-button type="button" variant="outline" class="btn-outline-brand" wire:click="previousSetupStep">
                            {{ __('Back') }}
                        </x-ts-button>
                    @endif

                    @if ($this->setupStep < 3)
                        <x-ts-button type="button" variant="primary" wire:click="nextSetupStep">
                            {{ __('Continue') }}
                        </x-ts-button>
                    @else
                        <x-ts-button type="submit" variant="primary">
                            {{ __('Finish') }}
                        </x-ts-button>
                    @endif
                </div>
            </div>
        </form>
    </x-ts-modal>
</div>
