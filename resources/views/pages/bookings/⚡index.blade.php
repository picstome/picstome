<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\SlotUnavailableException;
use App\Livewire\Forms\CalendarBlockForm;
use App\Livewire\Forms\ManualBookingForm;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\CalendarBlock;
use App\Models\Team;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingFulfilmentService;
use App\Services\Booking\BookingService;
use App\Support\MonthGrid;
use App\Traits\InteractsWithTallStackModals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use InteractsWithTallStackModals;

    /** month | agenda */
    #[Url]
    public string $view = 'month';

    /** Displayed month, Y-m. */
    public string $month = '';

    /** Selected day, Y-m-d. */
    public string $selectedDate = '';

    public ?int $selectedBookingId = null;

    public ?int $pendingCancelId = null;

    public string $cancelReason = '';

    public ?int $pendingBlockDeleteId = null;

    public ManualBookingForm $manualForm;

    public CalendarBlockForm $blockForm;

    /**
     * Free-text search used to reuse an existing customer in a manual
     * booking. It never stores an id: the email is the identity, so editing
     * the email creates or reuses the right customer.
     */
    public string $customerSearch = '';

    public function mount(): void
    {
        if (! in_array($this->view, ['month', 'agenda', 'products'], true)) {
            $this->view = 'month';
        }

        $today = CarbonImmutable::now($this->timezone);

        $this->month = $today->format('Y-m');
        $this->selectedDate = $today->toDateString();
    }

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function timezone(): string
    {
        return BookingSetting::forTeam($this->team)->timezone;
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
     * All bookings of the visible month, expired ones excluded.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Booking>
     */
    #[Computed]
    public function monthBookings()
    {
        return $this->team->bookings()
            ->with('product')
            ->where('status', '!=', BookingStatus::Expired->value)
            ->where('starts_at', '>=', $this->monthStart->utc())
            ->where('starts_at', '<=', $this->monthEnd->utc())
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CalendarBlock>
     */
    #[Computed]
    public function monthBlocks()
    {
        return $this->team->calendarBlocks()
            ->overlapping($this->monthStart->utc(), $this->monthEnd->utc())
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Cells of the month grid, Monday first.
     *
     * @return array<int, array{
     *     date: string,
     *     day: int,
     *     inMonth: bool,
     *     isToday: bool,
     *     isSelected: bool,
     *     isPast: bool,
     *     blocked: bool,
     *     partialBlock: bool,
     *     count: int,
     *     statuses: array<int, string>
     * }>
     */
    #[Computed]
    public function calendarDays(): array
    {
        $timezone = $this->timezone;
        $today = CarbonImmutable::now($timezone)->toDateString();
        $bookings = $this->monthBookings->groupBy(
            fn (Booking $booking) => $booking->starts_at->setTimezone($timezone)->toDateString(),
        );
        $blocks = $this->monthBlocks;
        $days = [];

        foreach (app(MonthGrid::class)->days($this->monthStart) as $date) {
            $key = $date->toDateString();
            $dayStart = $date->startOfDay();
            $dayEnd = $date->addDay();
            $dayEndOfDay = $date->endOfDay()->startOfSecond();

            $blocked = $blocks->contains(
                fn (CalendarBlock $block) => $block->coversDay($dayStart, $dayEndOfDay),
            );

            $partialBlock = ! $blocked && $blocks->contains(
                fn (CalendarBlock $block) => $block->starts_at->lessThan($dayEnd)
                    && $block->ends_at->greaterThan($dayStart),
            );

            $dayBookings = $bookings->get($key, collect());

            $days[] = [
                'date' => $key,
                'day' => $date->day,
                'inMonth' => $date->month === $this->monthStart->month,
                'isToday' => $key === $today,
                'isSelected' => $key === $this->selectedDate,
                'isPast' => $key < $today,
                'blocked' => $blocked,
                'partialBlock' => $partialBlock,
                'count' => $dayBookings->count(),
                'statuses' => $dayBookings->pluck('status')->map(fn (BookingStatus $status) => $status->value)->unique()->values()->all(),
            ];
        }

        return $days;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    #[Computed]
    public function dayBookings()
    {
        $timezone = $this->timezone;

        return $this->monthBookings
            ->filter(fn (Booking $booking) => $booking->starts_at->setTimezone($timezone)->toDateString() === $this->selectedDate)
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, CalendarBlock>
     */
    #[Computed]
    public function dayBlocks()
    {
        $timezone = $this->timezone;
        $dayStart = CarbonImmutable::parse($this->selectedDate, $timezone)->startOfDay();
        $dayEnd = $dayStart->addDay();

        return $this->monthBlocks
            ->filter(fn (CalendarBlock $block) => $block->starts_at->lessThan($dayEnd) && $block->ends_at->greaterThan($dayStart))
            ->values();
    }

    /**
     * Confirmed and pending sessions from today onwards, for the agenda.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Booking>
     */
    #[Computed]
    public function upcoming()
    {
        return $this->team->bookings()
            ->with('product')
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::PendingPayment->value,
                BookingStatus::PendingBizum->value,
            ])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(60)
            ->get();
    }

    #[Computed]
    public function selectedBooking(): ?Booking
    {
        if ($this->selectedBookingId === null) {
            return null;
        }

        return $this->team->bookings()->with(['product', 'customer', 'payments.invoice'])->find($this->selectedBookingId);
    }

    /**
     * Times the photographer can pick for a manual booking, from the same
     * engine that drives the public calendar.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function availableSlots(): array
    {
        if ($this->manualForm->product_id === null || $this->manualForm->date === '') {
            return [];
        }

        $product = $this->team->products()->find($this->manualForm->product_id);

        if ($product === null) {
            return [];
        }

        try {
            $date = CarbonImmutable::parse($this->manualForm->date, $this->timezone);
        } catch (Throwable) {
            return [];
        }

        return app(AvailabilityService::class)
            ->slotsForDate($this->team, $product, $date)
            ->map(fn ($slot) => $slot->startsAt->setTimezone($this->timezone)->format('H:i'))
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product>
     */
    #[Computed]
    public function products()
    {
        return $this->team->products()->active()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Customers matching the manual booking search, by name or email.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Customer>
     */
    #[Computed]
    public function customerMatches()
    {
        $query = trim($this->customerSearch);

        if (mb_strlen($query) < 2) {
            return collect();
        }

        return $this->team->customers()
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%');
            })
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function weekdays(): array
    {
        return [
            1 => __('Mon'),
            2 => __('Tue'),
            3 => __('Wed'),
            4 => __('Thu'),
            5 => __('Fri'),
            6 => __('Sat'),
            7 => __('Sun'),
        ];
    }

    public function previousMonth(): void
    {
        $this->changeMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->changeMonth(1);
    }

    public function goToday(): void
    {
        $today = CarbonImmutable::now($this->timezone);

        $this->month = $today->format('Y-m');
        $this->selectedDate = $today->toDateString();

        unset(
            $this->monthStart,
            $this->monthEnd,
            $this->calendarDays,
            $this->monthBookings,
            $this->monthBlocks,
            $this->dayBookings,
            $this->dayBlocks,
        );
    }

    public function selectDay(string $date): void
    {
        $this->selectedDate = $date;

        if (CarbonImmutable::parse($date)->format('Y-m') !== $this->month) {
            $this->month = CarbonImmutable::parse($date)->format('Y-m');

            unset(
                $this->monthStart,
                $this->monthEnd,
                $this->calendarDays,
                $this->monthBookings,
                $this->monthBlocks,
                $this->dayBookings,
                $this->dayBlocks,
            );
        }
    }

    public function setView(string $view): void
    {
        abort_unless(in_array($view, ['month', 'agenda', 'products'], true), 404);

        $this->view = $view;
    }

    public function openManualModal(?string $date = null): void
    {
        $this->manualForm->resetForm();
        $this->customerSearch = '';
        $this->manualForm->setDate($date ?? $this->selectedDate);
        $this->manualForm->product_id = $this->products->first()?->id;

        $this->openModal('manual-booking');
    }

    /**
     * Prefill the form with an existing customer. The email remains the
     * identity: editing it afterwards simply targets another customer.
     */
    public function selectCustomer(int $customerId): void
    {
        $customer = $this->team->customers()->findOrFail($customerId);

        $this->manualForm->name = (string) $customer->name;
        $this->manualForm->email = (string) $customer->email;
        $this->manualForm->phone = (string) $customer->phone;
        $this->customerSearch = '';
    }

    public function saveManualBooking(): void
    {
        try {
            $booking = $this->manualForm->save($this->team, app(BookingService::class), $this->timezone);
        } catch (SlotUnavailableException) {
            $this->addError('manualForm.start_time', __('That time is no longer available. Choose another one.'));

            return;
        }

        $this->selectedDate = $booking->starts_at->setTimezone($this->timezone)->toDateString();
        $this->month = $booking->starts_at->setTimezone($this->timezone)->format('Y-m');

        $this->refreshCalendar();

        $this->manualForm->resetForm();
        $this->customerSearch = '';

        $this->closeModal('manual-booking');

        $this->toast()->success(__('Booking created.'))->send();
    }

    public function openBlockModal(?string $date = null): void
    {
        $this->blockForm->resetForm();
        $this->blockForm->setDate($date ?? $this->selectedDate);

        $this->openModal('calendar-block');
    }

    public function saveBlock(): void
    {
        $this->blockForm->save($this->team, $this->timezone);

        $this->refreshCalendar();

        $this->blockForm->resetForm();

        $this->closeModal('calendar-block');

        $this->toast()->success(__('Calendar blocked.'))->send();
    }

    public function requestBlockDelete(int $blockId): void
    {
        $this->pendingBlockDeleteId = $blockId;

        $this->openModal('delete-block');
    }

    public function deleteBlock(): void
    {
        if ($this->pendingBlockDeleteId === null) {
            return;
        }

        $this->team->calendarBlocks()->findOrFail($this->pendingBlockDeleteId)->delete();

        $this->pendingBlockDeleteId = null;

        $this->refreshCalendar();

        $this->closeModal('delete-block');

        $this->toast()->success(__('Block removed.'))->send();
    }

    public function showBooking(int $bookingId): void
    {
        $this->selectedBookingId = $bookingId;

        $this->openModal('booking-detail');
    }

    public function confirmBooking(int $bookingId)
    {
        $booking = $this->team->bookings()->findOrFail($bookingId);

        if (! $booking->status->isPending()) {
            return null;
        }

        $method = $booking->status === BookingStatus::PendingBizum
            ? PaymentMethod::Bizum
            : PaymentMethod::Cash;

        $payment = app(BookingFulfilmentService::class)->pay(
            $booking,
            $booking->payment_kind ?? PaymentKind::Full,
            $method,
        );

        $this->refreshCalendar();

        $invoice = $payment->invoice()->first();

        if ($invoice !== null) {
            // The draft invoice is the next thing the photographer has to
            // review. If it is not needed, it can be deleted from there.
            return $this->redirectRoute('invoices.show', [
                'invoice' => $invoice,
                'created' => 1,
            ], navigate: true);
        }

        $this->selectedBookingId = $booking->id;

        $this->toast()->success(__('Booking confirmed. Payment recorded as received.'))->send();

        return null;
    }

    public function requestCancel(int $bookingId): void
    {
        $this->pendingCancelId = $bookingId;
        $this->cancelReason = '';

        $this->openModal('cancel-booking');
    }

    public function cancelBooking(): void
    {
        if ($this->pendingCancelId === null) {
            return;
        }

        $booking = $this->team->bookings()->findOrFail($this->pendingCancelId);

        app(BookingService::class)->cancel(
            $booking,
            $this->cancelReason !== '' ? $this->cancelReason : null,
        );

        $this->pendingCancelId = null;
        $this->cancelReason = '';
        $this->selectedBookingId = null;

        $this->refreshCalendar();

        $this->closeModal('cancel-booking');
        $this->closeModal('booking-detail');

        $this->toast()->success(__('Booking cancelled.'))->send();
    }

    public function formatAmount(int $minorUnits, string $currency): string
    {
        return Cashier::formatAmount($minorUnits, $currency);
    }

    public function statusLabel(BookingStatus $status): string
    {
        return $status->label();
    }

    public function statusColor(BookingStatus $status): string
    {
        return $status->color();
    }

    public function rendering(View $view): void
    {
        $view->title(__('Bookings'));
    }

    private function changeMonth(int $delta): void
    {
        $month = CarbonImmutable::parse($this->month.'-01', $this->timezone)
            ->startOfMonth()
            ->addMonthsNoOverflow($delta);

        $this->month = $month->format('Y-m');
        $this->selectedDate = $month->toDateString();

        // The month computeds were calculated for the previous month during
        // this request; without dropping them the first click looks ignored.
        unset(
            $this->monthStart,
            $this->monthEnd,
            $this->calendarDays,
            $this->monthBookings,
            $this->monthBlocks,
            $this->dayBookings,
            $this->dayBlocks,
        );
    }

    /**
     * Drop the cached calendar data after a write so the screen reflects it
     * without a full page reload.
     */
    private function refreshCalendar(): void
    {
        unset(
            $this->monthBookings,
            $this->monthBlocks,
            $this->calendarDays,
            $this->dayBookings,
            $this->dayBlocks,
            $this->upcoming,
            $this->availableSlots,
            $this->selectedBooking,
        );
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-ts-heading level="1" size="xl">{{ __('Bookings') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Your agenda, your availability and the sessions already sold.') }}</x-ts-subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ts-button :href="route('bookings.settings')" variant="secondary" icon="cog-6-tooth">
                {{ __('Settings') }}
            </x-ts-button>
            <x-ts-button type="button" variant="secondary" icon="no-symbol" wire:click="openBlockModal">
                {{ __('Block') }}
            </x-ts-button>
            <x-ts-button type="button" variant="primary" icon="plus" wire:click="openManualModal">
                {{ __('New booking') }}
            </x-ts-button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach (['month' => __('Month'), 'agenda' => __('Agenda'), 'products' => __('Products')] as $key => $label)
            <button
                type="button"
                wire:click="setView('{{ $key }}')"
                @class([
                    'rounded-lg border px-3 py-1.5 text-sm font-medium transition',
                    'border-brand-green bg-brand-green text-white' => $view === $key,
                    'border-zinc-200 text-brand-ink hover:bg-brand-sage-soft dark:border-white/10 dark:text-brand-sage dark:hover:bg-white/7' => $view !== $key,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($view === 'month')
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-ts-heading size="xl" level="2" class="capitalize">
                {{ $this->monthStart->translatedFormat('F Y') }}
            </x-ts-heading>

            <div class="flex items-center gap-2">
                <x-ts-button type="button" variant="ghost" icon="chevron-left" wire:click="previousMonth" />
                <x-ts-button type="button" variant="secondary" size="sm" wire:click="goToday">
                    {{ __('Today') }}
                </x-ts-button>
                <x-ts-button type="button" variant="ghost" icon="chevron-right" wire:click="nextMonth" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="border-brand-green/10 overflow-hidden rounded-xl border bg-white dark:border-white/10 dark:bg-white/3">
                <div class="grid grid-cols-7 border-b border-zinc-100 bg-zinc-50/70 dark:border-white/10 dark:bg-white/5">
                    @foreach ($this->weekdays as $label)
                        <div class="px-2 py-2 text-center text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                            {{ $label }}
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7" data-test="booking-calendar">
                    @foreach ($this->calendarDays as $day)
                        <button
                            type="button"
                            wire:key="day-{{ $day['date'] }}"
                            wire:click="selectDay('{{ $day['date'] }}')"
                            data-date="{{ $day['date'] }}"
                            @class([
                                'relative flex min-h-24 flex-col gap-1 border-r border-b border-zinc-100 p-2 text-left transition last:border-r-0 dark:border-white/10',
                                'bg-white dark:bg-transparent' => $day['inMonth'] && ! $day['blocked'],
                                'bg-zinc-50/70 text-zinc-400 dark:bg-white/3 dark:text-zinc-500' => ! $day['inMonth'],
                                'bg-zinc-100/80 dark:bg-white/5' => $day['inMonth'] && $day['blocked'],
                                'hover:bg-brand-sage-soft/70 dark:hover:bg-white/7' => $day['inMonth'] && ! $day['blocked'],
                                'ring-brand-green ring-2 ring-inset' => $day['isSelected'],
                            ])
                        >
                            <div class="flex items-center justify-between gap-1">
                                <span @class([
                                    'grid size-7 place-items-center rounded-full text-sm font-medium',
                                    'bg-brand-green text-white' => $day['isToday'],
                                    'text-brand-ink dark:text-white' => ! $day['isToday'] && $day['inMonth'],
                                ])>
                                    {{ $day['day'] }}
                                </span>

                                @if ($day['count'] > 0)
                                    <span class="rounded-full bg-zinc-900/70 px-1.5 py-0.5 text-[10px] font-semibold text-white dark:bg-white/20">
                                        {{ $day['count'] }}
                                    </span>
                                @endif
                            </div>

                            @if ($day['blocked'])
                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-zinc-500 dark:text-zinc-400">
                                    <x-icon name="no-symbol" variant="micro" class="size-3" />
                                    {{ __('Blocked') }}
                                </span>
                            @elseif ($day['partialBlock'])
                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-zinc-500 dark:text-zinc-400">
                                    <x-icon name="clock" variant="micro" class="size-3" />
                                    {{ __('Partially blocked') }}
                                </span>
                            @endif

                            <div class="mt-auto flex flex-wrap gap-1">
                                @foreach ($day['statuses'] as $status)
                                    @php($statusEnum = \App\Enums\BookingStatus::from($status))
                                    <span @class([
                                        'size-1.5 rounded-full',
                                        'bg-amber-500' => $statusEnum === \App\Enums\BookingStatus::PendingPayment || $statusEnum === \App\Enums\BookingStatus::PendingBizum,
                                        'bg-emerald-500' => $statusEnum === \App\Enums\BookingStatus::Confirmed,
                                        'bg-blue-500' => $statusEnum === \App\Enums\BookingStatus::Completed,
                                        'bg-red-500' => $statusEnum === \App\Enums\BookingStatus::NoShow,
                                        'bg-zinc-400' => $statusEnum === \App\Enums\BookingStatus::Cancelled,
                                    ])></span>
                                @endforeach
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4">
                <x-ts-card :heading="\Carbon\CarbonImmutable::parse($selectedDate, $this->timezone)->translatedFormat('l j F')">
                    <div class="flex items-center justify-between gap-2">
                        <x-ts-text variant="subtle" size="sm">
                            {{ trans_choice(':count session|:count sessions', $this->dayBookings->count(), ['count' => $this->dayBookings->count()]) }}
                        </x-ts-text>
                        <x-ts-button type="button" variant="ghost" size="sm" icon="plus" wire:click="openManualModal('{{ $selectedDate }}')">
                            {{ __('Add') }}
                        </x-ts-button>
                    </div>

                    @if ($this->dayBookings->isEmpty() && $this->dayBlocks->isEmpty())
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <x-icon name="calendar" class="mb-3 size-8 text-zinc-300 dark:text-zinc-600" />
                            <x-ts-text variant="subtle" size="sm">{{ __('Nothing scheduled.') }}</x-ts-text>
                        </div>
                    @else
                        <div class="mt-4 space-y-2">
                            @foreach ($this->dayBookings as $booking)
                                <button
                                    type="button"
                                    wire:key="day-booking-{{ $booking->id }}"
                                    wire:click="showBooking({{ $booking->id }})"
                                    class="hover:border-brand-green/40 flex w-full items-start gap-3 rounded-lg border border-zinc-200 p-3 text-left transition hover:bg-zinc-50 dark:border-white/10 dark:hover:bg-white/5"
                                >
                                    <div class="min-w-12 text-sm font-semibold">
                                        {{ $booking->starts_at->setTimezone($this->timezone)->format('H:i') }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-brand-ink truncate font-medium dark:text-white">{{ $booking->product_name }}</p>
                                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $booking->customer_name }}
                                        </p>
                                    </div>
                                    <x-ts-badge :color="$this->statusColor($booking->status)" size="sm">
                                        {{ $this->statusLabel($booking->status) }}
                                    </x-ts-badge>
                                </button>
                            @endforeach

                            @foreach ($this->dayBlocks as $block)
                                <div
                                    wire:key="day-block-{{ $block->id }}"
                                    class="flex items-center gap-3 rounded-lg border border-dashed border-zinc-300 p-3 dark:border-white/15"
                                >
                                    <x-icon name="no-symbol" class="size-4 text-zinc-400" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium">{{ $block->reason ?: __('Blocked') }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                            @if ($block->all_day)
                                                {{ __('All day') }}
                                            @else
                                                {{ $block->starts_at->setTimezone($this->timezone)->format('H:i') }}
                                                –
                                                {{ $block->ends_at->setTimezone($this->timezone)->format('H:i') }}
                                            @endif
                                        </p>
                                    </div>
                                    <x-ts-button type="button" variant="ghost" size="sm" icon="trash" wire:click="requestBlockDelete({{ $block->id }})" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ts-card>
            </div>
        </div>
    @elseif ($view === 'agenda')
        <x-ts-card :heading="__('Next sessions')">
            @if ($this->upcoming->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <x-icon name="calendar-days" class="mb-4 size-10 text-zinc-300 dark:text-zinc-600" />
                    <x-ts-heading size="lg">{{ __('No upcoming sessions') }}</x-ts-heading>
                    <x-ts-subheading class="mt-2 max-w-sm">
                        {{ __('When someone books, their session appears here.') }}
                    </x-ts-subheading>
                </div>
            @else
                @php($grouped = $this->upcoming->groupBy(fn ($booking) => $booking->starts_at->setTimezone($this->timezone)->toDateString()))
                <div class="space-y-6">
                    @foreach ($grouped as $date => $bookings)
                        <div wire:key="agenda-{{ $date }}">
                            <p class="mb-2 text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                {{ \Carbon\CarbonImmutable::parse($date, $this->timezone)->translatedFormat('l j F') }}
                            </p>
                            <div class="divide-y divide-zinc-100 dark:divide-white/10">
                                @foreach ($bookings as $booking)
                                    <button
                                        type="button"
                                        wire:key="agenda-booking-{{ $booking->id }}"
                                        wire:click="showBooking({{ $booking->id }})"
                                        class="flex w-full flex-wrap items-center gap-3 py-3 text-left"
                                    >
                                        <span class="w-14 text-sm font-semibold">
                                            {{ $booking->starts_at->setTimezone($this->timezone)->format('H:i') }}
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="text-brand-ink block truncate font-medium dark:text-white">{{ $booking->product_name }}</span>
                                            <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $booking->customer_name }}</span>
                                        </span>
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $this->formatAmount($booking->total_amount, $booking->currency) }}
                                        </span>
                                        <x-ts-badge :color="$this->statusColor($booking->status)" size="sm">
                                            {{ $this->statusLabel($booking->status) }}
                                        </x-ts-badge>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ts-card>
    @endif

    @if ($view === 'products')
        <livewire:product-manager />
    @endif

    <x-ts-modal name="manual-booking" class="w-full sm:max-w-lg">
        <form wire:submit="saveManualBooking" class="space-y-5">
            <div>
                <x-ts-heading size="lg">{{ __('New booking') }}</x-ts-heading>
                <x-ts-subheading>{{ __('For clients who book by phone, email or in person.') }}</x-ts-subheading>
            </div>

            @if ($this->products->isEmpty())
                <x-ts-callout variant="warning" icon="exclamation-triangle">
                    <x-ts-callout-text>
                        {{ __('You have no products yet. Create one in the booking settings first.') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('bookings.settings')" variant="secondary" size="sm">
                            {{ __('Settings') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @else
                <x-ts-select wire:model.live="manualForm.product_id" :label="__('Product')">
                    @foreach ($this->products as $product)
                        <x-ts-select-option value="{{ $product->id }}">
                            {{ $product->name }} · {{ $product->duration_minutes }} {{ __('min') }}
                        </x-ts-select-option>
                    @endforeach
                </x-ts-select>
            @endif

            <x-date wire:model.live="manualForm.date" :label="__('Date')" />

            @if (count($this->availableSlots) > 0)
                <x-ts-select wire:model="manualForm.start_time" :label="__('Start time')">
                    <x-ts-select-option value="">{{ __('Choose a time') }}</x-ts-select-option>
                    @foreach ($this->availableSlots as $slot)
                        <x-ts-select-option value="{{ $slot }}">{{ $slot }}</x-ts-select-option>
                    @endforeach
                </x-ts-select>
            @elseif ($manualForm->product_id !== null)
                <x-ts-callout variant="warning" icon="exclamation-triangle">
                    <x-ts-callout-text>
                        {{ __('There are no free times for this date. Check the schedule, the blocks or the other bookings.') }}
                    </x-ts-callout-text>
                </x-ts-callout>
            @endif

            @error('manualForm.start_time')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-white/10">
                <x-ts-input
                    wire:model.live.debounce.300ms="customerSearch"
                    :label="__('Search customer')"
                    icon="magnifying-glass"
                    :description="__('Type a name or email to reuse an existing customer. If it does not exist, it is created when you save.')"
                />

                @if ($this->customerMatches->isNotEmpty())
                    <div class="divide-y divide-zinc-100 overflow-hidden rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                        @foreach ($this->customerMatches as $customer)
                            <button
                                type="button"
                                wire:key="customer-match-{{ $customer->id }}"
                                wire:click="selectCustomer({{ $customer->id }})"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-white/5"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{{ $customer->name }}</span>
                                    <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $customer->email }}</span>
                                </span>
                                <x-icon name="arrow-right" class="size-4 shrink-0 text-zinc-400" />
                            </button>
                        @endforeach
                    </div>
                @elseif (mb_strlen(trim($customerSearch)) >= 2)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('No customer found. Fill the fields below and a new one will be created.') }}
                    </p>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ts-input wire:model="manualForm.name" :label="__('Name')" required />
                <x-ts-input wire:model="manualForm.email" type="email" :label="__('Email')" required />
                <x-ts-input wire:model="manualForm.phone" :label="__('Phone')" />
                <x-ts-input wire:model="manualForm.notes" :label="__('Notes')" />
            </div>

            <div class="flex items-center gap-3">
                <x-ts-spacer />
                <x-ts-button type="button" variant="secondary" wire:click="closeModal('manual-booking')">
                    {{ __('Cancel') }}
                </x-ts-button>
                <x-ts-button type="submit" variant="primary" :disabled="$manualForm->product_id === null || count($this->availableSlots) === 0">
                    {{ __('Create booking') }}
                </x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="calendar-block" class="w-full sm:max-w-lg">
        <form wire:submit="saveBlock" class="space-y-5">
            <div>
                <x-ts-heading size="lg">{{ __('Block time') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Holidays, personal appointments or a free afternoon.') }}</x-ts-subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-date wire:model.live="blockForm.starts_on" :label="__('From')" />
                <x-date wire:model.live="blockForm.ends_on" :label="__('To')" />
            </div>

            @error('blockForm.ends_on')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <x-ts-switch wire:model.live="blockForm.all_day" :label="__('All day')" />

            @if (! $blockForm->all_day)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-time wire:model="blockForm.start_time" :label="__('From')" format="24" />
                    <x-time wire:model="blockForm.end_time" :label="__('To')" format="24" />
                </div>

                @error('blockForm.end_time')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            @endif

            <x-ts-input wire:model="blockForm.reason" :label="__('Reason')" :description="__('Optional, only you see it.')" />

            <div class="flex items-center gap-3">
                <x-ts-spacer />
                <x-ts-button type="button" variant="secondary" wire:click="closeModal('calendar-block')">
                    {{ __('Cancel') }}
                </x-ts-button>
                <x-ts-button type="submit" variant="primary">{{ __('Block') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="booking-detail" variant="flyout">
        @if ($this->selectedBooking)
            @php($booking = $this->selectedBooking)
            <div class="space-y-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <x-ts-heading size="lg">{{ $booking->product_name }}</x-ts-heading>
                        <x-ts-subheading>
                            {{ $booking->starts_at->setTimezone($this->timezone)->translatedFormat('l j F, H:i') }}
                            – {{ $booking->ends_at->setTimezone($this->timezone)->format('H:i') }}
                        </x-ts-subheading>
                    </div>
                    <x-ts-badge :color="$this->statusColor($booking->status)">
                        {{ $this->statusLabel($booking->status) }}
                    </x-ts-badge>
                </div>

                <div class="space-y-2 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                    <p class="text-brand-ink font-medium dark:text-white">{{ $booking->customer_name }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $booking->customer_email }}</p>
                    @if ($booking->customer_phone)
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $booking->customer_phone }}</p>
                    @endif
                    @if ($booking->notes)
                        <p class="mt-2 border-t border-zinc-100 pt-2 text-sm dark:border-white/10">{{ $booking->notes }}</p>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <p class="text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</p>
                        <p class="font-semibold">{{ $this->formatAmount($booking->total_amount, $booking->currency) }}</p>
                    </div>
                    <div>
                        <p class="text-zinc-500 dark:text-zinc-400">{{ __('Deposit') }}</p>
                        <p class="font-semibold">{{ $this->formatAmount($booking->deposit_amount, $booking->currency) }}</p>
                    </div>
                    <div>
                        <p class="text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</p>
                        <p class="font-semibold">{{ $this->formatAmount($booking->balance_amount, $booking->currency) }}</p>
                    </div>
                </div>

                @if ($booking->status === \App\Enums\BookingStatus::PendingBizum)
                    <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-center dark:border-white/15">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Reference') }}</p>
                        <p class="text-2xl font-black tracking-widest">{{ $booking->bizumReference() }}</p>
                    </div>
                @endif

                @php($invoice = $booking->payments->pluck('invoice')->filter()->first())

                @if ($invoice)
                    <a
                        href="{{ route('invoices.show', $invoice) }}"
                        wire:navigate
                        class="border-brand-green/20 hover:bg-brand-sage-soft/50 flex items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm dark:border-white/10 dark:hover:bg-white/5"
                    >
                        <span class="flex items-center gap-2">
                            <x-icon name="document-text" class="size-4 text-zinc-400" />
                            {{ $invoice->isDraft() ? __('View draft invoice') : __('View invoice') }}
                        </span>
                        <x-ts-badge :color="$invoice->isPaid() ? 'lime' : 'zinc'" size="sm">
                            {{ $invoice->isPaid() ? __('Paid invoice') : $invoice->status_label }}
                        </x-ts-badge>
                    </a>
                @endif

                @if ($booking->status->isPending())
                    <div class="flex flex-wrap gap-2">
                        <x-ts-button type="button" variant="primary" wire:click="confirmBooking({{ $booking->id }})">
                            {{ $booking->status === \App\Enums\BookingStatus::PendingBizum ? __('Confirm Bizum received') : __('Confirm payment received') }}
                        </x-ts-button>
                        <x-ts-button type="button" variant="secondary" wire:click="requestCancel({{ $booking->id }})">
                            {{ __('Cancel booking') }}
                        </x-ts-button>
                    </div>
                @elseif ($booking->status === \App\Enums\BookingStatus::Confirmed)
                    <x-ts-button type="button" variant="secondary" wire:click="requestCancel({{ $booking->id }})">
                        {{ __('Cancel booking') }}
                    </x-ts-button>
                @endif
            </div>
        @endif
    </x-ts-modal>

    <x-ts-modal name="cancel-booking" class="w-full sm:max-w-md">
        <div class="space-y-4">
            <x-ts-heading size="lg">{{ __('Cancel booking') }}</x-ts-heading>
            <x-ts-text>
                {{ __('The slot is released immediately. Refunds and invoices are handled separately.') }}
            </x-ts-text>

            <x-ts-textarea
                wire:model="cancelReason"
                :label="__('Reason (optional)')"
                :description="__('It is included in the email sent to the client.')"
                rows="2"
            />

            <div class="flex items-center gap-3">
                <x-ts-spacer />
                <x-ts-button type="button" variant="secondary" wire:click="closeModal('cancel-booking')">
                    {{ __('Keep booking') }}
                </x-ts-button>
                <x-ts-button type="button" variant="danger" wire:click="cancelBooking">
                    {{ __('Cancel booking') }}
                </x-ts-button>
            </div>
        </div>
    </x-ts-modal>

    <x-ts-modal name="delete-block" class="w-full sm:max-w-md">
        <div class="space-y-4">
            <x-ts-heading size="lg">{{ __('Remove block') }}</x-ts-heading>
            <x-ts-text>{{ __('The time becomes bookable again.') }}</x-ts-text>
            <div class="flex items-center gap-3">
                <x-ts-spacer />
                <x-ts-button type="button" variant="secondary" wire:click="closeModal('delete-block')">
                    {{ __('Cancel') }}
                </x-ts-button>
                <x-ts-button type="button" variant="danger" wire:click="deleteBlock">
                    {{ __('Remove') }}
                </x-ts-button>
            </div>
        </div>
    </x-ts-modal>
</div>
