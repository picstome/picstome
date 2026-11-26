<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Services\Booking\BookingFulfilmentService;
use Facades\App\Services\StripeConnectService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public \App\Models\Team $team;

    public \App\Models\Booking $booking;

    public function mount(string $handle, ?string $session_id = null, ?string $booking = null): void
    {
        $this->team = \App\Models\Team::where('handle', strtolower($handle))->firstOrFail();

        if ($booking !== null) {
            $this->booking = $this->team->bookings()->with('product')->where('ulid', $booking)->firstOrFail();

            return;
        }

        abort_if($session_id === null, 404);

        $session = StripeConnectService::getCheckoutSession($this->team, $session_id);

        abort_if(($session['payment_status'] ?? null) !== 'paid', 404);

        $ulid = $session['metadata']['booking_ulid'] ?? null;

        abort_if(! is_string($ulid) || $ulid === '', 404);

        $this->booking = $this->team->bookings()->with('product')->where('ulid', $ulid)->firstOrFail();

        // The webhook is the source of truth, but if it has not arrived yet
        // (or the request was made from a local environment without it) the
        // success page can finish the job safely: fulfilment is idempotent.
        if ($this->booking->status !== BookingStatus::Confirmed) {
            $kind = PaymentKind::tryFrom((string) ($session['metadata']['payment_kind'] ?? ''))
                ?? PaymentKind::Full;

            app(BookingFulfilmentService::class)->pay(
                $this->booking,
                $kind,
                PaymentMethod::Card,
                $session['payment_intent'] ?? null,
                $session['customer_details']['email'] ?? null,
            );

            $this->booking->refresh();
        }
    }

    public function rendering(\Illuminate\View\View $view): void
    {
        $view->title(__('Booking with :team', ['team' => $this->team->name]));
    }
}; ?>

<div class="mx-auto w-full max-w-lg px-4 py-12 text-center">
    <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="inline-block" wire:navigate>
        @if ($team->brand_logo_icon_url)
            <img src="{{ $team->brand_logo_icon_url.'&w=192&h=192' }}" class="mx-auto mb-6 size-20 rounded" alt="{{ $team->name }}" />
        @else
            <x-ts-heading size="xl" class="mb-6">{{ $team->name }}</x-ts-heading>
        @endif
    </a>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 text-left dark:border-white/10 dark:bg-white/5">
        <x-ts-heading size="lg">
            {{ $booking->status === \App\Enums\BookingStatus::PendingBizum ? __('Booking received') : __('Booking confirmed') }}
        </x-ts-heading>

        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            @if ($booking->status === \App\Enums\BookingStatus::PendingBizum)
                {{ __('Send the Bizum with the reference below. The photographer confirms the booking as soon as the payment arrives.') }}
            @else
                {{ __('We have sent you an email with the details and a calendar file.') }}
            @endif
        </p>

        <div class="mt-5 space-y-2 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Session') }}</span>
                <span class="text-right font-medium">{{ $booking->product_name }}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Date') }}</span>
                <span class="text-right font-medium">
                    {{ $booking->starts_at->setTimezone($booking->timezone)->translatedFormat('l j F, H:i') }}
                    – {{ $booking->ends_at->setTimezone($booking->timezone)->format('H:i') }}
                </span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</span>
                <span class="text-right font-medium">{{ \Laravel\Cashier\Cashier::formatAmount($booking->total_amount, $booking->currency) }}</span>
            </div>
            @if ($booking->status === \App\Enums\BookingStatus::PendingBizum)
                <div class="flex justify-between gap-4">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Reference') }}</span>
                    <span class="text-right text-2xl font-black tracking-widest">{{ $booking->bizumReference() }}</span>
                </div>
            @endif
        </div>

        @if ($booking->status === \App\Enums\BookingStatus::Confirmed)
            <div class="mt-6">
                <x-ts-button
                    :href="route('handle.book.ics', ['handle' => $team->handle, 'booking' => $booking->ulid])"
                    variant="secondary"
                    icon="calendar-days"
                    class="w-full"
                >
                    {{ __('Add to my calendar') }}
                </x-ts-button>
            </div>
        @endif
    </div>

    <p class="mt-6 text-xs text-zinc-400">
        {{ __('If you need to change anything, contact :team directly.', ['team' => $team->name]) }}
    </p>
</div>
