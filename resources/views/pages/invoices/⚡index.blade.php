<?php

use App\Livewire\Forms\InvoiceSettingsForm;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Services\InvoiceDuplicationService;
use App\Traits\FormatsMoney;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
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

        $this->modal('invoice-setup')->show();
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

        $this->modal('invoice-setup')->close();

        Flux::toast(__('Your invoicing settings have been saved.'), variant: 'success');
    }

    public function skipSetup(): void
    {
        $this->modal('invoice-setup')->close();
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
            <x-heading level="1" size="xl">{{ __('Invoices') }}</x-heading>
            <x-subheading>{{ __('Create invoices and share them with your clients.') }}</x-subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="primary" class="btn-ink" icon="cog" :href="route('invoices.settings')" wire:navigate>
                {{ __('Invoice settings') }}
            </flux:button>

            <flux:button variant="primary" :href="route('invoices.create')" wire:navigate>
                {{ __('New invoice') }}
            </flux:button>
        </div>
    </div>

    @if ($this->needsSetup)
        <flux:callout icon="information-circle" variant="outline" class="mt-6">
            <flux:callout.heading>{{ __('Complete your invoicing settings') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Add your legal name, tax ID and address before issuing your first invoice.') }}
            </flux:callout.text>
            <x-slot name="actions">
                @if ($this->setupPending)
                    <flux:button variant="primary" wire:click="openSetup">{{ __('Set up now') }}</flux:button>
                @endif

                <flux:button variant="ghost" :href="route('invoices.settings')" wire:navigate>
                    {{ __('Invoice settings') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if ($this->invoices->isNotEmpty())
        <div class="mt-8 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs [&_table]:table-auto [&_td:first-child]:pl-3 [&_td:last-child]:pr-3 [&_th:first-child]:pl-3 [&_th:last-child]:pr-3 dark:border-white/10 dark:bg-white/5">
            <x-table id="table">
                <x-table.columns class="border-b border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/5">
                    <x-table.column>{{ __('Number') }}</x-table.column>
                    <x-table.column class="hidden w-full sm:table-cell">{{ __('Client') }}</x-table.column>
                    <x-table.column class="hidden xl:table-cell">{{ __('Issue date') }}</x-table.column>
                    <x-table.column class="group/right-align">{{ __('Total') }}</x-table.column>
                    <x-table.column class="group/right-align hidden xl:table-cell">{{ __('Status') }}</x-table.column>
                    <x-table.column class="group/right-align">{{ __('Actions') }}</x-table.column>
                </x-table.columns>

                <x-table.rows>
                    @foreach ($this->invoices as $invoice)
                        <x-table.row
                            wire:key="invoice-{{ $invoice->id }}"
                            class="transition hover:bg-zinc-50 dark:hover:bg-white/5"
                        >
                            <x-table.cell variant="strong" class="relative">
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
                                        <flux:badge
                                            size="sm"
                                            :color="match ($invoice->status) {
                                                'issued' => 'green',
                                                'rectified' => 'orange',
                                                default => 'zinc',
                                            }"
                                            inset
                                        >
                                            {{ $invoice->status_label }}
                                        </flux:badge>
                                    </span>
                                </div>
                            </x-table.cell>
                            <x-table.cell variant="muted" class="hidden w-full sm:table-cell">
                                {{ $invoice->customer?->name ?? '—' }}
                            </x-table.cell>
                            <x-table.cell variant="muted" class="hidden xl:table-cell">
                                {{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}
                            </x-table.cell>
                            <x-table.cell variant="strong" class="whitespace-nowrap text-end">
                                {{ $this->money($invoice->total) }}
                                <span class="font-normal text-zinc-500">{{ $invoice->currency }}</span>
                            </x-table.cell>
                            <x-table.cell class="hidden text-end xl:table-cell">
                                <flux:badge
                                    :color="match ($invoice->status) {
                                        'issued' => 'green',
                                        'rectified' => 'orange',
                                        default => 'zinc',
                                    }"
                                    inset
                                >
                                    {{ $invoice->status_label }}
                                </flux:badge>
                            </x-table.cell>
                            <x-table.cell class="whitespace-nowrap text-end">
                                <div class="flex flex-col items-end gap-1 sm:flex-row sm:items-center sm:justify-end">
                                    @if ($invoice->pdf_path)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            class="!px-2"
                                            icon="document-arrow-down"
                                            :href="route('invoices.pdf', $invoice)"
                                            :aria-label="__('Download PDF')"
                                        >
                                            <span class="hidden xl:inline">{{ __('PDF') }}</span>
                                        </flux:button>
                                    @endif

                                    @if (filled($invoice->public_token))
                                        <flux:button
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
                                        </flux:button>
                                    @endif

                                    @if ($invoice->isIssued())
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="document-duplicate"
                                            wire:click="duplicate({{ $invoice->id }})"
                                            :aria-label="__('Duplicate')"
                                        >
                                            <span class="hidden sm:inline">{{ __('Duplicate') }}</span>
                                        </flux:button>
                                    @endif
                                </div>
                            </x-table.cell>
                        </x-table.row>
                    @endforeach
                </x-table.rows>
            </x-table>
        </div>

        <div class="mt-6">
            <flux:pagination :paginator="$this->invoices" />
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <flux:icon.document-text class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <flux:heading size="lg" level="2">{{ __('No invoices') }}</flux:heading>
            <flux:subheading class="mb-6 max-w-72 text-center">
                {{ __('Create your first invoice and share it with your client.') }}
            </flux:subheading>
            <div class="flex items-center gap-3">
                <flux:button variant="primary" class="btn-ink" icon="cog" :href="route('invoices.settings')" wire:navigate>
                    {{ __('Invoice settings') }}
                </flux:button>
                <flux:button variant="primary" :href="route('invoices.create')" wire:navigate>
                    {{ __('New invoice') }}
                </flux:button>
            </div>
        </div>
    @endif

    <flux:modal name="invoice-setup" class="w-full sm:max-w-2xl">
        <form wire:submit="finishSetup" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Set up your invoicing') }}</flux:heading>
                <flux:subheading>{{ __('Step :step of 3', ['step' => $this->setupStep]) }}</flux:subheading>
            </div>

            @php
                $setupErrors = collect($this->getErrorBag()->getMessages())
                    ->filter(fn ($messages, $key) => ! in_array($key, $this->visibleSetupFields(), true))
                    ->flatten();
            @endphp

            @if ($setupErrors->isNotEmpty())
                <flux:callout icon="exclamation-triangle" variant="danger">
                    <flux:callout.text>
                        @foreach ($setupErrors as $setupError)
                            <div>{{ $setupError }}</div>
                        @endforeach
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($this->setupStep === 1)
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.legal_name" :label="__('Legal name')" />
                    <flux:input wire:model="form.tax_id" :label="__('Tax ID')" />
                </div>
            @elseif ($this->setupStep === 2)
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.address" :label="__('Address')" class="sm:col-span-2" />
                    <flux:input wire:model="form.city" :label="__('City')" />
                    <flux:input wire:model="form.state" :label="__('State / Province')" />
                    <flux:input wire:model="form.postal_code" :label="__('Postal code')" />
                    <flux:input
                        wire:model="form.country"
                        :label="__('Country')"
                        :description="__('Two letter country code, for example ES.')"
                        maxlength="2"
                    />
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.currency" :label="__('Currency')">
                        @foreach (config('invoices.currencies') as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="form.vat_percent" :label="__('VAT rate')">
                        @foreach (config('invoices.vat_rates') as $rate)
                            <option value="{{ $rate }}"><x-vat-rate-label :rate="$rate" /></option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="form.irpf_percent" :label="__('IRPF withholding')" type="number" step="0.01" min="0" max="100" />
                    <flux:input wire:model="form.series" :label="__('Series')" />
                    <flux:input wire:model="form.payment_terms" :label="__('Payment terms')" class="sm:col-span-2" />
                    <flux:input wire:model="form.notes" :label="__('Invoice footer')" class="sm:col-span-2" />
                </div>
            @endif

            <div class="flex items-center justify-between gap-3">
                <flux:button type="button" variant="ghost" wire:click="skipSetup">
                    {{ __('Skip for now') }}
                </flux:button>

                <div class="flex items-center gap-3">
                    @if ($this->setupStep > 1)
                        <flux:button type="button" variant="outline" class="btn-outline-brand" wire:click="previousSetupStep">
                            {{ __('Back') }}
                        </flux:button>
                    @endif

                    @if ($this->setupStep < 3)
                        <flux:button type="button" variant="primary" wire:click="nextSetupStep">
                            {{ __('Continue') }}
                        </flux:button>
                    @else
                        <flux:button type="submit" variant="primary">
                            {{ __('Finish') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </form>
    </flux:modal>
</div>
