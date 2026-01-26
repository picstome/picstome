<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Notifications\BookingCreated;
use Carbon\Carbon;
use Facades\App\Services\StripeConnectService;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    public ?Photoshoot $photoshoot = null;

    public array $checkoutSession = [];

    #[Url]
    public ?string $session_id = null;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();

        abort_if(! $this->session_id, 404);

        $this->checkoutSession = StripeConnectService::getCheckoutSession($this->team, $this->session_id);

        abort_if(($this->checkoutSession['payment_status'] ?? null) !== 'paid', 404);

        $metadata = $this->checkoutSession['metadata'] ?? [];

        $this->photoshoot = isset($metadata['photoshoot_id'])
            ? $this->team->photoshoots()->find($metadata['photoshoot_id'])
            : null;

        $paymentIntentId = $this->checkoutSession['payment_intent'] ?? null;

        abort_if(! $paymentIntentId, 404);

        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

        abort_if($payment, 404);

        $customer = $this->createCustomerFromPayment();

        if ($this->isBookingWithoutPhotoshootId($metadata)) {
            $this->photoshoot = $this->createPhotoshootFromBooking($metadata, $customer);
        }

        $payment = $this->team->payments()->create([
            'amount' => $this->checkoutSession['amount_total'] ?? 0,
            'currency' => $this->checkoutSession['currency'] ?? 'usd',
            'stripe_payment_intent_id' => $paymentIntentId,
            'description' => $this->checkoutSession['line_items']['data'][0]['description'] ?? null,
            'customer_email' => $this->checkoutSession['customer_details']['email'] ?? null,
            'completed_at' => now(),
            'photoshoot_id' => $this->photoshoot?->id,
        ]);

        if ($this->isBookingWithoutPhotoshootId($metadata)) {
            $this->sendBookingNotifications($metadata, $payment);
        }
    }

    private function sendBookingNotifications(array $metadata, $payment): void
    {
        $tz = $metadata['timezone'];
        $date = Carbon::parse($metadata['booking_date'], $tz);
        $startTime = Carbon::parse($metadata['booking_start_time'], $tz);
        $endTime = Carbon::parse($metadata['booking_end_time'], $tz);
        $this->photoshoot->team->owner->notify(new BookingCreated($this->photoshoot, $date, $startTime, $endTime, $payment, $tz));

        $payerEmail = $this->checkoutSession['customer_details']['email'] ?? null;

        Notification::route('mail', $payerEmail)->notify(new BookingCreated($this->photoshoot, $date, $startTime, $endTime, $payment, $tz));
    }

    private function createPhotoshootFromBooking(array $metadata, Customer $customer)
    {
        $tz = $metadata['timezone'];
        $date = Carbon::parse($metadata['booking_date'], $tz)->format('Y-m-d');
        $timeRange = __('Booked time: :range', ['range' => $metadata['booking_start_time'].' - '.$metadata['booking_end_time']]);

        return $this->team->photoshoots()->create([
            'name' => $this->checkoutSession['line_items']['data'][0]['description'] ?? __('Session'),
            'date' => $date,
            'customer_name' => $this->checkoutSession['customer_details']['email'] ?? null,
            'comment' => $timeRange,
            'customer_id' => $customer->id,
        ]);
    }

    private function createCustomerFromPayment()
    {
        $customerDetails = $this->checkoutSession['customer_details'] ?? [];
        $email = $customerDetails['email'] ?? null;
        $name = $customerDetails['name'] ?? $email ?? __('Customer');

        if ($email) {
            return $this->team->customers()->firstOrCreate(
                ['email' => $email],
                ['name' => $name]
            );
        }

        return $this->team->customers()->create([
            'name' => $name,
        ]);
    }

    private function isBookingWithoutPhotoshootId(array $metadata): bool
    {
        return ($metadata['booking'] ?? false) && ! ($metadata['photoshoot_id'] ?? false);
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
                @if ($team->brand_logo_icon_url)
                    <img
                        src="{{ $team->brand_logo_icon_url.'&w=256&h=256' }}"
                        class="mx-auto size-32"
                        alt="{{ $team->name }}"
                    />
                @else
                    <flux:heading size="xl">{{ $team->name }}</flux:heading>
                @endif
            </a>

            <div>
                <flux:heading size="xl">{{ __('Payment Successful!') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Thank you for your payment. Your transaction was completed successfully.') }}
                </flux:text>
            </div>
        </div>
    </div>
</div>
