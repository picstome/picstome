<?php

use App\Livewire\Forms\CustomerForm;
use App\Livewire\Forms\InvoiceForm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Services\InvoiceIssuanceService;
use App\Services\InvoicePlanLimitService;
use App\Services\InvoiceService;
use App\Traits\FormatsMoney;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use FormatsMoney;

    public InvoiceForm $form;

    public CustomerForm $customerForm;

    public int $step = 1;

    public string $customerSearch = '';

    public bool $editingCustomer = false;

    public function mount(): void
    {
        $this->authorize('create', Invoice::class);

        $settings = InvoiceSetting::firstOrCreate(
            ['team_id' => Auth::user()->currentTeam->id],
            config('invoices.defaults')
        );

        $this->form->setSettings($settings);

        $ulid = request()->get('invoice');

        $invoice = $ulid
            ? Auth::user()->currentTeam->invoices()->where('ulid', $ulid)->first()
            : null;

        if ($invoice?->isDraft()) {
            $this->form->setInvoice($invoice);

            $this->step = $invoice->isRectificative() ? 3 : ($invoice->customer_id ? 2 : 1);
        }

        $this->ensureLineItem();
    }

    /**
     * A new invoice always starts with one line item.
     */
    private function ensureLineItem(): void
    {
        if ($this->form->lineItems === []) {
            $this->form->lineItems = [
                ['id' => (string) Str::uuid(), 'description' => '', 'quantity' => 1, 'unit_price' => 0],
            ];
        }
    }

    /**
     * The clients of the team, flagged when they miss fiscal data.
     */
    #[Computed]
    public function customers()
    {
        $snapshots = app(InvoiceService::class);

        return Auth::user()->currentTeam
            ->customers()
            ->when($this->customerSearch !== '', function ($query) {
                $search = '%'.str_replace('%', '\%', $this->customerSearch).'%';

                $query->where(fn ($query) => $query
                    ->where('name', 'like', $search)
                    ->orWhere('legal_name', 'like', $search)
                    ->orWhere('tax_id', 'like', $search));
            })
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->each(function (Customer $customer) use ($snapshots) {
                $customer->setAttribute(
                    'missing_fiscal_data',
                    $snapshots->missingCustomerFields($snapshots->customerSnapshot($customer)) !== []
                );
            });
    }

    #[Computed]
    public function canIssue(): bool
    {
        return app(InvoicePlanLimitService::class)->canIssue(Auth::user()->currentTeam);
    }

    #[Computed]
    public function settings(): InvoiceSetting
    {
        return Auth::user()->currentTeam->invoiceSetting;
    }

    /**
     * The amounts of the invoice, calculated live.
     *
     * @return array<string, float>
     */
    public function summary(): array
    {
        return $this->form->totals();
    }

    #[Computed]
    public function selectedCustomer(): ?Customer
    {
        if (! $this->form->customer_id) {
            return null;
        }

        return Auth::user()->currentTeam->customers()->find($this->form->customer_id);
    }

    /**
     * Fiscal fields the selected client is still missing.
     *
     * @return list<string>
     */
    #[Computed]
    public function missingCustomerFields(): array
    {
        $customer = $this->selectedCustomer();

        if (! $customer) {
            return [];
        }

        $snapshots = app(InvoiceService::class);

        return $snapshots->missingCustomerFields($snapshots->customerSnapshot($customer));
    }

    public function selectCustomer(int $customerId): void
    {
        $this->form->customer_id = $customerId;

        $this->nextStep();
    }

    public function addCustomer(): void
    {
        $this->editingCustomer = false;

        $this->customerForm->reset();

        $this->modal('customer')->show();
    }

    /**
     * Open the form to complete the fiscal data of the selected client.
     */
    public function editCustomer(): void
    {
        $customer = $this->selectedCustomer();

        if (! $customer) {
            return;
        }

        $this->editingCustomer = true;

        $this->customerForm->setCustomer($customer);

        $this->modal('customer')->show();
    }

    public function saveCustomer(): void
    {
        if ($this->editingCustomer) {
            $this->customerForm->update();
        } else {
            $customer = $this->customerForm->store();

            $this->form->customer_id = $customer->id;
        }

        $this->modal('customer')->close();

        Flux::toast(__('The client details have been saved.'), variant: 'success');

        if (! $this->editingCustomer) {
            $this->nextStep();
        }
    }

    public function addLine(): void
    {
        $this->form->lineItems[] = [
            'id' => (string) Str::uuid(),
            'description' => '',
            'quantity' => 1,
            'unit_price' => 0,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->form->lineItems[$index]);

        $this->form->lineItems = array_values($this->form->lineItems);
    }

    public function nextStep(): void
    {
        match ($this->step) {
            1 => $this->form->validateOnly('customer_id'),
            2 => $this->validateLineItems(),
            default => null,
        };

        $this->step = min($this->step + 1, 3);
    }

    /**
     * Validate every field of the line items, so the step is not left with
     * an incomplete line.
     */
    protected function validateLineItems(): void
    {
        foreach (['lineItems.*.description', 'lineItems.*.quantity', 'lineItems.*.unit_price'] as $field) {
            $this->form->validateOnly($field);
        }
    }

    /**
     * Errors that are not visible in the current step, so the user is told
     * what is missing instead of pressing a button that does nothing.
     *
     * @return list<string>
     */
    #[Computed]
    public function hiddenErrorMessages(): array
    {
        $messages = [];

        foreach ($this->getErrorBag()->getMessages() as $key => $errors) {
            if ($this->isErrorVisible($key)) {
                continue;
            }

            foreach ($errors as $error) {
                $messages[] = $error;
            }
        }

        return array_values(array_unique($messages));
    }

    /**
     * Whether the field of an error is rendered in the current step.
     */
    protected function isErrorVisible(string $key): bool
    {
        // Errors raised by the services are never bound to a field.
        if (! str_starts_with($key, 'form.') && ! str_starts_with($key, 'customerForm.')) {
            return false;
        }

        return match ($this->step) {
            1 => str_starts_with($key, 'form.customer_id') || str_starts_with($key, 'customerForm.'),
            2 => str_starts_with($key, 'form.lineItems'),
            default => ! str_starts_with($key, 'form.lineItems') && ! str_starts_with($key, 'form.customer_id'),
        };
    }

    public function previousStep(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function saveDraft(): void
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->form->persist(Auth::user()->currentTeam);

        $this->form->setInvoice($invoice);

        Flux::toast(__('The invoice has been saved as a draft.'), variant: 'success');
    }

    public function issue(): void
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->form->persist(Auth::user()->currentTeam);

        if (Gate::forUser(Auth::user())->denies('issue', $invoice)) {
            $this->addError('invoice', __('You have reached the monthly invoice limit of your plan.'));

            return;
        }

        app(InvoiceIssuanceService::class)->issue($invoice, Auth::user());

        $this->redirect(route('invoices.show', $invoice), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center gap-2">
        <flux:button
            variant="ghost"
            icon="chevron-left"
            :href="$this->form->invoice ? route('invoices.show', $this->form->invoice) : route('invoices')"
            wire:navigate
            class="-ml-2"
        >
            {{ __('Invoices') }}
        </flux:button>
    </div>

    <x-heading level="1" size="xl">
        {{ $this->form->invoice ? __('Edit invoice') : __('New invoice') }}
    </x-heading>
    <x-subheading>{{ __('Step :step of 3', ['step' => $this->step]) }}</x-subheading>

    @if ($this->form->invoice && ! $this->form->invoice->isRectificative())
        <flux:callout icon="information-circle" variant="outline" class="mt-6">
            <flux:callout.text>
                {{ __('You are editing a draft invoice. It keeps no number until you issue it.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->form->invoice?->isRectificative())
        <flux:callout icon="information-circle" variant="outline" class="mt-6">
            <flux:callout.text>
                {{ __('This is a corrective invoice of :number.', ['number' => $this->form->invoice->rectifiesInvoice?->full_number]) }}
                @if (filled($this->form->invoice->rectification_reason))
                    {{ __('Reason: :reason', ['reason' => $this->form->invoice->rectification_reason]) }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->selectedCustomer() && $this->missingCustomerFields() !== [])
        <flux:callout icon="exclamation-triangle" variant="outline" class="mt-6">
            <flux:callout.heading>{{ __('The client is missing fiscal data') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('A complete invoice needs the legal name, the tax ID and the address of the client.') }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button variant="primary" wire:click="editCustomer">
                    {{ __('Complete the client data') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="mt-8 grid gap-2 sm:grid-cols-3">
        @foreach ([1 => __('Client'), 2 => __('Line items'), 3 => __('Review and issue')] as $index => $label)
            <div @class([
                'rounded-lg border px-3 py-2 text-sm',
                'border-zinc-200 bg-white text-zinc-500 dark:border-white/10 dark:bg-white/5' => $this->step !== $index,
                'border-brand-green/50 bg-brand-green/10 font-medium text-brand-ink dark:border-brand-green/40 dark:bg-brand-green/10 dark:text-white' => $this->step === $index,
            ])>
                <span class="font-medium">{{ $index }}.</span> {{ $label }}
            </div>
        @endforeach
    </div>

    @if ($this->hiddenErrorMessages !== [])
        <flux:callout icon="exclamation-triangle" variant="danger" class="mt-6">
            <flux:callout.text>
                @foreach ($this->hiddenErrorMessages as $hiddenError)
                    <div>{{ $hiddenError }}</div>
                @endforeach
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->step === 1)
        <section class="mt-8">
            <flux:input
                wire:model.live.debounce.300ms="customerSearch"
                :label="__('Search a client')"
                icon="magnifying-glass"
            />

            <div class="mt-4 space-y-2">
                @forelse ($this->customers as $customer)
                    <button
                        type="button"
                        wire:key="customer-{{ $customer->id }}"
                        wire:click="selectCustomer({{ $customer->id }})"
                        class="flex w-full items-center justify-between rounded-lg border border-zinc-200 px-4 py-3 text-left text-sm transition hover:bg-zinc-50 dark:border-white/10 dark:hover:bg-white/5"
                    >
                        <span>
                            <span class="font-medium">{{ $customer->legal_name ?: $customer->name }}</span>
                            @if ($customer->tax_id)
                                <span class="ms-2 text-zinc-500">{{ $customer->tax_id }}</span>
                            @endif
                            @if ($customer->missing_fiscal_data)
                                <flux:badge color="yellow" inset size="sm" class="ms-2">
                                    {{ __('Missing fiscal data') }}
                                </flux:badge>
                            @endif
                        </span>
                        <flux:icon.chevron-right class="size-4 text-zinc-400" />
                    </button>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('No clients found.') }}</p>
                @endforelse
            </div>

            <flux:button variant="primary" class="btn-ink mt-6" icon="plus" wire:click="addCustomer">
                {{ __('Add a new client') }}
            </flux:button>
        </section>
    @elseif ($this->step === 2)
        <section class="mt-8">
            <div class="space-y-3">
                @foreach ($this->form->lineItems as $index => $lineItem)
                    <div wire:key="line-item-{{ $lineItem['id'] ?? $index }}" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs sm:grid-cols-[1fr_6rem_8rem_auto] dark:border-white/10 dark:bg-white/5">
                        <flux:input
                            wire:model="form.lineItems.{{ $index }}.description"
                            :label="__('Description')"
                        />
                        <flux:input
                            wire:model="form.lineItems.{{ $index }}.quantity"
                            :label="__('Qty')"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <flux:input
                            wire:model="form.lineItems.{{ $index }}.unit_price"
                            :label="__('Price')"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <flux:button
                            variant="ghost"
                            icon="trash"
                            class="sm:mt-6"
                            :disabled="count($this->form->lineItems) <= 1"
                            :aria-label="__('Remove line item')"
                            wire:click="removeLine({{ $index }})"
                        />
                    </div>
                @endforeach
            </div>

            <flux:button variant="primary" class="btn-ink mt-4" icon="plus" wire:click="addLine">
                {{ __('Add line item') }}
            </flux:button>
        </section>
    @else
        <section class="mt-8 grid gap-8 lg:grid-cols-2">
            <div class="space-y-4">
                <flux:select
                    wire:model="form.invoice_type"
                    :label="__('Invoice type')"
                    :disabled="$this->form->invoice?->isRectificative() ?? false"
                    :description="$this->form->invoice?->isRectificative() ? __('A corrective invoice keeps the type of the original invoice.') : null"
                >
                    <option value="full">{{ __('Complete invoice') }}</option>
                    <option value="simplified">{{ __('Simplified invoice') }}</option>
                </flux:select>

                <flux:input wire:model="form.issue_date" :label="__('Issue date')" type="date" />
                <flux:input wire:model="form.due_date" :label="__('Due date')" type="date" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="form.vat_percent" :label="__('VAT rate')">
                        @foreach (config('invoices.vat_rates') as $rate)
                            <option value="{{ $rate }}"><x-vat-rate-label :rate="$rate" /></option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model.live.debounce.500ms="form.irpf_percent" :label="__('IRPF withholding')" type="number" step="0.01" min="0" max="100" />
                </div>

                <flux:checkbox
                    wire:model.live="form.apply_irpf"
                    :label="__('Apply IRPF withholding')"
                    :description="__('The withholding is deducted from the amount the client pays.')"
                />

                @if ((float) $this->form->vat_percent === 0.0)
                    <flux:input
                        wire:model.live.debounce.500ms="form.vat_exemption_reason"
                        :label="__('Reason for the exemption')"
                        :description="__('An exempt invoice must state why it has no VAT. For example: «Operación exenta por el artículo 20 de la LIVA».')"
                        placeholder="{{ __('Exempt operation') }}"
                    />
                @endif

                @if ($this->settings->country === 'ES' && $this->settings->currency !== 'EUR')
                    <flux:input
                        wire:model="form.exchange_rate"
                        :label="__('Exchange rate to euros')"
                        type="number"
                        step="0.000001"
                        min="0"
                        :description="__('Used to express the VAT in euros.')"
                    />
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="form.discount_type" :label="__('Discount')">
                        <option value="">{{ __('No discount') }}</option>
                        <option value="percentage">{{ __('Percentage') }}</option>
                        <option value="fixed">{{ __('Fixed amount') }}</option>
                    </flux:select>

                    <flux:input wire:model.live.debounce.500ms="form.discount_amount" :label="__('Discount amount')" type="number" step="0.01" min="0" />
                </div>

                <flux:textarea wire:model="form.payment_terms" :label="__('Payment terms')" rows="2" />
                <flux:textarea wire:model="form.notes" :label="__('Invoice footer')" rows="2" />
            </div>

            <div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
                    <flux:heading level="3" size="sm">{{ __('Summary') }}</flux:heading>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Subtotal') }}</dt>
                            <dd>{{ $this->money($this->summary()['subtotal']) }} {{ $this->settings->currency }}</dd>
                        </div>
                        @if ($this->summary()['discount_total'] > 0)
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">{{ __('Discount') }}</dt>
                                <dd>-{{ $this->money($this->summary()['discount_total']) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Taxable base') }}</dt>
                            <dd>{{ $this->money($this->summary()['base_amount']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('VAT') }}</dt>
                            <dd>{{ $this->money($this->summary()['vat_amount']) }}</dd>
                        </div>
                        @if ($this->form->apply_irpf)
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">{{ __('IRPF withheld') }}</dt>
                                <dd>-{{ $this->money($this->summary()['irpf_amount']) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-white/10">
                            <dt>{{ $this->form->apply_irpf ? __('Amount to pay') : __('Total') }}</dt>
                            <dd>{{ $this->money($this->form->apply_irpf ? $this->summary()['amount_due'] : $this->summary()['total']) }} {{ $this->settings->currency }}</dd>
                        </div>
                    </dl>
                </div>

                @unless ($this->canIssue)
                    <flux:callout icon="exclamation-triangle" variant="outline" class="mt-4">
                        <flux:callout.heading>{{ __('Monthly limit reached') }}</flux:callout.heading>
                        <flux:callout.text>{{ __('Your plan allows one invoice per month. Upgrade to issue more invoices.') }}</flux:callout.text>
                        <x-slot name="actions">
                            <flux:button variant="primary" :href="route('subscribe')">{{ __('Upgrade') }}</flux:button>
                        </x-slot>
                    </flux:callout>
                @endunless
            </div>
        </section>
    @endif

    <div class="mt-8 flex items-center justify-between gap-3">
        <flux:button variant="ghost" wire:click="previousStep" :disabled="$this->step === 1">
            {{ __('Back') }}
        </flux:button>

        <div class="flex items-center gap-3">
            <flux:button variant="primary" class="btn-ink" wire:click="saveDraft">{{ __('Save draft') }}</flux:button>

            @if ($this->step < 3)
                <flux:button variant="primary" wire:click="nextStep">{{ __('Continue') }}</flux:button>
            @else
                <flux:button variant="primary" wire:click="issue" :disabled="! $this->canIssue">
                    {{ __('Issue') }}
                </flux:button>
            @endif
        </div>
    </div>

    <flux:modal name="customer" class="w-full sm:max-w-lg">
        <form wire:submit="saveCustomer" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $this->editingCustomer ? __('Edit client') : __('Add a new client') }}
                </flux:heading>
                <flux:subheading>{{ __('Please enter your client details.') }}</flux:subheading>
            </div>
            <x-customer-form-fields prefix="customerForm" />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
