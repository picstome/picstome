<?php

use App\Traits\InteractsWithTallStackModals;
use App\Livewire\Forms\PaymentForm;
use App\Livewire\Forms\PaymentLinkForm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceFromPaymentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use InteractsWithTallStackModals;
    use WithPagination;

    public $sortBy = 'completed_at';

    public $sortDirection = 'desc';

    public PaymentLinkForm $linkForm;

    public PaymentForm $paymentForm;

    public ?string $paymentLink = null;

    public ?Payment $selectedPayment = null;

    public bool $onboardingComplete = false;

    public function editPayment(Payment $payment)
    {
        $this->authorize('view', $payment);

        $this->paymentForm->setPayment($payment);

        $this->selectedPayment = $payment;

        $this->openModal('edit-payment');
    }

    public function savePayment()
    {
        $this->authorize('update', $this->selectedPayment);

        $this->paymentForm->update();

        $this->closeModal('edit-payment');

        $this->selectedPayment = null;
    }

    public ?int $pendingPaymentId = null;

    public function requestDeletePayment(int $id): void
    {
        $this->pendingPaymentId = $id;

        $this->openModal('delete-payment');
    }

    public function deletePayment(Payment $payment)
    {
        $this->authorize('delete', $payment);

        $payment->delete();

        $this->closeModal('edit-payment');
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
    }

    #[Computed]
    public function payments()
    {
        return $this->team->payments()
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(25);
    }

    public function mount()
    {
        $this->onboardingComplete = $this->team ? $this->team->hasCompletedOnboarding() : false;
    }

    #[Computed]
    public function photoshoots()
    {
        return $this->team?->photoshoots()->orderBy('created_at', 'desc')->get();
    }

    public function generatePaymentLink()
    {
        $this->paymentLink = $this->linkForm->generatePaymentLink();

        $this->closeModal('generate-payment-link');

        $this->openModal('payment-link');

        $this->linkForm->reset();
    }

    /**
     * Create the draft invoice of a payment and open it for review.
     */
    public function createInvoice(int $paymentId)
    {
        $this->authorize('create', Invoice::class);

        $payment = $this->team->payments()->findOrFail($paymentId);

        $invoice = app(InvoiceFromPaymentService::class)->createFromPayment($payment, Auth::user());

        $this->redirect(route('invoices.create', ['invoice' => $invoice->ulid]), navigate: true);
    }

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }
} ?>

<div>
    @if (! $this->team?->subscribed())
        <x-ts-callout icon="credit-card" variant="secondary" class="mx-auto mt-8 max-w-prose">
            <x-ts-callout-heading>{{ __('Subscribe to enable payments') }}</x-ts-callout-heading>
            <x-ts-callout-text>
                {{ __('The payments feature is available for subscribed users only. Please subscribe to unlock payment links and payment management.') }}
            </x-ts-callout-text>
            <x-slot name="actions">
                <x-ts-button :href="route('subscribe')" variant="primary">
                    {{ __('Subscribe') }}
                </x-ts-button>
            </x-slot>
        </x-ts-callout>
    @else
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-sm:w-full sm:flex-1">
                <x-ts-heading level="1" size="xl">{{ __('Payments') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Generate payment links and manage payments.') }}</x-ts-subheading>
            </div>
            <div class="flex gap-2">
                <x-ts-button :href="route('branding.payments')" variant="primary" class="btn-ink">
                    {{ __('Settings') }}
                </x-ts-button>
                @if ($this->onboardingComplete)
                    <x-ts-modal-trigger :name="auth()->check() ? 'generate-payment-link' : 'login'">
                        <x-ts-button variant="primary">{{ __('Generate Payment Link') }}</x-ts-button>
                    </x-ts-modal-trigger>
                @endif
            </div>
        </div>

        @if ($this->onboardingComplete)
            @if ($this->payments?->count())
                <x-data-table id="table" class="mt-8">
                    <x-data-table.columns>
                        <x-data-table.column>{{ __('Description') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Amount') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Customer Email') }}</x-data-table.column>
                        <x-data-table.column
                            sortable
                            :sorted="$sortBy === 'completed_at'"
                            :direction="$sortDirection"
                            wire:click="sort('completed_at')"
                        >
                            {{ __('Payment Date') }}
                        </x-data-table.column>
                        <x-data-table.column>{{ __('Photoshoot') }}</x-data-table.column>
                    </x-data-table.columns>
                    <x-data-table.rows>
                        @foreach ($this->payments as $payment)
                            <x-data-table.row>
                                <x-data-table.cell variant="strong">{{ $payment->description }}</x-data-table.cell>
                                <x-data-table.cell>{{ $payment->formattedAmount }}</x-data-table.cell>
                                <x-data-table.cell>{{ $payment->customer_email }}</x-data-table.cell>
                                <x-data-table.cell>
                                    {{ $payment->completed_at ? $payment->completed_at->format('F j, Y H:i') : '-' }}
                                </x-data-table.cell>
                                <x-data-table.cell>
                                    @if ($payment->photoshoot)
                                        <x-ts-link href="/photoshoots/{{ $payment->photoshoot->id }}">
                                            {{ $payment->photoshoot->name }}
                                        </x-ts-link>
                                    @else
                                        -
                                    @endif
                                </x-data-table.cell>
                                <x-data-table.cell align="end">
                                    <x-dropdown position="bottom-end">
                                        <x-slot:action>
                                            <x-ts-button variant="ghost" size="sm" icon="ellipsis-horizontal" x-on:click="show = ! show" />
                                        </x-slot:action>

                                        <x-dropdown.items :text="__('Edit')" icon="pencil-square" wire:click="editPayment({{ $payment->id }})" />

                                        @if ($payment->invoice)
                                            <x-dropdown.items
                                                :text="__('View invoice')"
                                                icon="document-text"
                                                :href="route('invoices.show', $payment->invoice)"
                                                navigate
                                            />
                                        @else
                                            <x-dropdown.items
                                                :text="__('Create invoice')"
                                                icon="document-plus"
                                                wire:click="createInvoice({{ $payment->id }})"
                                            />
                                        @endif

                                        <x-dropdown.items
                                            :text="__('Delete')"
                                            icon="trash"
                                            wire:click="requestDeletePayment({{ $payment->id }})"
                                            class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                        />
                                    </x-dropdown>
                                </x-data-table.cell>
                            </x-data-table.row>
                        @endforeach
                    </x-data-table.rows>
                    {{ $this->payments->links() }}
                </x-data-table>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <x-icon name="credit-card" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <x-ts-heading size="lg" level="2">{{ __('No payments') }}</x-ts-heading>
                    <x-ts-subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn’t find any payments. Generate a payment link to request payment from a client.') }}
                    </x-ts-subheading>
                    <x-ts-modal-trigger name="generate-payment-link">
                        <x-ts-button variant="primary">
                            {{ __('Generate Link') }}
                        </x-ts-button>
                    </x-ts-modal-trigger>
                </div>
            @endif

            <x-ts-modal name="generate-payment-link" class="w-full sm:max-w-lg">
                <form
                    wire:submit="generatePaymentLink"
                    class="space-y-6"
                    x-data
                    x-init="
                        $nextTick(() => {
                            if ($wire.linkForm) {
                                $wire.linkForm.timezone =
                                    Intl.DateTimeFormat().resolvedOptions().timeZone
                            }
                        })
                    "
                >
                    <div>
                        <x-ts-heading size="lg">{{ __('Generate a New Payment Link') }}</x-ts-heading>
                        <x-ts-subheading>
                            {{ __('Fill out the details below to generate a payment link you can send to your client.') }}
                        </x-ts-subheading>
                    </div>
                    <x-ts-field>
                        <x-ts-label>{{ __('Amount') }}</x-ts-label>
                        <x-ts-input-group>
                            <x-ts-input wire:model="linkForm.amount" type="number" required />
                            <x-ts-input-group-suffix>
                                {{ strtoupper($this->team?->stripe_currency ?? 'EUR') }}
                            </x-ts-input-group-suffix>
                        </x-ts-input-group>

                    </x-ts-field>
                    <x-ts-input wire:model="linkForm.description" :label="__('Description')" type="text" required />

                    <x-ts-switch wire:model.live="linkForm.booking" :label="__('Booking')" />

                    @if ($linkForm->booking)
                        <div>
                            <x-date wire:model="linkForm.booking_date" :label="__('Date')" />
                            <div class="mt-4 grid grid-cols-2 gap-4">
                                <x-time wire:model="linkForm.booking_start_time" :label="__('Start Time')" />
                                <x-time wire:model="linkForm.booking_end_time" :label="__('End Time')" />
                            </div>
                        </div>
                    @endif

                    <div class="flex">
                        <x-ts-spacer />
                        <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
                    </div>
                </form>
            </x-ts-modal>

            <x-ts-modal name="payment-link" class="w-full sm:max-w-lg">
                <div class="space-y-6">
                    <x-ts-heading size="lg">{{ __('Your payment link is ready!') }}</x-ts-heading>
                    <x-ts-subheading>
                        {{ __('Share this link with your client to request payment.') }}
                    </x-ts-subheading>

                    <x-ts-input icon="link" :value="$this->paymentLink" readonly copyable />
                </div>
            </x-ts-modal>

            <x-ts-modal name="edit-payment" variant="flyout">
                @if ($selectedPayment)
                    <form wire:submit="savePayment" class="space-y-6">
                        <div>
                            <x-ts-heading size="lg">{{ __('Update payment') }}</x-ts-heading>
                            <x-ts-text class="mt-2">
                                {{ __('Make changes to the payment details.') }}
                            </x-ts-text>
                        </div>
                        <x-ts-select wire:model="paymentForm.photoshoot_id" :label="__('Photoshoot')">
                            <x-ts-select-option value="">{{ __('None') }}</x-ts-select-option>
                            @foreach ($this->photoshoots as $photoshoot)
                                <x-ts-select-option value="{{ $photoshoot->id }}">
                                    {{ $photoshoot->name }}
                                </x-ts-select-option>
                            @endforeach
                        </x-ts-select>
                        <x-ts-input
                            :value="$this->selectedPayment->description"
                            :label="__('Description')"
                            type="text"
                            variant="filled"
                            readonly
                        />
                        <x-ts-input
                            :value="$this->selectedPayment->formattedAmount"
                            :label="__('Amount')"
                            variant="filled"
                            readonly
                        />
                        <x-ts-input
                            :value="strtoupper($this->selectedPayment->currency)"
                            :label="__('Currency')"
                            variant="filled"
                            readonly
                        />
                        <x-ts-input
                            :value="$this->selectedPayment->completed_at?->format('Y-m-d H:i:s')"
                            :label="__('Payment Date')"
                            variant="filled"
                            readonly
                        />
                        <x-ts-input
                            :value="$this->selectedPayment->stripe_payment_intent_id"
                            :label="__('Payment Intent ID')"
                            variant="filled"
                            readonly
                        />
                        <div class="flex items-center gap-4">
                            <x-ts-spacer />
                            <x-ts-button type="submit" variant="primary">{{ __('Save changes') }}</x-ts-button>
                        </div>
                    </form>
                @endif
            </x-ts-modal>
        @else
            <x-ts-callout icon="banknotes" variant="secondary" class="mt-8">
                <x-ts-callout-heading>
                    {{ __('Complete Stripe onboarding to accept payments') }}
                </x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('Before you can accept payments, you must complete your Stripe Connect onboarding process.') }}
                </x-ts-callout-text>
                <x-slot name="actions">
                    <x-ts-button :href="route('stripe.connect')" variant="primary">
                        {{ __('Begin Stripe Onboarding') }}
                    </x-ts-button>
                </x-slot>
            </x-ts-callout>
        @endif
    @endif

    <x-confirm-modal
        name="delete-payment"
        :title="__('Delete payment?')"
        :message="__('This action cannot be undone.')"
        :method="$this->pendingPaymentId ? 'deletePayment('.$this->pendingPaymentId.')' : null"
    />
</div>
