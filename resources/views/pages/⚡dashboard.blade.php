<?php

use App\Models\Invoice;
use App\Traits\FormatsMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use FormatsMoney;

    #[Computed]
    public function birthdaySoonCustomers()
    {
        return $this->team->customers()
            ->get()
            ->filter(fn ($customer) => $customer->isBirthdaySoon())
            ->sortBy(function ($customer) {
                $now = now()->startOfDay();
                $thisYearBirthday = $customer->birthdate->copy()->year($now->year);
                if ($thisYearBirthday->lt($now)) {
                    $thisYearBirthday->addYear();
                }

                return $now->diffInDays($thisYearBirthday, false);
            });
    }

    #[Computed]
    public function customersCount()
    {
        return $this->team->customers()->count();
    }

    #[Computed]
    public function expiringGalleries()
    {
        return $this->team->galleries()
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>=', now()->copy()->startOfDay())
            ->where('expiration_date', '<=', now()->addDays(7)->toDateString())
            ->orderBy('expiration_date')
            ->get();
    }

    #[Computed]
    public function galleriesCount()
    {
        return $this->team->galleries()->count();
    }

    #[Computed]
    public function incompleteSteps()
    {
        $dismissed = $this->team->dismissed_setup_steps ?? [];

        return collect($this->steps)
            ->where('complete', false)
            ->reject(fn ($step) => in_array($step['key'], $dismissed));
    }

    #[Computed]
    public function revenue30Days()
    {
        $start = Carbon::now()->subDays(30);

        return Cashier::formatAmount($this->team->payments()->where('completed_at', '>=', $start)->sum('amount'), $this->team->stripe_currency);
    }

    /**
     * Progress is based on the full checklist, including steps the user dismissed.
     *
     * @return array{completed: int, total: int, percent: int}
     */
    #[Computed]
    public function setupProgress(): array
    {
        $steps = collect($this->steps);
        $completed = $steps->where('complete', true)->count();
        $total = $steps->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    #[Computed]
    public function steps()
    {
        return [
            [
                'key' => 'branding',
                'label' => __('Configure branding settings'),
                'desc' => __('Upload your studio logos and visual identity.'),
                'action' => __('Configure branding'),
                'route' => route('branding.logos'),
                'icon' => 'paint-brush',
                'complete' => $this->isBrandingConfigured(),
            ],
            [
                'key' => 'watermark',
                'label' => __('Configure watermark settings'),
                'desc' => __('Protect your photos with custom watermarks.'),
                'action' => __('Configure watermark'),
                'route' => route('branding.watermark'),
                'icon' => 'sparkles',
                'complete' => $this->isWatermarkConfigured(),
            ],
            [
                'key' => 'portfolio',
                'label' => __('Set up your portfolio'),
                'desc' => __('Showcase your best work to attract clients.'),
                'action' => __('Set up portfolio'),
                'route' => route('portfolio'),
                'icon' => 'folder',
                'complete' => $this->isPortfolioComplete(),
            ],
            [
                'key' => 'biolink',
                'label' => __('Create your BioLink'),
                'desc' => __('Share a single link to all your socials and portfolio.'),
                'action' => __('Create BioLink'),
                'route' => route('public-profile'),
                'icon' => 'link',
                'complete' => $this->isBiolinkCreated(),
            ],
            [
                'key' => 'payments',
                'label' => __('Configure payment settings'),
                'desc' => __('Enable payments to get paid for your work.'),
                'action' => __('Configure payments'),
                'route' => route('branding.payments'),
                'icon' => 'credit-card',
                'complete' => $this->isPaymentsConfigured(),
            ],
        ];
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function totalGb()
    {
        return $this->team->hasUnlimitedStorage ? __('Unlimited') : $this->team->storage_limit_gb;
    }

    #[Computed]
    public function upcomingContractsAwaitingSignature()
    {
        return $this->team->contracts()
            ->whereNull('executed_at')
            ->orderBy('shooting_date')
            ->get();
    }

    #[Computed]
    public function upcomingPhotoshoots()
    {
        return $this->team->photoshoots()
            ->where('date', '>=', now()->copy()->startOfDay())
            ->with('customer')
            ->orderBy('date')
            ->get();
    }

    /**
     * Issued invoices that still have money pending.
     *
     * An invoice counts as paid either when the team marks it as paid or when
     * its linked payments settle the total, so the check runs in PHP over the
     * eager-loaded payments. Rectified invoices and their replacements are
     * skipped: the original is no longer collectable.
     *
     * The rows are fetched before the paid ones are dropped because the amount
     * settled by the linked payments is not a column we can filter on; the
     * query is bounded so a long-lived team cannot load an unbounded list.
     */
    #[Computed]
    public function unpaidInvoices()
    {
        return $this->team->invoices()
            ->where('status', Invoice::STATUS_ISSUED)
            ->whereNull('paid_at')
            ->whereNull('rectifies_invoice_id')
            ->with(['customer', 'payments'])
            ->orderByRaw('coalesce(due_date, issue_date) asc')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->reject(fn (Invoice $invoice) => $invoice->isPaid())
            ->take(25)
            ->values();
    }

    #[Computed]
    public function usagePercent()
    {
        return $this->team->hasUnlimitedStorage ? null : $this->team->storage_used_percent;
    }

    #[Computed]
    public function usedGb()
    {
        return $this->team->storage_used_gb;
    }

    #[Computed]
    public function user()
    {
        return Auth::user();
    }

    public function dismissStep(string $step)
    {
        $this->team->dismissSetupStep($step);
        $this->team->refresh();
    }

    public function formatEventDate($date)
    {
        return $date->isoFormat('MMM D, YYYY');
    }

    public function isToday($date)
    {
        return $date->isSameDay(now());
    }

    public function getUpcomingEventsAndReminders()
    {
        $events = collect();

        foreach ($this->birthdaySoonCustomers as $customer) {
            $now = now()->startOfDay();

            $birthday = $customer->birthdate->copy()->year($now->year);

            if ($birthday->lt($now)) {
                $birthday->addYear();
            }

            $isToday = $this->isToday($birthday);
            $age = $isToday ? $customer->age : $customer->age + 1;

            $events->push([
                'type' => 'birthday',
                'label' => $customer->name,
                'date' => $birthday,
                'link' => route('customers.show', $customer),
                'is_today' => $isToday,
                'icon' => 'cake',
                'badge' => $isToday ? __('Birthday today') : __('Birthday soon'),
                'badge_color' => $isToday ? 'green' : 'yellow',
                'badge_icon' => 'cake',
                'detail' => __('Turns :age', ['age' => $age]),
                'today_color' => 'green',
            ]);
        }

        foreach ($this->upcomingPhotoshoots as $photoshoot) {
            $isToday = $this->isToday($photoshoot->date);

            $events->push([
                'type' => 'photoshoot',
                'label' => $photoshoot->name,
                'date' => $photoshoot->date,
                'link' => route('photoshoots.show', ['photoshoot' => $photoshoot]),
                'is_today' => $isToday,
                'icon' => 'camera',
                'badge' => $isToday ? __('Scheduled today') : __('Scheduled'),
                'badge_color' => $isToday ? 'green' : 'blue',
                'badge_icon' => 'camera',
                'detail' => $photoshoot->customer?->name ?? $photoshoot->customer_name,
                'today_color' => 'green',
            ]);
        }

        foreach ($this->unpaidInvoices as $invoice) {
            $isOverdue = $invoice->due_date !== null && $invoice->due_date->isBefore(now()->startOfDay());
            $isToday = $invoice->due_date !== null && $this->isToday($invoice->due_date);

            $status = match (true) {
                $isOverdue => __('Overdue'),
                $isToday => __('Due today'),
                default => __('Payment pending'),
            };

            $events->push([
                'type' => 'invoice',
                'label' => $invoice->full_number ?? __('Invoice'),
                'date' => $invoice->due_date ?? $invoice->issue_date,
                'link' => route('invoices.show', $invoice),
                'is_today' => $isToday,
                'is_overdue' => $isOverdue,
                'icon' => 'receipt-percent',
                // The amount travels inside the badge: it only makes sense for
                // invoices, so a dedicated column would sit empty on every other
                // reminder.
                'badge' => $status.' · '.$this->money($invoice->pendingAmount()).' '.$invoice->currency,
                'badge_color' => match (true) {
                    $isOverdue => 'red',
                    $isToday => 'orange',
                    default => 'yellow',
                },
                'badge_icon' => $isOverdue ? 'exclamation-triangle' : 'receipt-percent',
                'detail' => $invoice->customer?->name,
                'today_color' => 'orange',
            ]);
        }

        foreach ($this->expiringGalleries as $gallery) {
            $isToday = $this->isToday($gallery->expiration_date);

            $events->push([
                'type' => 'gallery',
                'label' => $gallery->name,
                'date' => $gallery->expiration_date,
                'link' => route('galleries.show', ['gallery' => $gallery]),
                'is_today' => $isToday,
                'icon' => 'clock',
                'badge' => $isToday ? __('Expiring today') : __('Expiring soon'),
                'badge_color' => $isToday ? 'green' : 'red',
                'badge_icon' => 'clock',
                'detail' => null,
                'today_color' => 'green',
            ]);
        }

        foreach ($this->upcomingContractsAwaitingSignature as $contract) {
            $isToday = $this->isToday($contract->shooting_date);

            $events->push([
                'type' => 'contract',
                'label' => $contract->title ?? __('Contract'),
                'date' => $contract->shooting_date,
                'link' => route('contracts.show', ['contract' => $contract]),
                'is_today' => $isToday,
                'icon' => 'document-text',
                'badge' => __('Awaiting signature'),
                'badge_color' => 'orange',
                'badge_icon' => 'document-text',
                'detail' => $contract->location,
                'today_color' => 'green',
            ]);
        }

        return $events
            ->sortBy(fn (array $event) => [
                ($event['is_overdue'] ?? false) ? 0 : 1,
                $event['date']?->toDateString() ?? '9999-12-31',
            ])
            ->values();
    }

    public function hasUpcomingEventsOrReminders()
    {
        return
            $this->birthdaySoonCustomers?->isNotEmpty() ||
            $this->upcomingPhotoshoots?->isNotEmpty() ||
            $this->unpaidInvoices?->isNotEmpty() ||
            $this->expiringGalleries?->isNotEmpty() ||
            $this->upcomingContractsAwaitingSignature?->isNotEmpty();
    }

    public function isBiolinkCreated()
    {
        return $this->team->bioLinks()->exists();
    }

    public function isPaymentsConfigured()
    {
        return $this->team->hasCompletedOnboarding();
    }

    public function isPortfolioComplete()
    {
        return $this->team->galleries()->public()->exists();
    }

    public function isWatermarkConfigured()
    {
        return $this->team->hasBrandWatermark();
    }

    public function isBrandingConfigured()
    {
        return ! empty($this->team->brand_logo_path);
    }
} ?>

<div>
    <livewire:storage-alert-banner />

    <div class="flex items-center gap-3">
        <span class="bg-brand-coral h-10 w-1 rounded-full"></span>

        <div>
            <x-ts-heading size="xl" level="1" class="text-brand-ink dark:text-brand-cream font-semibold">
                {{ __('Welcome back, :name', ['name' => $this->user->name]) }}
            </x-ts-heading>
            <x-ts-text class="text-brand-ink/75 dark:text-brand-sage/85 mt-0.5 font-medium">
                {{ __('A quick look at your studio.') }}
            </x-ts-text>
        </div>
    </div>

    <x-ts-spacer class="my-6" />

    <x-ts-modal-trigger name="search" shortcut="cmd.k">
        <x-ts-input as="button" :placeholder="__('Search...')" icon="magnifying-glass" kbd="⌘K" />
    </x-ts-modal-trigger>

    <x-ts-spacer class="my-6" />

    @if (! $this->team->subscribed())
        <section
            class="border-brand-green/15 bg-brand-sage/20 dark:bg-brand-sage/5 relative overflow-hidden rounded-2xl border px-6 py-6 sm:px-8 dark:border-white/10"
        >
            <div
                class="bg-brand-sage/40 dark:bg-brand-green/20 pointer-events-none absolute -top-16 -right-12 size-48 rounded-full blur-3xl"
            ></div>

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="text-brand-green dark:text-brand-sage flex size-11 shrink-0 items-center justify-center rounded-xl bg-white shadow-xs dark:bg-white/10"
                    >
                        <x-icon name="shield-check" class="size-5" />
                    </div>

                    <div class="max-w-2xl">
                        <x-ts-heading size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                            {{ __('Get More With Picstome Pro') }}
                        </x-ts-heading>
                        <x-ts-text class="text-brand-ink/80 dark:text-brand-sage/85 mt-2 font-medium">
                            {{ __('Unlock 1000GB storage, galleries that never expire, unlimited contracts, invoices and payments, and white label branding. Upgrade to Pro and power up your business.') }}
                        </x-ts-text>
                    </div>
                </div>

                <x-ts-button :href="route('subscribe')" variant="primary" class="shrink-0">
                    {{ __('Upgrade to Pro') }}
                </x-ts-button>
            </div>
        </section>
    @endif

    <x-ts-spacer class="my-6" />

    @if ($this->incompleteSteps->count())
        <section>
            <div
                class="border-brand-green/10 overflow-hidden rounded-2xl border bg-white shadow-xs dark:border-white/10 dark:bg-white/5"
            >
                <div class="border-brand-green/10 border-b px-5 py-4 dark:border-white/10">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <x-ts-heading size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                            {{ __('Complete Your Account Setup') }}
                        </x-ts-heading>

                        <span
                            class="bg-brand-sage/30 text-brand-green dark:bg-brand-sage/10 dark:text-brand-sage inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium tabular-nums"
                        >
                            {{ $this->setupProgress['completed'] }}/{{ $this->setupProgress['total'] }}
                        </span>
                    </div>

                    <div class="bg-brand-sage/30 mt-3 h-1.5 w-full overflow-hidden rounded-full dark:bg-white/10">
                        <div
                            class="bg-brand-green dark:bg-brand-sage h-full rounded-full transition-all duration-300"
                            style="width: {{ $this->setupProgress['percent'] }}%"
                        ></div>
                    </div>
                </div>

                <div class="divide-brand-green/10 divide-y dark:divide-white/10">
                    @foreach ($this->incompleteSteps as $step)
                        <div
                            class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center"
                            wire:key="setup-{{ $step['key'] }}"
                        >
                            <div
                                class="bg-brand-sage/30 text-brand-green dark:bg-brand-sage/10 dark:text-brand-sage flex size-10 shrink-0 items-center justify-center rounded-xl"
                            >
                                <x-ts-icon :icon="$step['icon']" class="size-5" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <x-ts-text variant="strong" class="font-medium">{{ $step['label'] }}</x-ts-text>
                                <x-ts-text class="text-brand-ink/75 dark:text-brand-sage/80 mt-0.5 text-xs font-medium">
                                    {{ $step['desc'] }}
                                </x-ts-text>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-ts-button
                                    size="sm"
                                    :href="$step['route']"
                                    variant="ghost"
                                    icon:trailing="arrow-right"
                                    class="text-brand-green! hover:bg-brand-green/5! dark:text-brand-sage! dark:hover:bg-white/10!"
                                >
                                    {{ $step['action'] }}
                                </x-ts-button>

                                <x-ts-button
                                    icon="x-mark"
                                    variant="subtle"
                                    size="sm"
                                    wire:click="dismissStep('{{ $step['key'] }}')"
                                    :aria-label="__('Dismiss')"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <x-ts-spacer class="my-6" />
    @endif

    <section>
        <div class="space-y-4">
            <x-ts-heading size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                {{ __('Account Overview') }}
            </x-ts-heading>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                              [
                                  'label' => __('Customers'),
                                  'value' => $this->customersCount,
                                  'icon' => 'user-group',
                                  'route' => route('customers'),
                                  'tone' => 'bg-brand-sage/35 text-brand-green dark:bg-brand-sage/10 dark:text-brand-sage',
                              ],
                              [
                                  'label' => __('Galleries'),
                                  'value' => $this->galleriesCount,
                                  'icon' => 'photo',
                                  'route' => route('galleries'),
                                  'tone' => 'bg-brand-coral/15 text-brand-coral-deep dark:bg-brand-coral/10 dark:text-brand-coral',
                              ],
                              [
                                  'label' => __('Revenue (30d)'),
                                  'value' => $this->revenue30Days,
                                  'icon' => 'banknotes',
                                  'route' => route('payments'),
                                  'tone' => 'bg-brand-ink text-brand-cream',
                              ],
                          ] as $card)
                    <a
                        href="{{ $card['route'] }}"
                        wire:navigate
                        class="group border-brand-green/10 hover:border-brand-green/25 relative flex flex-col rounded-xl border bg-white p-5 shadow-xs transition duration-200 hover:-translate-y-0.5 hover:shadow-md motion-reduce:transition-none motion-reduce:hover:translate-y-0 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20 dark:hover:bg-white/10"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <span class="{{ $card['tone'] }} flex size-10 items-center justify-center rounded-xl">
                                <x-ts-icon :icon="$card['icon']" class="size-5" />
                            </span>

                            <x-icon name="arrow-up-right"
                                class="text-brand-green/40 group-hover:text-brand-green dark:text-brand-sage/50 dark:group-hover:text-brand-sage size-4 transition"
                            />
                        </div>

                        <div class="mt-5">
                            <x-ts-text
                                class="text-brand-green-deep dark:text-brand-sage text-xs font-semibold tracking-wide uppercase"
                            >
                                {{ $card['label'] }}
                            </x-ts-text>
                            <x-ts-heading size="xl" class="mt-1 tabular-nums">
                                {{ $card['value'] }}
                            </x-ts-heading>
                        </div>
                    </a>
                @endforeach

                <div
                    class="border-brand-green/10 flex flex-col rounded-xl border bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5"
                >
                    <div class="flex items-start justify-between gap-4">
                        <span
                            class="bg-brand-green text-brand-cream flex size-10 items-center justify-center rounded-xl"
                        >
                            <x-icon name="server" class="size-5" />
                        </span>

                        @if ($this->team->hasUnlimitedStorage)
                            <span
                                class="bg-brand-sage/30 text-brand-green dark:bg-brand-sage/10 dark:text-brand-sage rounded-full px-2 py-1 text-[11px] font-medium"
                            >
                                {{ __('Unlimited') }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-5">
                        <x-ts-text
                            class="text-brand-green-deep dark:text-brand-sage text-xs font-semibold tracking-wide uppercase"
                        >
                            {{ __('Storage Used') }}
                        </x-ts-text>
                        <x-ts-heading size="xl" class="mt-1 tabular-nums">{{ $this->usedGb }}</x-ts-heading>
                    </div>

                    <div class="mt-auto pt-4">
                        @if (! $this->team->hasUnlimitedStorage)
                            @php
                                $storageTone = $this->usagePercent > 90 ? 'bg-brand-coral' : ($this->usagePercent > 75 ? 'bg-amber-400' : 'bg-brand-green dark:bg-brand-sage');
                            @endphp

                            <div class="bg-brand-sage/30 h-1.5 w-full overflow-hidden rounded-full dark:bg-white/10">
                                <div
                                    class="{{ $storageTone }} h-full rounded-full transition-all duration-300"
                                    style="width: {{ min($this->usagePercent, 100) }}%"
                                ></div>
                            </div>
                            <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-2 text-[11px] font-medium">
                                {{ __(':used of :total used', ['used' => $this->usedGb, 'total' => $this->totalGb]) }}
                            </x-ts-text>
                        @else
                            <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 text-[11px] font-medium">
                                {{ __(':used used (Unlimited)', ['used' => $this->usedGb]) }}
                            </x-ts-text>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($this->hasUpcomingEventsOrReminders())
        @php
            $events = $this->getUpcomingEventsAndReminders();
        @endphp

        <section class="mt-8 mb-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <x-ts-heading size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                        {{ __('Upcoming Events & Reminders') }}
                    </x-ts-heading>
                    <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-0.5 text-sm font-medium">
                        {{ __('Birthdays, shoots, invoices and galleries that need your attention.') }}
                    </x-ts-text>
                </div>

                <span
                    class="bg-brand-sage/30 text-brand-green dark:bg-brand-sage/10 dark:text-brand-sage inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium tabular-nums"
                >
                    {{ trans_choice(':count reminder|:count reminders', $events->count()) }}
                </span>
            </div>

            <div
                class="border-brand-green/10 mt-4 overflow-hidden rounded-2xl border bg-white shadow-xs [&_table]:table-auto [&_td:first-child]:pl-4 [&_td:last-child]:pr-4 [&_th:first-child]:pl-4 [&_th:last-child]:pr-4 dark:border-white/10 dark:bg-white/5"
            >
                <x-data-table>
                    <x-data-table.columns
                        class="border-brand-green/10 bg-brand-sage/15 border-b dark:border-white/10 dark:bg-white/5"
                    >
                        <x-data-table.column class="w-full">{{ __('Event') }}</x-data-table.column>
                        <x-data-table.column class="hidden md:table-cell">{{ __('Status') }}</x-data-table.column>
                        <x-data-table.column class="group/right-align">{{ __('Date') }}</x-data-table.column>
                    </x-data-table.columns>

                    <x-data-table.rows>
                        @foreach ($events as $event)
                            @php
                                $isToday = ! empty($event['is_today']);
                                $isOverdue = ! empty($event['is_overdue']);

                                $iconTone = match ($event['type']) {
                                    'birthday' => 'bg-brand-coral/15 text-brand-coral-deep dark:bg-brand-coral/10 dark:text-brand-coral',
                                    'photoshoot' => 'bg-brand-sage/35 text-brand-green dark:bg-brand-sage/10 dark:text-brand-sage',
                                    'invoice' => 'bg-brand-ink text-brand-cream',
                                    'gallery' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',
                                    default => 'bg-brand-green/10 text-brand-green dark:bg-brand-sage/10 dark:text-brand-sage',
                                };
                            @endphp

                            <x-data-table.row
                                :class="implode(' ', array_filter([
                                    'group relative transition hover:bg-brand-sage/10 dark:hover:bg-white/5',
                                    $isToday ? 'bg-brand-sage/20 dark:bg-brand-sage/10' : null,
                                    $isOverdue ? 'bg-brand-coral/5 dark:bg-brand-coral/5' : null,
                                ]))"
                            >
                                <x-data-table.cell variant="strong" class="relative">
                                    <a
                                        href="{{ $event['link'] }}"
                                        wire:navigate
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>

                                    <div class="flex items-center gap-3">
                                        <span
                                            class="{{ $iconTone }} flex size-9 shrink-0 items-center justify-center rounded-xl"
                                        >
                                            <x-icon :name="$event['icon']" class="size-4" />
                                        </span>

                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="truncate">{{ $event['label'] }}</span>

                                                <span class="md:hidden">
                                                    <x-ts-badge
                                                        :color="$event['badge_color']"
                                                        :icon="$event['badge_icon']"
                                                        inset="top bottom"
                                                        size="sm"
                                                    >
                                                        {{ $event['badge'] }}
                                                    </x-ts-badge>
                                                </span>
                                            </div>

                                            @if (filled($event['detail']))
                                                <x-ts-text class="mt-0.5 truncate text-xs font-medium">
                                                    {{ $event['detail'] }}
                                                </x-ts-text>
                                            @endif
                                        </div>
                                    </div>
                                </x-data-table.cell>

                                <x-data-table.cell class="relative hidden md:table-cell">
                                    <a
                                        href="{{ $event['link'] }}"
                                        wire:navigate
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>

                                    <x-ts-badge
                                        :color="$event['badge_color']"
                                        :icon="$event['badge_icon']"
                                        inset="top bottom"
                                        size="sm"
                                    >
                                        {{ $event['badge'] }}
                                    </x-ts-badge>
                                </x-data-table.cell>

                                <x-data-table.cell class="relative text-end" variant="muted">
                                    <a
                                        href="{{ $event['link'] }}"
                                        wire:navigate
                                        class="absolute inset-0 focus:outline-hidden"
                                    ></a>

                                    @if ($isToday)
                                        <x-ts-badge
                                            :color="$event['today_color']"
                                            size="sm"
                                            inset="top bottom"
                                        >
                                            {{ __('Today') }}
                                        </x-ts-badge>
                                    @elseif ($event['date'] !== null)
                                        <span class="whitespace-nowrap tabular-nums">
                                            {{ $this->formatEventDate($event['date']) }}
                                        </span>
                                    @else
                                        <span class="text-brand-ink/30 dark:text-brand-sage/40">—</span>
                                    @endif
                                </x-data-table.cell>
                            </x-data-table.row>
                        @endforeach
                    </x-data-table.rows>
                </x-data-table>
            </div>
        </section>
    @endif
</div>
