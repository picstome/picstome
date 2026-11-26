<?php

use App\Livewire\Forms\BookingSettingsForm;
use App\Models\BookingSetting;
use App\Models\CalendarBlock;
use App\Models\Team;
use App\Support\MonthGrid;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

    /** schedule | rules | products | payments */
    #[Url]
    public string $tab = 'schedule';

    /** weekly | monthly */
    public string $scheduleView = 'weekly';

    public string $blockMonth = '';

    public BookingSettingsForm $settingsForm;

    public function mount(): void
    {
        if (! in_array($this->tab, ['schedule', 'rules', 'products', 'payments'], true)) {
            $this->tab = 'schedule';
        }

        $this->settingsForm->setTeam($this->team);

        $this->blockMonth = CarbonImmutable::now($this->settingsForm->timezone)->format('Y-m');
    }

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function settings(): BookingSetting
    {
        return BookingSetting::forTeam($this->team);
    }

    /**
     * European timezones cover every photographer the app serves today,
     * without a 400-entry select.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function timezones(): array
    {
        $zones = collect(DateTimeZone::listIdentifiers(DateTimeZone::EUROPE))
            ->merge(['Atlantic/Canary', 'UTC'])
            ->unique()
            ->sort()
            ->values();

        return $zones->mapWithKeys(fn (string $zone) => [$zone => str_replace('_', ' ', $zone)])->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function days(): array
    {
        return [
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
        ];
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['schedule', 'rules', 'products', 'payments'], true), 404);

        $this->tab = $tab;
    }

    public function setScheduleView(string $view): void
    {
        abort_unless(in_array($view, ['weekly', 'monthly'], true), 404);

        $this->scheduleView = $view;
    }

    #[Computed]
    public function blockMonthStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->blockMonth.'-01', $this->settingsForm->timezone)->startOfMonth();
    }

    /**
     * Month grid for the monthly availability editor.
     *
     * @return array<int, array{date: string, day: int, inMonth: bool, blocked: bool, isToday: bool}>
     */
    #[Computed]
    public function blockDays(): array
    {
        $blocks = $this->team->calendarBlocks()
            ->overlapping($this->blockMonthStart->utc(), $this->blockMonthStart->endOfMonth()->utc())
            ->get();

        $days = [];

        foreach (app(MonthGrid::class)->days($this->blockMonthStart) as $date) {
            $dayStart = $date->startOfDay();
            $dayEnd = $date->endOfDay()->startOfSecond();

            $blocked = $blocks->contains(
                fn (CalendarBlock $block) => $block->coversDay($dayStart, $dayEnd),
            );

            $days[] = [
                'date' => $date->toDateString(),
                'day' => $date->day,
                'inMonth' => $date->month === $this->blockMonthStart->month,
                'blocked' => $blocked,
                'isToday' => $date->isToday(),
            ];
        }

        return $days;
    }

    public function previousBlockMonth(): void
    {
        $this->changeBlockMonth(-1);
    }

    public function nextBlockMonth(): void
    {
        $this->changeBlockMonth(1);
    }

    public function toggleBlockedDay(string $date): void
    {
        $timezone = $this->settingsForm->timezone;
        $dayStart = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->endOfDay()->startOfSecond();

        $existing = $this->team->calendarBlocks()
            ->overlapping($dayStart->utc(), $dayEnd->utc())
            ->get()
            ->filter(
                fn (CalendarBlock $block) => $block->coversDay($dayStart, $dayEnd),
            );

        $wasBlocked = $existing->isNotEmpty();

        if ($wasBlocked) {
            $existing->each->delete();
        } else {
            $this->team->calendarBlocks()->create([
                'starts_at' => $dayStart,
                'ends_at' => $dayEnd,
                'all_day' => true,
            ]);
        }

        unset($this->blockDays);

        $this->toast()->success($wasBlocked ? __('Day unblocked.') : __('Day blocked.'))->send();
    }

    private function changeBlockMonth(int $delta): void
    {
        $month = $this->blockMonthStart->addMonthsNoOverflow($delta);

        $this->blockMonth = $month->format('Y-m');

        unset($this->blockMonthStart, $this->blockDays);
    }

    public function copyMondayToAll(): void
    {
        $this->settingsForm->copyMondayToAll();

        $this->toast()->success(__('Monday copied to every day.'))->send();
    }

    public function saveSettings(): void
    {
        $this->settingsForm->save($this->team);

        $this->settingsForm->setTeam($this->team);

        unset($this->settings);

        $this->toast()->success(__('Booking settings saved.'))->send();
    }

    public function rendering(View $view): void
    {
        $view->title(__('Booking settings'));
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-ts-heading level="1" size="xl">{{ __('Booking settings') }}</x-ts-heading>
            <x-ts-subheading>{{ __('When clients can book you and how much they pay upfront.') }}</x-ts-subheading>
        </div>
        <x-ts-button :href="route('bookings')" variant="secondary" icon="calendar-days">
            {{ __('Calendar') }}
        </x-ts-button>
    </div>

    <div class="border-brand-green/10 flex gap-1 overflow-x-auto border-b pb-1 dark:border-white/10" role="tablist">
        @foreach ([
            'schedule' => __('Schedule'),
            'rules' => __('Rules'),
            'products' => __('Products'),
            'payments' => __('Payments and emails'),
        ] as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                role="tab"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class([
                    'rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition',
                    'bg-brand-green text-white' => $tab === $key,
                    'text-brand-ink hover:bg-brand-sage-soft dark:text-brand-sage dark:hover:bg-white/7' => $tab !== $key,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'schedule')
        <x-ts-card :heading="__('Availability')">
            <div class="mb-5 flex flex-wrap items-center gap-2">
                @foreach (['weekly' => __('Weekly'), 'monthly' => __('Monthly')] as $key => $label)
                    <button
                        type="button"
                        wire:click="setScheduleView('{{ $key }}')"
                        @class([
                            'rounded-lg border px-3 py-1.5 text-sm font-medium transition',
                            'border-brand-green bg-brand-green text-white' => $scheduleView === $key,
                            'border-zinc-200 text-brand-ink hover:bg-brand-sage-soft dark:border-white/10 dark:text-brand-sage dark:hover:bg-white/7' => $scheduleView !== $key,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($scheduleView === 'weekly')
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <x-ts-text variant="subtle" size="sm">
                        {{ __('Sessions are only offered inside these windows. Leave a day off to close it.') }}
                    </x-ts-text>
                    <x-ts-button type="button" variant="secondary" size="sm" wire:click="copyMondayToAll" icon="clipboard-document">
                        {{ __('Copy Monday to all') }}
                    </x-ts-button>
                </div>

                <div class="mt-4 divide-y divide-zinc-100 dark:divide-white/10">
                    @foreach ($this->days as $weekday => $day)
                        <div class="flex flex-wrap items-center gap-3 py-3" wire:key="window-{{ $weekday }}">
                            <div class="min-w-32">
                                <x-ts-switch wire:model.live="settingsForm.windows.{{ $weekday }}.enabled" :label="$day" />
                            </div>

                            @if ($settingsForm->windows[$weekday]['enabled'] ?? false)
                                <div class="flex items-end gap-3">
                                    <div class="w-28">
                                        <x-time wire:model="settingsForm.windows.{{ $weekday }}.start" :label="__('From')" format="24" />
                                    </div>
                                    <div class="w-28">
                                        <x-time wire:model="settingsForm.windows.{{ $weekday }}.end" :label="__('To')" format="24" />
                                    </div>
                                </div>
                                @error("settingsForm.windows.{$weekday}.end")
                                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            @else
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Closed') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end">
                    <x-ts-button type="button" variant="primary" wire:click="saveSettings">
                        {{ __('Save changes') }}
                    </x-ts-button>
                </div>
            @else
                <div class="flex items-center justify-between gap-3">
                    <x-ts-button type="button" variant="ghost" icon="chevron-left" wire:click="previousBlockMonth" />
                    <span class="text-sm font-semibold capitalize">
                        {{ $this->blockMonthStart->translatedFormat('F Y') }}
                    </span>
                    <x-ts-button type="button" variant="ghost" icon="chevron-right" wire:click="nextBlockMonth" />
                </div>

                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Click a day to block it. Click again to unblock it.') }}
                </p>

                <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-zinc-400">
                    @foreach ([__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')] as $day)
                        <div class="py-1">{{ $day }}</div>
                    @endforeach
                </div>

                <div class="mt-1 grid grid-cols-7 gap-1">
                    @foreach ($this->blockDays as $day)
                        <button
                            type="button"
                            wire:key="block-day-{{ $day['date'] }}"
                            wire:click="toggleBlockedDay('{{ $day['date'] }}')"
                            @class([
                                'flex h-9 items-center justify-center rounded-lg text-xs transition',
                                'bg-brand-coral/15 text-brand-coral-deep line-through dark:bg-brand-coral/10 dark:text-brand-coral' => $day['blocked'],
                                'text-zinc-300 dark:text-zinc-600' => ! $day['blocked'] && ! $day['inMonth'],
                                'text-brand-ink hover:bg-brand-sage-soft dark:text-white dark:hover:bg-white/10' => ! $day['blocked'] && $day['inMonth'],
                                'ring-brand-green/50 ring-1' => $day['isToday'] && ! $day['blocked'],
                            ])
                        >
                            {{ $day['day'] }}
                        </button>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Blocked days are not offered to clients. You can also block partial days from the agenda.') }}
                </p>
            @endif
        </x-ts-card>
    @endif

    @if ($tab === 'rules')
        <x-ts-card :heading="__('Booking rules')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ts-select wire:model="settingsForm.timezone" :label="__('Timezone')">
                    @foreach ($this->timezones as $zone => $label)
                        <x-ts-select-option value="{{ $zone }}">{{ $label }}</x-ts-select-option>
                    @endforeach
                </x-ts-select>

                <div>
                    <x-ts-input
                        wire:model="settingsForm.slot_step_minutes"
                        type="number"
                        min="5"
                        max="120"
                        :label="__('Start times every')"
                        :description="__('Minutes between two possible start times.')"
                    />
                </div>

                <div>
                    <x-ts-input
                        wire:model="settingsForm.buffer_minutes"
                        type="number"
                        min="0"
                        max="240"
                        :label="__('Buffer between sessions')"
                        :description="__('Minutes kept free after each session.')"
                    />
                </div>

                <div>
                    <x-ts-input
                        wire:model="settingsForm.min_notice_hours"
                        type="number"
                        min="0"
                        max="720"
                        :label="__('Minimum notice')"
                        :description="__('Clients cannot book with less notice than these hours.')"
                    />
                </div>

                <div>
                    <x-ts-input
                        wire:model="settingsForm.horizon_days"
                        type="number"
                        min="1"
                        max="365"
                        :label="__('Book up to')"
                        :description="__('How many days ahead the calendar is open.')"
                    />
                </div>

                <div>
                    <x-ts-input
                        wire:model="settingsForm.max_sessions_per_day"
                        type="number"
                        min="1"
                        max="50"
                        :label="__('Maximum sessions per day')"
                        :description="__('Leave empty for no limit.')"
                    />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <x-ts-button type="button" variant="primary" wire:click="saveSettings">
                    {{ __('Save changes') }}
                </x-ts-button>
            </div>
        </x-ts-card>
    @endif

    @if ($tab === 'products')
        <livewire:product-manager />
    @endif

    @if ($tab === 'payments')
        <x-ts-card :heading="__('Payment methods')">
            <div class="space-y-4">
                <x-ts-switch
                    wire:model.live="settingsForm.card_enabled"
                    :label="__('Card payments (Stripe)')"
                    :description="__('The client pays online with a card. The money goes to your connected Stripe account.')"
                />
                @include('partials.client-payment-settings-fields', ['model' => 'settingsForm'])
            </div>

            @error('settingsForm.card_enabled')
                <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="mt-6 flex justify-end">
                <x-ts-button type="button" variant="primary" wire:click="saveSettings">
                    {{ __('Save changes') }}
                </x-ts-button>
            </div>
        </x-ts-card>

        <x-ts-card :heading="__('Deposit')" class="mt-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ts-select wire:model.live="settingsForm.default_deposit_type" :label="__('Default deposit')">
                    <x-ts-select-option value="none">{{ __('No deposit, the client pays the full price') }}</x-ts-select-option>
                    <x-ts-select-option value="fixed">{{ __('A fixed amount') }}</x-ts-select-option>
                    <x-ts-select-option value="percent">{{ __('A percentage of the price') }}</x-ts-select-option>
                </x-ts-select>

                @if ($settingsForm->default_deposit_type !== 'none')
                    <x-ts-input
                        wire:model="settingsForm.default_deposit_value"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="$settingsForm->default_deposit_type === 'percent' ? __('Percentage') : __('Deposit amount')"
                        :description="$settingsForm->default_deposit_type === 'percent'
                            ? __('For example 30 for a 30% deposit.')
                            : __('For example 50 for a 50 € deposit.')"
                    />
                @endif
            </div>

            <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Each product can override this. The second payment is requested later with its own link.') }}
            </p>

            <div class="mt-6 flex justify-end">
                <x-ts-button type="button" variant="primary" wire:click="saveSettings">
                    {{ __('Save changes') }}
                </x-ts-button>
            </div>
        </x-ts-card>

        <x-ts-card :heading="__('Reminders')" class="mt-6">
            <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Emails sent to the client before the session.') }}
            </p>

            <div class="space-y-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-44">
                        <x-ts-input
                            wire:model="settingsForm.reminder_first_hours"
                            type="number"
                            min="1"
                            max="720"
                            :label="__('First reminder')"
                            :description="__('Hours before the session.')"
                        />
                    </div>
                    <x-ts-switch wire:model.live="settingsForm.reminder_first_enabled" :label="__('Send it')" />
                </div>

                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-44">
                        <x-ts-input
                            wire:model="settingsForm.reminder_second_hours"
                            type="number"
                            min="1"
                            max="720"
                            :label="__('Second reminder')"
                            :description="__('Hours before the session.')"
                        />
                    </div>
                    <x-ts-switch wire:model.live="settingsForm.reminder_second_enabled" :label="__('Send it')" />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <x-ts-button type="button" variant="primary" wire:click="saveSettings">
                    {{ __('Save changes') }}
                </x-ts-button>
            </div>
        </x-ts-card>

        <x-ts-card :heading="__('Invoicing')" class="mt-6">
            <x-ts-switch
                wire:model.live="settingsForm.auto_issue_invoices"
                :label="__('Issue the invoice automatically when the payment is collected')"
                :description="__('If your fiscal data is incomplete, the invoice stays as a draft.')"
            />

            <div class="mt-6 flex justify-end">
                <x-ts-button type="button" variant="primary" wire:click="saveSettings">
                    {{ __('Save changes') }}
                </x-ts-button>
            </div>
        </x-ts-card>

        <x-ts-card :heading="__('Cancellation policy')" class="mt-6">
            <x-ts-textarea
                wire:model="settingsForm.cancellation_policy"
                :label="__('Policy shown to the client')"
                :description="__('The client accepts a copy of this text when booking. Editing it later does not change past bookings.')"
                rows="6"
            />

            <div class="mt-6 flex justify-end">
                <x-ts-button type="button" variant="primary" wire:click="saveSettings">
                    {{ __('Save changes') }}
                </x-ts-button>
            </div>
        </x-ts-card>
    @endif

</div>
