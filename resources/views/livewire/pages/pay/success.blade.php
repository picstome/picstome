<?php

use App\Models\Payment;
use App\Models\Team;
use App\Notifications\BookingCreated;
use Facades\App\Services\StripeConnectService;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    public ?string $photoshoot_id = null;

    public array $checkoutSession = [];

    #[Url]
    public ?string $session_id = null;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();

        if ($this->session_id) {
            $this->checkoutSession = StripeConnectService::getCheckoutSession($this->team, $this->session_id);
            $metadata = $this->checkoutSession['metadata'] ?? [];
            $this->photoshoot_id = $metadata['photoshoot_id'] ?? null;

            if (($this->checkoutSession['payment_status'] ?? null) === 'paid') {
                $photoshoot = null;
                $paymentIntentId = $this->checkoutSession['payment_intent'] ?? null;
                $paymentExists = $paymentIntentId ? Payment::where('stripe_payment_intent_id', $paymentIntentId)->exists() : false;
                if (($metadata['booking'] ?? false) && ! $this->photoshoot_id && ! $paymentExists) {
                    $timeRange = match (true) {
                        ! empty($metadata['booking_start_time']) && ! empty($metadata['booking_end_time']) => __('Booked time: :range', ['range' => $metadata['booking_start_time'].' - '.$metadata['booking_end_time']]),
                        ! empty($metadata['booking_start_time']) => __('Booked time: :range', ['range' => $metadata['booking_start_time']]),
                        ! empty($metadata['booking_end_time']) => __('Booked time: :range', ['range' => $metadata['booking_end_time']]),
                        default => null,
                    };

                    $baseName = $this->checkoutSession['line_items']['data'][0]['description'] ?? __('Session');
                    $name = $timeRange ? ($baseName.' ('.$timeRange.')') : $baseName;
                    $photoshoot = $this->team->photoshoots()->create([
                        'name' => $name,
                        'date' => $metadata['booking_date'] ?? null,
                        'customer_name' => $this->checkoutSession['customer_details']['email'] ?? null,
                    ]);
                    $this->photoshoot_id = $photoshoot->id;
                }

                $paymentIntentId = $this->checkoutSession['payment_intent'] ?? null;
                if ($paymentIntentId) {
                    $photoshootForNotification = $photoshoot ?? null;
                    $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
                    if (! $payment) {
                        $payment = $this->team->payments()->create([
                            'amount' => $this->checkoutSession['amount_total'] ?? 0,
                            'currency' => $this->checkoutSession['currency'] ?? 'usd',
                            'stripe_payment_intent_id' => $paymentIntentId,
                            'description' => $this->checkoutSession['line_items']['data'][0]['description'] ?? null,
                            'customer_email' => $this->checkoutSession['customer_details']['email'] ?? null,
                            'completed_at' => now(),
                            'photoshoot_id' => $this->photoshoot_id,
                        ]);
                        // Only send notification if a photoshoot was just created
                        if (($metadata['booking'] ?? false) && $photoshootForNotification) {
                            if ($photoshootForNotification->team && $photoshootForNotification->team->owner) {
                                $photoshootForNotification->team->owner->notify(new BookingCreated($photoshootForNotification, $payment));
                            }
                            $payerEmail = $this->checkoutSession['customer_details']['email'] ?? null;
                            if ($payerEmail) {
                                Notification::route('mail', $payerEmail)->notify(new BookingCreated($photoshootForNotification, $payment));
                            }
                        }
                    }
                }
            }
        }
    }

    public function rendering(View $view): void
    {
        $view->title(__('Payment Successful for :team', ['team' => $this->team->name]));
    }
} ?>

<div class="flex min-h-screen items-center justify-center px-4">
    <div class="mx-auto w-full max-w-md text-center">
        <div class="space-y-4">
            <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="block space-y-4" wire:navigate>
                @if($team->brand_logo_icon_url)
                    <img src="{{ $team->brand_logo_icon_url . '&w=256&h=256' }}" class="mx-auto size-32" alt="{{ $team->name }}" />
                @else
                    <flux:heading size="xl">{{ $team->name }}</flux:heading>
                @endif
            </a>

            <div>
                <flux:heading size="xl">{{ __('Payment Successful!') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Thank you for your payment. Your transaction was completed successfully.') }}</flux:text>
            </div>
        </div>
    </div>
</div>
