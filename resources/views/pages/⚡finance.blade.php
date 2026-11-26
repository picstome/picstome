<?php

use App\Models\BookingSetting;
use App\Models\Invoice;
use App\Models\Team;
use App\Services\FinanceOverviewService;
use App\Services\RevenueService;
use App\Traits\FormatsMoney;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use FormatsMoney;

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function currency(): string
    {
        return strtoupper((string) ($this->team->stripe_currency ?: 'EUR'));
    }

    /**
     * The booking tool is still admin-only, so its block is hidden until the
     * flag is published.
     */
    #[Computed]
    public function canSeeBookings(): bool
    {
        return (bool) Auth::user()?->canSeeBookingsFeature();
    }

    #[Computed]
    public function revenue30Days(): string
    {
        $amount = app(RevenueService::class)->lastDays($this->team, 30);

        return Cashier::formatAmount((int) round($amount * 100), $this->currency);
    }

    /**
     * Percentage change against the previous 30 days. Null when there was no
     * revenue then, because a percentage against zero is not meaningful.
     */
    #[Computed]
    public function revenueTrend(): ?float
    {
        $revenue = app(RevenueService::class);
        $previous = $revenue->between($this->team, now()->subDays(60), now()->subDays(30)->subSecond());

        if ($previous <= 0) {
            return null;
        }

        $current = $revenue->lastDays($this->team, 30);

        return round((($current - $previous) / $previous) * 100, 1);
    }

    #[Computed]
    public function revenueTrendLabel(): string
    {
        if ($this->revenueTrend === null) {
            return __('No revenue in the previous 30 days');
        }

        return $this->revenueTrend >= 0
            ? __(':percent% more than the previous 30 days', ['percent' => number_format($this->revenueTrend, 1)])
            : __(':percent% less than the previous 30 days', ['percent' => number_format(abs($this->revenueTrend), 1)]);
    }

    #[Computed]
    public function unpaidInvoices(): Collection
    {
        return app(FinanceOverviewService::class)->unpaidInvoices($this->team);
    }

    #[Computed]
    public function draftInvoicesCount(): int
    {
        return app(FinanceOverviewService::class)->draftInvoicesCount($this->team);
    }

    #[Computed]
    public function pendingBookings(): Collection
    {
        if (! $this->canSeeBookings) {
            return collect();
        }

        return app(FinanceOverviewService::class)->pendingBookings($this->team);
    }

    /**
     * Administrators open the agenda; everyone else gets the teaser page until
     * the feature flag is published.
     */
    #[Computed]
    public function bookingsUrl(): string
    {
        return $this->canSeeBookings
            ? route('bookings')
            : route('bookings.coming-soon');
    }

    #[Computed]
    public function recentPayments(): Collection
    {
        return app(FinanceOverviewService::class)->recentPayments($this->team);
    }

    #[Computed]
    public function bookingTimezone(): string
    {
        return BookingSetting::forTeam($this->team)->timezone;
    }

    public function isOverdue(Invoice $invoice): bool
    {
        return $invoice->due_date !== null
            && $invoice->due_date->isBefore(now()->startOfDay());
    }
} ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="bg-brand-coral h-10 w-1 rounded-full"></span>

            <div>
                <x-ts-heading size="xl" level="1" class="text-brand-ink dark:text-brand-cream font-semibold">
                    {{ __('Finance') }}
                </x-ts-heading>
                <x-ts-text class="text-brand-ink/75 dark:text-brand-sage/85 mt-0.5 font-medium">
                    {{ __('Revenue, invoices and bookings in one place.') }}
                </x-ts-text>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ts-button :href="route('invoices.create')" wire:navigate icon="document-plus" variant="primary">
                {{ __('New invoice') }}
            </x-ts-button>
            <x-ts-button :href="route('payments')" wire:navigate icon="link" variant="secondary">
                {{ __('Payment links') }}
            </x-ts-button>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('payments') }}" wire:navigate class="block h-full">
            <x-stat-card
                class="h-full"
                tone="ink"
                :label="__('Revenue (30d)')"
                :value="$this->revenue30Days"
                :hint="$this->revenueTrendLabel"
            />
        </a>

        <a href="{{ route('invoices') }}" wire:navigate class="block h-full">
            <x-stat-card
                class="h-full"
                :label="__('Unpaid invoices')"
                :value="$this->unpaidInvoices->count()"
                :hint="$this->unpaidInvoices->isEmpty() ? __('All invoices settled') : __('Issued and not fully paid')"
            />
        </a>

        <a href="{{ route('invoices') }}" wire:navigate class="block h-full">
            <x-stat-card
                class="h-full"
                :label="__('Draft invoices')"
                :value="$this->draftInvoicesCount"
                :hint="__('Not issued yet')"
            />
        </a>

        <a href="{{ $this->bookingsUrl }}" wire:navigate class="block h-full">
            <x-stat-card
                class="h-full"
                :label="__('Bookings')"
                :value="$this->canSeeBookings ? $this->pendingBookings->count() : __('Coming soon')"
                :hint="$this->canSeeBookings ? __('Waiting for payment or Bizum') : __('Online bookings are on the way')"
            />
        </a>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <section class="xl:col-span-2">
            <x-ts-card :heading="__('Recent payments')">
                @if ($this->recentPayments->isEmpty())
                    <x-ts-text variant="subtle" size="sm">
                        {{ __('No payments collected yet.') }}
                    </x-ts-text>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-white/10">
                        @foreach ($this->recentPayments as $payment)
                            <a
                                href="{{ $payment->invoice ? route('invoices.show', $payment->invoice) : route('payments') }}"
                                wire:navigate
                                wire:key="payment-{{ $payment->id }}"
                                class="hover:bg-brand-sage-soft/40 dark:hover:bg-white/5 -mx-2 flex items-center justify-between gap-4 rounded-lg px-2 py-3 transition"
                            >
                                <div class="min-w-0">
                                    <p class="text-brand-ink truncate text-sm font-medium dark:text-white">
                                        {{ $payment->description }}
                                    </p>
                                    <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $payment->completed_at?->isoFormat('LL') }}
                                        @if ($payment->customer_email)
                                            · {{ $payment->customer_email }}
                                        @endif
                                        @if ($payment->booking_id)
                                            · {{ __('Booking') }}
                                        @endif
                                    </p>
                                </div>

                                <span class="shrink-0 text-sm font-semibold tabular-nums">
                                    {{ $payment->formattedAmount }}
                                </span>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-ts-link :href="route('payments')" wire:navigate>
                            {{ __('View all payments') }}
                        </x-ts-link>
                    </div>
                @endif
            </x-ts-card>
        </section>

        <aside>
            <x-ts-card :heading="__('Quick actions')">
                <div class="flex flex-col gap-2">
                    <x-ts-button :href="route('invoices.create')" wire:navigate icon="document-plus" variant="primary" class="justify-start!">
                        {{ __('Create an invoice') }}
                    </x-ts-button>
                    <x-ts-button :href="route('payments')" wire:navigate icon="link" variant="primary" class="btn-ink justify-start!">
                        {{ __('Generate a payment link') }}
                    </x-ts-button>
                    <x-ts-button :href="route('invoices.settings')" wire:navigate icon="cog-6-tooth" variant="primary" class="btn-coral justify-start!">
                        {{ __('Invoice settings') }}
                    </x-ts-button>

                    <x-ts-button :href="$this->bookingsUrl" wire:navigate icon="calendar-days" variant="primary" class="justify-start!">
                        {{ __('Bookings') }}
                    </x-ts-button>

                    @if ($this->canSeeBookings)
                        <x-ts-button :href="route('bookings.settings')" wire:navigate icon="adjustments-horizontal" variant="secondary" class="justify-start!">
                            {{ __('Booking settings') }}
                        </x-ts-button>
                    @endif

                    <x-ts-button :href="route('branding.payments')" wire:navigate icon="credit-card" variant="secondary" class="justify-start!">
                        {{ __('Stripe and payments') }}
                    </x-ts-button>
                </div>
            </x-ts-card>
        </aside>
    </div>

    <div @class([
        'mt-6 grid gap-6',
        'lg:grid-cols-2' => $this->canSeeBookings,
    ])>
        <x-ts-card :heading="__('Unpaid invoices')">
            @if ($this->unpaidInvoices->isEmpty())
                <x-ts-text variant="subtle" size="sm">
                    {{ __('All invoices are settled.') }}
                </x-ts-text>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-white/10">
                    @foreach ($this->unpaidInvoices->take(8) as $invoice)
                        <a
                            href="{{ route('invoices.show', $invoice) }}"
                            wire:navigate
                            wire:key="unpaid-invoice-{{ $invoice->id }}"
                            class="hover:bg-brand-sage-soft/40 dark:hover:bg-white/5 -mx-2 flex items-center justify-between gap-4 rounded-lg px-2 py-3 transition"
                        >
                            <div class="min-w-0">
                                <p @class(['truncate text-sm font-medium', 'font-mono' => filled($invoice->full_number)])>
                                    {{ $invoice->full_number ?? __('Draft') }}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $invoice->customer?->name ?? '—' }}
                                    @if ($invoice->due_date)
                                        · {{ __('Due :date', ['date' => $invoice->due_date->isoFormat('LL')]) }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span class="text-sm font-semibold tabular-nums">
                                    {{ $this->money($invoice->pendingAmount()) }} {{ $invoice->currency }}
                                </span>

                                @if ($this->isOverdue($invoice))
                                    <x-ts-badge size="sm" color="red">{{ __('Overdue') }}</x-ts-badge>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-4 flex justify-end">
                    <x-ts-link :href="route('invoices')" wire:navigate>
                        {{ __('View all invoices') }}
                    </x-ts-link>
                </div>
            @endif
        </x-ts-card>

        @if ($this->canSeeBookings)
            <x-ts-card :heading="__('Pending bookings')">
                @if ($this->pendingBookings->isEmpty())
                    <x-ts-text variant="subtle" size="sm">
                        {{ __('No bookings are waiting for payment.') }}
                    </x-ts-text>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-white/10">
                        @foreach ($this->pendingBookings->take(8) as $booking)
                            <a
                                href="{{ route('bookings') }}"
                                wire:navigate
                                wire:key="pending-booking-{{ $booking->id }}"
                                class="hover:bg-brand-sage-soft/40 dark:hover:bg-white/5 -mx-2 flex items-center justify-between gap-4 rounded-lg px-2 py-3 transition"
                            >
                                <div class="min-w-0">
                                    <p class="text-brand-ink truncate text-sm font-medium dark:text-white">
                                        {{ $booking->product_name }}
                                    </p>
                                    <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $booking->starts_at->setTimezone($this->bookingTimezone)->isoFormat('lll') }}
                                        · {{ $booking->customer_name }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="text-sm font-semibold tabular-nums">
                                        {{ $this->money($booking->total_amount / 100) }} {{ $booking->currency }}
                                    </span>
                                    <x-ts-badge size="sm" :color="$booking->status->color()">
                                        {{ $booking->status->label() }}
                                    </x-ts-badge>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-ts-link :href="route('bookings')" wire:navigate>
                            {{ __('Open the agenda') }}
                        </x-ts-link>
                    </div>
                @endif
            </x-ts-card>
        @endif
    </div>
</div>
