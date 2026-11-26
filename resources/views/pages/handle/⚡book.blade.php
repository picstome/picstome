<?php

use App\Enums\BookingStatus;
use App\Enums\LightMode;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingLimitReachedException;
use App\Exceptions\SlotUnavailableException;
use App\Livewire\Forms\PublicBookingForm;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Product;
use App\Models\Team;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingFulfilmentService;
use App\Services\Booking\BookingPageGate;
use App\Services\Booking\BookingPlanLimitService;
use App\Services\Booking\BookingService;
use App\Services\Booking\SunlightService;
use App\Services\FakePaymentService;
use App\Support\MonthGrid;
use Carbon\CarbonImmutable;
use Facades\App\Services\StripeConnectService;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new
#[Layout('layouts.guest')]
class extends Component
{
    use Interactions;

    public Team $team;

    public ?Product $product = null;

    public PublicBookingForm $form;

    /** product | datetime | details | payment | bizum */
    public string $step = 'product';

    public string $month = '';

    public string $date = '';

    public string $slot = '';

    public string $paymentKind = 'full';

    public ?int $bookingId = null;

    /**
     * Availability per month, cached across Livewire requests so clicking a
     * day does not recompute the whole month.
     *
     * @var array<string, array<string, bool>>
     */
    public array $monthAvailability = [];

    public function mount(string $handle, ?string $productUlid = null): void
    {
        $this->team = Team::where('handle', strtolower($handle))->firstOrFail();

        $settings = $this->settings;

        $this->form->phoneRequired = (bool) $this->team->phone_required;
        $this->form->hasPolicy = filled($settings->cancellation_policy);

        $today = CarbonImmutable::now($settings->timezone);
        $this->month = $today->format('Y-m');
        $this->date = $today->toDateString();

        abort_unless($this->isBookable(), 404);

        if ($productUlid !== null) {
            $this->product = $this->team->products()->active()->where('ulid', $productUlid)->firstOrFail();
            $this->startBooking();
        } elseif ($this->products->count() === 1) {
            $this->product = $this->products->first();
            $this->startBooking();
        }
    }

    #[Computed]
    public function settings(): BookingSetting
    {
        return BookingSetting::forTeam($this->team);
    }

    #[Computed]
    public function timezone(): string
    {
        return $this->settings->timezone;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function products()
    {
        return $this->team->products()->active()->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function canPayWithCard(): bool
    {
        return app(BookingPageGate::class)->canPayWithCard($this->team, $this->settings);
    }

    #[Computed]
    public function canPayWithBizum(): bool
    {
        return app(BookingPageGate::class)->canPayWithBizum($this->team);
    }

    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month.'-01', $this->timezone)->startOfMonth();
    }

    #[Computed]
    public function monthEnd(): CarbonImmutable
    {
        return $this->monthStart->endOfMonth();
    }

    /**
     * @return array<int, array{date: string, day: int, inMonth: bool, available: bool, isToday: bool, isSelected: bool}>
     */
    #[Computed]
    public function calendarDays(): array
    {
        $timezone = $this->timezone;
        $today = CarbonImmutable::now($timezone)->toDateString();
        $availability = $this->monthAvailability[$this->month] ?? [];
        $days = [];

        foreach (app(MonthGrid::class)->days($this->monthStart) as $date) {
            $key = $date->toDateString();
            $inMonth = $date->month === $this->monthStart->month;

            $days[] = [
                'date' => $key,
                'day' => $date->day,
                'inMonth' => $inMonth,
                'available' => $inMonth && ($availability[$key] ?? false),
                'isToday' => $key === $today,
                'isSelected' => $key === $this->date,
            ];
        }

        return $days;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Services\Booking\Slot>
     */
    #[Computed]
    public function daySlots()
    {
        if ($this->product === null || $this->date === '') {
            return collect();
        }

        return app(AvailabilityService::class)->slotsForDate(
            $this->team,
            $this->product,
            CarbonImmutable::parse($this->date, $this->timezone),
        );
    }

    #[Computed]
    public function sunTime(): ?CarbonImmutable
    {
        if ($this->product === null
            || $this->product->light_mode === LightMode::None
            || $this->product->latitude === null
            || $this->product->longitude === null
            || $this->date === '') {
            return null;
        }

        return app(SunlightService::class)->sunTime(
            CarbonImmutable::parse($this->date, $this->timezone),
            $this->product->latitude,
            $this->product->longitude,
            $this->timezone,
            $this->product->light_mode,
        );
    }

    #[Computed]
    public function recommendedStart(): ?CarbonImmutable
    {
        if ($this->product === null
            || $this->product->light_mode === LightMode::None
            || $this->product->latitude === null
            || $this->product->longitude === null) {
            return null;
        }

        return app(SunlightService::class)->recommendedStart(
            $this->daySlots->all(),
            $this->product->light_mode,
            CarbonImmutable::parse($this->date, $this->timezone),
            $this->product->latitude,
            $this->product->longitude,
            $this->timezone,
            $this->product->duration_minutes,
        );
    }

    #[Computed]
    public function depositAmount(): int
    {
        return $this->product?->depositAmount($this->settings) ?? 0;
    }

    #[Computed]
    public function amountToPay(): int
    {
        if ($this->product === null) {
            return 0;
        }

        return $this->paymentKind === 'deposit'
            ? $this->depositAmount
            : $this->product->price;
    }

    #[Computed]
    public function booking(): ?Booking
    {
        if ($this->bookingId === null) {
            return null;
        }

        return $this->team->bookings()->with(['product', 'payments'])->find($this->bookingId);
    }

    public function selectProduct(int $productId): void
    {
        $this->product = $this->team->products()->active()->findOrFail($productId);
        $this->monthAvailability = [];
        $this->startBooking();
    }

    public function previousMonth(): void
    {
        $this->changeMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->changeMonth(1);
    }

    public function selectDate(string $date): void
    {
        if (! ($this->monthAvailability[$this->month][$date] ?? false)) {
            return;
        }

        $this->date = $date;
        $this->slot = '';
    }

    public function selectSlot(string $time): void
    {
        $this->slot = $time;
    }

    public function continueToDetails(): void
    {
        $available = $this->daySlots->contains(
            fn ($slot) => $slot->startsAt->setTimezone($this->timezone)->format('H:i') === $this->slot,
        );

        if ($this->slot === '' || ! $available) {
            $this->addError('slot', __('Choose a free time to continue.'));

            return;
        }

        $this->resetErrorBag('slot');
        $this->step = 'details';
    }

    public function submitDetails()
    {
        $this->form->validate();

        if ($this->product === null) {
            return null;
        }

        $startsAt = CarbonImmutable::parse($this->date.' '.$this->slot, $this->timezone);

        try {
            $booking = app(BookingService::class)->hold(
                $this->team,
                $this->product,
                $startsAt,
                [
                    'name' => $this->form->name,
                    'email' => $this->form->email,
                    'phone' => $this->form->phone !== '' ? $this->form->phone : null,
                    'notes' => $this->form->notes !== '' ? $this->form->notes : null,
                ],
                PaymentMethod::Card,
                $this->settings->cancellation_policy,
            );
        } catch (SlotUnavailableException) {
            $this->step = 'datetime';
            $this->slot = '';
            $this->addError('slot', __('That time is no longer available. Choose another one.'));

            return null;
        } catch (BookingLimitReachedException) {
            $this->addError('payment', __('This photographer cannot accept more reservations this month.'));

            return null;
        }

        $this->bookingId = $booking->id;

        if ($this->depositAmount > 0 || ($this->canPayWithCard && $this->canPayWithBizum)) {
            $this->step = 'payment';

            return null;
        }

        if ($this->canPayWithCard) {
            return $this->payWithCard();
        }

        if ($this->canPayWithBizum) {
            $this->payWithBizum();

            return null;
        }

        $this->addError('payment', __('No payment method is available. Please contact the photographer.'));

        return null;
    }

    public function setPaymentKind(string $kind): void
    {
        abort_unless(in_array($kind, ['full', 'deposit'], true), 404);

        if ($kind === 'deposit' && $this->depositAmount <= 0) {
            return;
        }

        $this->paymentKind = $kind;
    }

    public function payWithCard()
    {
        $booking = $this->booking;

        if ($booking === null || ! $this->canPayWithCard) {
            return null;
        }

        $kind = $this->paymentKind === 'deposit' ? PaymentKind::Deposit : PaymentKind::Full;
        $booking->update(['payment_kind' => $kind]);

        if (FakePaymentService::enabled()) {
            app(BookingFulfilmentService::class)->pay(
                $booking,
                $kind,
                PaymentMethod::Card,
                'fake_'.uniqid(),
                $booking->customer_email,
            );

            return $this->redirectRoute('handle.book.success', [
                'handle' => $this->team->handle,
                'booking' => $booking->ulid,
            ]);
        }

        $url = StripeConnectService::createCheckoutSession(
            $this->team,
            route('handle.book.success', ['handle' => $this->team->handle]).'?session_id={CHECKOUT_SESSION_ID}',
            route('handle.book', ['handle' => $this->team->handle, 'productUlid' => $this->product?->ulid]),
            $this->amountToPay,
            $this->product?->name.' · '.($kind === PaymentKind::Deposit ? __('Deposit') : __('Full payment')),
            [
                'booking_ulid' => $booking->ulid,
                'payment_kind' => $kind->value,
            ],
        );

        return redirect()->away($url);
    }

    public function payWithBizum(): void
    {
        $booking = $this->booking;

        if ($booking === null || ! $this->canPayWithBizum) {
            return;
        }

        $kind = $this->paymentKind === 'deposit' ? PaymentKind::Deposit : PaymentKind::Full;

        app(BookingFulfilmentService::class)->markPendingBizum($booking, $kind);

        $this->bookingId = $booking->id;
        $this->step = 'bizum';
    }

    public function back(): void
    {
        $this->step = match ($this->step) {
            'details' => 'datetime',
            'payment' => 'details',
            'bizum' => 'details',
            default => 'product',
        };
    }

    public function formatAmount(int $minorUnits): string
    {
        return Cashier::formatAmount($minorUnits, $this->product?->currency ?? 'EUR');
    }

    public function reference(): string
    {
        return $this->booking?->bizumReference() ?? '';
    }

    public function rendering(View $view): void
    {
        $view->title(__('Book a session with :team', ['team' => $this->team->name]));
    }

    private function startBooking(): void
    {
        $this->step = 'datetime';
        $this->loadMonthAvailability();
    }

    private function isBookable(): bool
    {
        return app(BookingPageGate::class)->canBook($this->team);
    }

    private function changeMonth(int $delta): void
    {
        $month = CarbonImmutable::parse($this->month.'-01', $this->timezone)
            ->startOfMonth()
            ->addMonthsNoOverflow($delta);

        $this->month = $month->format('Y-m');
        $this->date = $month->toDateString();
        $this->slot = '';

        // Drop the cached month values: they were computed for the previous
        // month during this same request, and the render would reuse them.
        unset($this->monthStart, $this->monthEnd, $this->calendarDays);

        $this->loadMonthAvailability();
    }

    private function loadMonthAvailability(): void
    {
        if ($this->product === null || array_key_exists($this->month, $this->monthAvailability)) {
            return;
        }

        $availability = app(AvailabilityService::class);
        $map = [];

        for ($date = $this->monthStart; $date->lessThanOrEqualTo($this->monthEnd); $date = $date->addDay()) {
            $map[$date->toDateString()] = $availability
                ->slotsForDate($this->team, $this->product, $date)
                ->isNotEmpty();
        }

        $this->monthAvailability[$this->month] = $map;
    }
}; ?>

<div class="mx-auto w-full max-w-3xl px-4 py-10">
    <div class="mb-8 text-center">
        @if ($team->brand_logo_icon_url)
            <img src="{{ $team->brand_logo_icon_url.'&w=192&h=192' }}" class="mx-auto mb-4 size-20 rounded" alt="{{ $team->name }}" />
        @endif
        <x-ts-heading level="1" size="xl">{{ __('Book a session with :team', ['team' => $team->name]) }}</x-ts-heading>
        @if ($product)
            <x-ts-subheading class="mt-1">
                {{ $product->name }} · {{ $product->duration_minutes }} {{ __('min') }} · {{ $this->formatAmount($product->price) }}
            </x-ts-subheading>
        @endif
    </div>

    <div class="mb-8 flex items-center justify-center gap-2 text-xs font-medium text-zinc-400">
        @foreach (['product' => __('Session'), 'datetime' => __('Date and time'), 'details' => __('Your details'), 'payment' => __('Payment')] as $key => $label)
            <span @class([
                'rounded-full px-3 py-1',
                'bg-brand-green text-white' => $step === $key,
                'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300' => $step !== $key,
            ])>{{ $label }}</span>
        @endforeach
    </div>

    @if ($step === 'product')
        <div class="space-y-3">
            @foreach ($this->products as $option)
                <button
                    type="button"
                    wire:key="product-{{ $option->id }}"
                    wire:click="selectProduct({{ $option->id }})"
                    class="flex w-full items-start justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-4 text-left transition hover:border-brand-green hover:bg-brand-paper dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                >
                    <span class="min-w-0">
                        <span class="block font-semibold">{{ $option->name }}</span>
                        @if ($option->description)
                            <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ $option->description }}</span>
                        @endif
                        <span class="mt-2 block text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $option->duration_minutes }} {{ __('min') }}
                            @if ($option->depositAmount($this->settings) > 0)
                                · {{ __('Deposit') }} {{ $this->formatAmount($option->depositAmount($this->settings)) }}
                            @endif
                        </span>
                    </span>
                    <span class="shrink-0 text-right font-semibold">{{ $this->formatAmount($option->price) }}</span>
                </button>
            @endforeach
        </div>
    @endif

    @if ($step === 'datetime')
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
            <div class="flex items-center justify-between">
                <x-ts-button type="button" variant="ghost" icon="chevron-left" wire:click="previousMonth" />
                <span class="text-sm font-semibold capitalize">{{ $this->monthStart->translatedFormat('F Y') }}</span>
                <x-ts-button type="button" variant="ghost" icon="chevron-right" wire:click="nextMonth" />
            </div>

            <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-zinc-400">
                @foreach ([__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')] as $day)
                    <div class="py-1">{{ $day }}</div>
                @endforeach
            </div>

            <div class="mt-1 grid grid-cols-7 gap-1">
                @foreach ($this->calendarDays as $day)
                    <button
                        type="button"
                        wire:key="book-day-{{ $day['date'] }}"
                        wire:click="selectDate('{{ $day['date'] }}')"
                        @disabled(! $day['available'])
                        @class([
                            'flex h-9 items-center justify-center rounded-lg text-xs transition sm:h-10 sm:text-sm',
                            'text-zinc-300 dark:text-zinc-600' => ! $day['available'],
                            'hover:bg-brand-sage-soft dark:hover:bg-white/10' => $day['available'] && ! $day['isSelected'],
                            'bg-brand-green text-white' => $day['isSelected'],
                            'font-semibold text-brand-ink dark:text-white' => $day['available'] && ! $day['isSelected'],
                            'ring-1 ring-brand-green/30' => $day['isToday'] && ! $day['isSelected'],
                        ])
                    >
                        {{ $day['day'] }}
                    </button>
                @endforeach
            </div>

            <div class="mt-5 border-t border-zinc-100 pt-4 dark:border-white/10">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold">
                        {{ \Carbon\CarbonImmutable::parse($this->date, $this->timezone)->translatedFormat('l j F') }}
                    </p>
                    @if ($this->sunTime)
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $product->light_mode === \App\Enums\LightMode::Sunset ? __('Sunset') : __('Sunrise') }}
                            {{ $this->sunTime->format('H:i') }}
                        </p>
                    @endif
                </div>

                @if ($this->daySlots->isEmpty())
                    <x-ts-callout variant="warning" icon="exclamation-triangle">
                        <x-ts-callout-text>{{ __('No free times on this day. Choose another one.') }}</x-ts-callout-text>
                    </x-ts-callout>
                @else
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($this->daySlots as $option)
                            @php($time = $option->startsAt->setTimezone($this->timezone)->format('H:i'))
                            <button
                                type="button"
                                wire:key="slot-{{ $time }}"
                                wire:click="selectSlot('{{ $time }}')"
                                @class([
                                    'rounded-lg border px-2 py-2 text-sm transition',
                                    'border-brand-green bg-brand-green text-white' => $this->slot === $time,
                                    'border-zinc-200 hover:border-brand-green dark:border-white/10' => $this->slot !== $time,
                                    'ring-2 ring-brand-coral/60' => $this->recommendedStart && $option->startsAt->equalTo($this->recommendedStart) && $this->slot !== $time,
                                ])
                            >
                                {{ $time }}
                                @if ($this->recommendedStart && $option->startsAt->equalTo($this->recommendedStart))
                                    <span class="block text-[10px] opacity-80">{{ __('Best light') }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif

                @error('slot')
                    <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-5 flex justify-between">
            @if ($this->products->count() > 1)
                <x-ts-button type="button" variant="secondary" wire:click="$set('step', 'product')">
                    {{ __('Change session') }}
                </x-ts-button>
            @else
                <span></span>
            @endif
            <x-ts-button type="button" variant="primary" wire:click="continueToDetails" :disabled="$this->slot === ''">
                {{ __('Continue') }}
            </x-ts-button>
        </div>
    @endif

    @if ($step === 'details')
        <div class="space-y-5">
            <x-ts-card :heading="__('Your details')">
                @error('payment')
                    <x-ts-callout variant="error" icon="exclamation-circle" class="mb-4">
                        <x-ts-callout-text>{{ $message }}</x-ts-callout-text>
                    </x-ts-callout>
                @enderror

                @if ($errors->has('form.name') || $errors->has('form.email') || $errors->has('form.phone'))
                    <x-ts-callout variant="error" icon="exclamation-circle" class="mb-4">
                        <x-ts-callout-text>
                            {{ $errors->first('form.name') ?: $errors->first('form.email') ?: $errors->first('form.phone') }}
                        </x-ts-callout-text>
                    </x-ts-callout>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ts-input wire:model="form.name" :label="__('Name')" required />
                    <x-ts-input wire:model="form.email" type="email" :label="__('Email')" required />
                    <x-ts-input wire:model="form.phone" :label="__('Phone')" :required="$form->phoneRequired" />
                    <x-ts-input wire:model="form.notes" :label="__('Anything we should know?')" />
                </div>

                @if ($form->hasPolicy)
                    <div class="mt-5 rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-white/5 dark:text-zinc-300">
                        <p class="mb-2 font-semibold">{{ __('Cancellation policy') }}</p>
                        <p class="whitespace-pre-line">{{ $this->settings->cancellation_policy }}</p>
                    </div>

                    <div class="mt-3">
                        <x-ts-checkbox wire:model="form.acceptedPolicy" :label="__('I accept the cancellation policy')" />
                        @error('form.acceptedPolicy')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </x-ts-card>

            <x-ts-card :heading="__('Summary')">
                <div class="flex items-center justify-between text-sm">
                    <span>{{ $product->name }}</span>
                    <span class="font-semibold">{{ $this->formatAmount($product->price) }}</span>
                </div>
                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ \Carbon\CarbonImmutable::parse($this->date.' '.$this->slot, $this->timezone)->translatedFormat('l j F, H:i') }}
                    – {{ \Carbon\CarbonImmutable::parse($this->date.' '.$this->slot, $this->timezone)->addMinutes($product->duration_minutes)->format('H:i') }}
                </div>
            </x-ts-card>

            <div class="flex justify-between">
                <x-ts-button type="button" variant="secondary" wire:click="back">{{ __('Back') }}</x-ts-button>
                <x-ts-button type="button" variant="primary" wire:click="submitDetails">
                    {{ __('Continue to payment') }}
                </x-ts-button>
            </div>
        </div>
    @endif

    @if ($step === 'payment' && $this->booking)
        <div class="space-y-5">
            <x-ts-card :heading="__('Payment')">
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span>{{ $product->name }}</span>
                        <span class="font-semibold">{{ $this->formatAmount($product->price) }}</span>
                    </div>
                    <div class="text-zinc-500 dark:text-zinc-400">
                        {{ $this->booking->starts_at->setTimezone($this->timezone)->translatedFormat('l j F, H:i') }}
                        – {{ $this->booking->ends_at->setTimezone($this->timezone)->format('H:i') }}
                    </div>
                </div>

                @if ($this->depositAmount > 0)
                    <div class="mt-5 space-y-2">
                        <p class="text-sm font-semibold">{{ __('How much do you want to pay now?') }}</p>
                        <button type="button" wire:click="setPaymentKind('deposit')" @class([
                            'flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left text-sm',
                            'border-brand-green bg-brand-sage-soft dark:bg-white/10' => $paymentKind === 'deposit',
                            'border-zinc-200 dark:border-white/10' => $paymentKind !== 'deposit',
                        ])>
                            <span>{{ __('Deposit') }}</span>
                            <span class="font-semibold">{{ $this->formatAmount($this->depositAmount) }}</span>
                        </button>
                        <button type="button" wire:click="setPaymentKind('full')" @class([
                            'flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left text-sm',
                            'border-brand-green bg-brand-sage-soft dark:bg-white/10' => $paymentKind === 'full',
                            'border-zinc-200 dark:border-white/10' => $paymentKind !== 'full',
                        ])>
                            <span>{{ __('Full payment') }}</span>
                            <span class="font-semibold">{{ $this->formatAmount($product->price) }}</span>
                        </button>
                    </div>
                @endif

                <div class="mt-5 rounded-lg bg-zinc-50 p-4 text-sm dark:bg-white/5">
                    <div class="flex items-center justify-between font-semibold">
                        <span>{{ __('Pay now') }}</span>
                        <span>{{ $this->formatAmount($this->amountToPay) }}</span>
                    </div>
                    @if ($this->amountToPay < $product->price)
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('The remaining :amount is requested before the session.', ['amount' => $this->formatAmount($product->price - $this->amountToPay)]) }}
                        </p>
                    @endif
                </div>
            </x-ts-card>

            <div class="grid gap-3 sm:grid-cols-2">
                @if ($this->canPayWithCard)
                    <x-ts-button type="button" variant="primary" wire:click="payWithCard">
                        {{ __('Pay by card') }}
                    </x-ts-button>
                @endif
                @if ($this->canPayWithBizum)
                    <x-ts-button type="button" variant="secondary" wire:click="payWithBizum">
                        {{ __('Pay by Bizum') }}
                    </x-ts-button>
                @endif
            </div>

            <x-ts-button type="button" variant="ghost" wire:click="back">{{ __('Back') }}</x-ts-button>
        </div>
    @endif

    @if ($step === 'bizum' && $this->booking)
        <x-ts-card :heading="__('Pay by Bizum')">
            <div class="space-y-4 text-sm">
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-white/5">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Session') }}</p>
                    <p class="mt-1 text-base font-semibold">
                        {{ $this->booking->starts_at->setTimezone($this->booking->timezone)->isoFormat('LL') }}
                        · {{ $this->booking->starts_at->setTimezone($this->booking->timezone)->format('H:i') }}
                        - {{ $this->booking->ends_at->setTimezone($this->booking->timezone)->format('H:i') }}
                    </p>
                </div>

                <p>{{ __('Send the amount to this phone number and the photographer will confirm your booking.') }}</p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-white/5">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Bizum phone') }}</p>
                        <p class="text-3xl font-black tracking-tight sm:text-4xl">{{ $this->team->bizum_phone }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-white/5">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Amount') }}</p>
                        <p class="text-2xl font-bold">{{ $this->formatAmount($this->amountToPay) }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-center dark:border-white/15">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Reference') }}</p>
                    <p class="text-3xl font-black tracking-widest sm:text-4xl">{{ $this->reference() }}</p>
                </div>

                @if (filled($this->team->bizum_instructions))
                    <p class="whitespace-pre-line text-zinc-500 dark:text-zinc-400">{{ $this->team->bizum_instructions }}</p>
                @endif

                <x-ts-callout variant="warning" icon="clock">
                    <x-ts-callout-text>
                        {{ __('Your slot is held for a while. If the payment does not arrive, it is released automatically.') }}
                    </x-ts-callout-text>
                </x-ts-callout>
            </div>
        </x-ts-card>
    @endif
</div>
