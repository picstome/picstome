<?php

use App\Livewire\Forms\PaymentLinkForm;
use App\Livewire\Forms\PaymentForm;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Facades\App\Services\StripeConnectService;
use Flux\Flux;
use Livewire\WithPagination;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('payments');
middleware(['auth', 'verified']);

new class extends Component
{
    use WithPagination;

    public $sortBy = 'completed_at';

    public $sortDirection = 'desc';

    public array $currencies = [];

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

        Flux::modal('edit-payment')->show();
    }

    public function savePayment()
    {
        $this->authorize('update', $this->selectedPayment);

        $this->paymentForm->update();

        Flux::modal('edit-payment')->close();

        $this->selectedPayment = null;
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
        $this->currencies = StripeConnectService::supportedCurrencies();
        $this->onboardingComplete = $this->team ? $this->team->hasCompletedOnboarding() : false;
    }

    public function generatePaymentLink()
    {
        $this->paymentLink = $this->linkForm->generatePaymentLink();

        Flux::modal('generate-payment-link')->close();

        Flux::modal('payment-link')->show();

        $this->linkForm->reset();
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

<x-app-layout>
    @volt('pages.payments')
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Payments') }}</x-heading>
                    <x-subheading>{{ __('Generate payment links and manage payments.') }}</x-subheading>
                </div>
                <div>
                    @if ($this->onboardingComplete)
                        <flux:modal.trigger :name="auth()->check() ? 'generate-payment-link' : 'login'">
                            <flux:button variant="primary">{{ __('Generate Payment Link') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
            </div>

            @if ($this->onboardingComplete)
                @if ($this->payments?->count())
                    <x-table id="table" class="mt-8">
                        <x-table.columns>
                            <x-table.column>{{ __('Description') }}</x-table.column>
                            <x-table.column>{{ __('Amount') }}</x-table.column>
                            <x-table.column>{{ __('Customer Email') }}</x-table.column>
                            <x-table.column sortable :sorted="$sortBy === 'completed_at'" :direction="$sortDirection" wire:click="sort('completed_at')">{{ __('Payment Date') }}</x-table.column>
                            <x-table.column>{{ __('Photoshoot') }}</x-table.column>
                        </x-table.columns>
                        <x-table.rows>
                            @foreach ($this->payments as $payment)
                                <x-table.row>
                                    <x-table.cell variant="strong">{{ $payment->description }}</x-table.cell>
                                    <x-table.cell>{{ $payment->formattedAmount }}</x-table.cell>
                                    <x-table.cell>{{ $payment->customer_email }}</x-table.cell>
                                    <x-table.cell>{{ $payment->completed_at ? $payment->completed_at->format('F j, Y H:i') : '-' }}</x-table.cell>
                                    <x-table.cell>
                                        @if ($payment->photoshoot)
                                            <flux:link href="/photoshoots/{{ $payment->photoshoot->id }}">
                                                {{ $payment->photoshoot->name }}
                                            </flux:link>
                                        @else
                                            -
                                        @endif
                                    </x-table.cell>
                                    <x-table.cell align="end">
                                        <form wire:submit="editPayment({{ $payment->id }})">
                                            <flux:button type="submit" variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                                        </form>
                                    </x-table.cell>
                                </x-table.row>
                            @endforeach
                        </x-table.rows>
                        {{ $this->payments->links() }}
                    </x-table>
                @else
                    <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                        <flux:icon.credit-card class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                        <flux:heading size="lg" level="2">{{ __('No payments') }}</flux:heading>
                        <flux:subheading class="mb-6 max-w-72 text-center">
                            {{ __('We couldn’t find any payments. Generate a payment link to request payment from a client.') }}
                        </flux:subheading>
                        <flux:modal.trigger name="generate-payment-link">
                            <flux:button variant="primary">
                                {{ __('Generate Link') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif

                <flux:modal name="generate-payment-link" class="w-full sm:max-w-lg">
                    <form wire:submit="generatePaymentLink" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Generate a New Payment Link') }}</flux:heading>
                            <flux:subheading>{{ __('Fill out the details below to generate a payment link you can send to your client.') }}</flux:subheading>
                        </div>
                        <flux:input wire:model="linkForm.amount" :label="__('Amount')" required />
                        <flux:select wire:model="linkForm.currency" :label="__('Currency')" required>
                            @foreach ($this->currencies as $currency)
                                <flux:select.option value="{{ strtolower($currency) }}">{{ strtoupper($currency) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="linkForm.description" :label="__('Description')" type="text" required />
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>

                <flux:modal name="payment-link" class="w-full sm:max-w-lg">
                    <div class="space-y-6">
                        <flux:heading size="lg">{{ __('Payment link') }}</flux:heading>

                        <flux:input
                            icon="link"
                            :value="$this->paymentLink"
                            :label="__('Payment link')"
                            readonly
                            copyable
                        />
                    </div>
                </flux:modal>

                <flux:modal name="edit-payment" variant="flyout">
                    @if ($selectedPayment)
                        <form wire:submit="savePayment" class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Update payment') }}</flux:heading>
                                <flux:text class="mt-2">{{ __('Make changes to the payment details.') }}</flux:text>
                            </div>
                            <flux:select wire:model="paymentForm.photoshoot_id" :label="__('Photoshoot')">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                @foreach ($this->team->photoshoots as $photoshoot)
                                    <flux:select.option value="{{ $photoshoot->id }}">{{ $photoshoot->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input :value="$this->selectedPayment->description" :label="__('Description')" type="text" variant="filled" readonly />
                            <flux:input :value="$this->selectedPayment->formattedAmount" :label="__('Amount')" variant="filled" readonly />
                            <flux:input :value="strtoupper($this->selectedPayment->currency)" :label="__('Currency')" variant="filled" readonly />
                            <flux:input :value="$this->selectedPayment->completed_at->format('Y-m-d H:i:s')" :label="__('Payment Date')" variant="filled" readonly />
                            <flux:input :value="$this->selectedPayment->stripe_payment_intent_id" :label="__('Payment Intent ID')" variant="filled" readonly />
                            <div class="flex">
                                <flux:spacer />
                                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                            </div>
                        </form>
                    @endif
                </flux:modal>
            @else
                <flux:callout icon="banknotes" variant="secondary" class="mt-8">
                    <flux:callout.heading>{{ __('Complete Stripe onboarding to accept payments') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Before you can accept payments, you must complete your Stripe Connect onboarding process.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('stripe.connect')" variant="primary">
                            {{ __('Begin Stripe Onboarding') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif
        </div>
    @endvolt
</x-app-layout>
