<?php

use App\Services\CalculatorService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public array $settings = [];

    public array $fixed_expenses = [];

    public array $session_expenses = [];

    public array $equipment = [];

    public array $session_tasks = [];

    public array $monthly_tasks = [];

    public string $tab = 'fixed';

    public string $newFixedName = '';

    public string $newFixedAmount = '';

    public string $newSessionName = '';

    public string $newSessionAmount = '';

    public string $newEquipmentName = '';

    public string $newEquipmentPrice = '';

    public string $newEquipmentResale = '0';

    public string $newEquipmentLife = '5';

    public string $newSessionTaskName = '';

    public string $newSessionTaskHours = '';

    public string $newMonthlyTaskName = '';

    public string $newMonthlyTaskHours = '';

    public function mount(): void
    {
        $this->loadState(CalculatorService::forTeam(Auth::user()->currentTeam));
    }

    public function updated(string $property): void
    {
        if (
            str_starts_with($property, 'settings.')
            || str_starts_with($property, 'fixed_expenses.')
            || str_starts_with($property, 'session_expenses.')
            || str_starts_with($property, 'equipment.')
            || str_starts_with($property, 'session_tasks.')
            || str_starts_with($property, 'monthly_tasks.')
        ) {
            $this->persist();
        }
    }

    public function addFixedExpense(): void
    {
        $this->validate([
            'newFixedName' => ['required', 'string', 'max:255'],
            'newFixedAmount' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $this->fixed_expenses[] = [
            'id' => (string) Str::uuid(),
            'name' => trim($this->newFixedName),
            'amount' => round((float) $this->newFixedAmount, 2),
        ];

        $this->reset('newFixedName', 'newFixedAmount');
        $this->persist();
    }

    public function addSessionExpense(): void
    {
        $this->validate([
            'newSessionName' => ['required', 'string', 'max:255'],
            'newSessionAmount' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $this->session_expenses[] = [
            'id' => (string) Str::uuid(),
            'name' => trim($this->newSessionName),
            'amount' => round((float) $this->newSessionAmount, 2),
        ];

        $this->reset('newSessionName', 'newSessionAmount');
        $this->persist();
    }

    public function addEquipment(): void
    {
        $this->validate([
            'newEquipmentName' => ['required', 'string', 'max:255'],
            'newEquipmentPrice' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'newEquipmentResale' => ['nullable', 'numeric', 'min:0', 'lte:newEquipmentPrice'],
            'newEquipmentLife' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $this->equipment[] = [
            'id' => (string) Str::uuid(),
            'name' => trim($this->newEquipmentName),
            'price' => round((float) $this->newEquipmentPrice, 2),
            'resale_value' => round((float) ($this->newEquipmentResale ?: 0), 2),
            'useful_life_years' => (int) $this->newEquipmentLife,
        ];

        $this->reset('newEquipmentName', 'newEquipmentPrice', 'newEquipmentResale', 'newEquipmentLife');
        $this->newEquipmentResale = '0';
        $this->newEquipmentLife = '5';
        $this->persist();
    }

    public function addSessionTask(): void
    {
        $this->validate([
            'newSessionTaskName' => ['required', 'string', 'max:255'],
            'newSessionTaskHours' => ['required', 'numeric', 'min:0', 'max:10000'],
        ]);

        $this->session_tasks[] = [
            'id' => (string) Str::uuid(),
            'name' => trim($this->newSessionTaskName),
            'hours' => round((float) $this->newSessionTaskHours, 2),
        ];

        $this->reset('newSessionTaskName', 'newSessionTaskHours');
        $this->persist();
    }

    public function addMonthlyTask(): void
    {
        $this->validate([
            'newMonthlyTaskName' => ['required', 'string', 'max:255'],
            'newMonthlyTaskHours' => ['required', 'numeric', 'min:0', 'max:10000'],
        ]);

        $this->monthly_tasks[] = [
            'id' => (string) Str::uuid(),
            'name' => trim($this->newMonthlyTaskName),
            'hours' => round((float) $this->newMonthlyTaskHours, 2),
        ];

        $this->reset('newMonthlyTaskName', 'newMonthlyTaskHours');
        $this->persist();
    }

    public function deleteItem(string $type, string $id): void
    {
        $property = match ($type) {
            'fixed' => 'fixed_expenses',
            'session' => 'session_expenses',
            'equipment' => 'equipment',
            'session_task' => 'session_tasks',
            'monthly_task' => 'monthly_tasks',
            default => null,
        };

        if ($property === null) {
            return;
        }

        $this->{$property} = array_values(array_filter(
            $this->{$property},
            fn (array $item): bool => ($item['id'] ?? null) !== $id
        ));

        $this->persist();
    }

    public function resetCalculator(): void
    {
        $this->loadState(CalculatorService::reset(Auth::user()->currentTeam));
        Flux::modal('reset-calculator')->close();
        Flux::toast(__('Calculator reset to defaults.'), variant: 'success');
    }

    public function dismissGuide(): void
    {
        $this->settings['guide_dismissed_at'] = now()->toIso8601String();
        $this->persist();
    }

    #[Computed]
    public function summary(): array
    {
        return CalculatorService::summary($this->state());
    }

    #[Computed]
    public function currency(): string
    {
        return strtoupper(Auth::user()->currentTeam->stripe_currency ?? 'EUR');
    }

    #[Computed]
    public function guideDismissed(): bool
    {
        return ! empty($this->settings['guide_dismissed_at']);
    }

    #[Computed]
    public function copySummary(): string
    {
        $summary = $this->summary;

        return implode(PHP_EOL, [
            __('Base price').': '.$this->formatMoney($summary['base_price']),
            __('VAT').' ('.$summary['vat_percent'].'%): '.$this->formatMoney($summary['vat_amount']),
            __('Invoice total').': '.$this->formatMoney($summary['invoice_total']),
            __('IRPF withheld').' ('.$summary['income_tax_percent'].'%): -'.$this->formatMoney($summary['income_tax_withheld']),
            __('Client pays').': '.$this->formatMoney($summary['client_pays']),
            __('You receive now').': '.$this->formatMoney($summary['net_received']),
        ]);
    }

    public function formatMoney(float $value): string
    {
        return Number::currency($value, $this->currency, app()->getLocale());
    }

    public function formatHours(float $value): string
    {
        return Number::format($value, 2, null, app()->getLocale()).' h';
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        return [
            'settings' => $this->settings,
            'fixed_expenses' => $this->fixed_expenses,
            'session_expenses' => $this->session_expenses,
            'equipment' => $this->equipment,
            'session_tasks' => $this->session_tasks,
            'monthly_tasks' => $this->monthly_tasks,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function loadState(array $state): void
    {
        $this->settings = $state['settings'];
        $this->fixed_expenses = $state['fixed_expenses'];
        $this->session_expenses = $state['session_expenses'];
        $this->equipment = $state['equipment'];
        $this->session_tasks = $state['session_tasks'];
        $this->monthly_tasks = $state['monthly_tasks'];
    }

    private function persist(): void
    {
        CalculatorService::save(Auth::user()->currentTeam, $this->state());
    }
}; ?>

@php
    $summary = $this->summary;
@endphp

<div class="mx-auto max-w-6xl">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <flux:heading size="xl" level="1">{{ __('Session price calculator') }}</flux:heading>
            <flux:subheading size="lg">
                {{ __('Price your sessions with your costs, time and taxes.') }}
            </flux:subheading>
        </div>

        <flux:badge icon="banknotes" color="zinc">
            {{ __('Currency') }}: {{ $this->currency }}
        </flux:badge>
    </div>

    @if (! $this->guideDismissed)
        <div class="relative mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-500/30 dark:bg-blue-500/10">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <flux:heading size="sm">{{ __('How this calculator works') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">
                        {{ __('It adds your costs and the value of your time, then applies VAT and IRPF transparently.') }}
                    </flux:text>

                    <ul class="mt-3 list-disc space-y-1 ps-4 text-sm text-zinc-600 dark:text-zinc-400">
                        <li>{{ __('Fixed expenses and monthly time are split across your sessions per month.') }}</li>
                        <li>{{ __('Equipment is depreciated after its resale value over its useful life.') }}</li>
                        <li>{{ __('VAT is added to the invoice and remitted; IRPF is withheld from your base price and credited against your income tax.') }}</li>
                    </ul>
                </div>

                <flux:button
                    variant="subtle"
                    size="sm"
                    square
                    icon="x-mark"
                    :tooltip="__('Dismiss')"
                    wire:click="dismissGuide"
                />
            </div>
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-5">
                    <flux:heading size="lg">{{ __('Quick settings') }}</flux:heading>
                    <flux:text size="sm">{{ __('These values drive every calculation.') }}</flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model.live.debounce.500ms="settings.hourly_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('Hourly rate')"
                        :description="__('Value of one hour of your time, before taxes.')"
                    />

                    <flux:input
                        wire:model.live.debounce.500ms="settings.sessions_per_month"
                        type="number"
                        step="1"
                        min="1"
                        :label="__('Sessions per month')"
                        :description="__('Used to split monthly costs and monthly time.')"
                    />

                    <flux:input
                        wire:model.live.debounce.500ms="settings.vat_percent"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        :label="__('VAT (%)')"
                        :description="__('Added to the client invoice. Set it to 0 when it does not apply.')"
                    />

                    <flux:input
                        wire:model.live.debounce.500ms="settings.income_tax_percent"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        :label="__('IRPF (%)')"
                        :description="__('Withheld from your base price and credited against your income tax. Set it to 0 when it does not apply.')"
                    />
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:tab.group>
                    <flux:tabs wire:model="tab" scrollable>
                        <flux:tab name="fixed" icon="receipt-percent">{{ __('Fixed expenses') }}</flux:tab>
                        <flux:tab name="session" icon="camera">{{ __('Session expenses') }}</flux:tab>
                        <flux:tab name="equipment" icon="wrench-screwdriver">{{ __('Equipment') }}</flux:tab>
                        <flux:tab name="time" icon="clock">{{ __('Time') }}</flux:tab>
                    </flux:tabs>

                    <flux:tab.panel name="fixed">
                        <flux:text size="sm" class="mb-4">{{ __('Monthly costs that do not change per session.') }}</flux:text>

                        <div class="space-y-2">
                            @forelse ($fixed_expenses as $index => $item)
                                <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 sm:flex-row sm:items-center dark:border-zinc-700" wire:key="fixed-{{ $item['id'] }}">
                                    <flux:input
                                        wire:model.live.debounce.500ms="fixed_expenses.{{ $index }}.name"
                                        :aria-label="__('Expense name')"
                                        :placeholder="__('Expense name')"
                                        class="flex-1"
                                    />

                                    <flux:input
                                        wire:model.live.debounce.500ms="fixed_expenses.{{ $index }}.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :aria-label="__('Amount')"
                                        class="sm:w-36"
                                    />

                                    <flux:button
                                        variant="subtle"
                                        size="sm"
                                        square
                                        icon="trash"
                                        :tooltip="__('Delete')"
                                        class="self-end sm:self-auto"
                                        wire:click="deleteItem('fixed', '{{ $item['id'] }}')"
                                        wire:confirm="{{ __('Delete this expense?') }}"
                                    />
                                </div>
                            @empty
                                <flux:text size="sm">{{ __('No fixed expenses yet.') }}</flux:text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <flux:input wire:model="newFixedName" :placeholder="__('Expense name')" :aria-label="__('Expense name')" />
                                <flux:input wire:model="newFixedAmount" type="number" step="0.01" min="0" :placeholder="__('Amount')" :aria-label="__('Amount')" />
                                <flux:button variant="primary" icon="plus" wire:click="addFixedExpense">
                                    {{ __('Add expense') }}
                                </flux:button>
                            </div>
                            <flux:error name="newFixedName" class="mt-2" />
                            <flux:error name="newFixedAmount" />
                        </div>

                        <div class="mt-4 flex flex-wrap justify-between gap-2 rounded-lg bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900">
                            <span>{{ __('Total fixed costs') }}: <strong>{{ $this->formatMoney($summary['fixed_total']) }}</strong></span>
                            <span>{{ __('Fixed costs per session') }}: <strong>{{ $this->formatMoney($summary['fixed_per_session']) }}</strong></span>
                        </div>
                    </flux:tab.panel>

                    <flux:tab.panel name="session">
                        <flux:text size="sm" class="mb-4">{{ __('Variable costs for each session.') }}</flux:text>

                        <div class="space-y-2">
                            @forelse ($session_expenses as $index => $item)
                                <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 sm:flex-row sm:items-center dark:border-zinc-700" wire:key="session-{{ $item['id'] }}">
                                    <flux:input
                                        wire:model.live.debounce.500ms="session_expenses.{{ $index }}.name"
                                        :aria-label="__('Expense name')"
                                        :placeholder="__('Expense name')"
                                        class="flex-1"
                                    />

                                    <flux:input
                                        wire:model.live.debounce.500ms="session_expenses.{{ $index }}.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :aria-label="__('Amount')"
                                        class="sm:w-36"
                                    />

                                    <flux:button
                                        variant="subtle"
                                        size="sm"
                                        square
                                        icon="trash"
                                        :tooltip="__('Delete')"
                                        class="self-end sm:self-auto"
                                        wire:click="deleteItem('session', '{{ $item['id'] }}')"
                                        wire:confirm="{{ __('Delete this expense?') }}"
                                    />
                                </div>
                            @empty
                                <flux:text size="sm">{{ __('No session expenses yet.') }}</flux:text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <flux:input wire:model="newSessionName" :placeholder="__('Expense name')" :aria-label="__('Expense name')" />
                                <flux:input wire:model="newSessionAmount" type="number" step="0.01" min="0" :placeholder="__('Amount')" :aria-label="__('Amount')" />
                                <flux:button variant="primary" icon="plus" wire:click="addSessionExpense">
                                    {{ __('Add expense') }}
                                </flux:button>
                            </div>
                            <flux:error name="newSessionName" class="mt-2" />
                            <flux:error name="newSessionAmount" />
                        </div>

                        <div class="mt-4 rounded-lg bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900">
                            {{ __('Total per session') }}:
                            <strong>{{ $this->formatMoney($summary['session_total']) }}</strong>
                        </div>
                    </flux:tab.panel>

                    <flux:tab.panel name="equipment">
                        <flux:text size="sm" class="mb-4">{{ __('Camera gear and depreciation.') }}</flux:text>

                        <div class="space-y-2">
                            @forelse ($equipment as $index => $item)
                                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="equipment-{{ $item['id'] }}">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                        <flux:input
                                            wire:model.live.debounce.500ms="equipment.{{ $index }}.name"
                                            :label="__('Equipment name')"
                                            class="flex-1"
                                        />

                                        <flux:input
                                            wire:model.live.debounce.500ms="equipment.{{ $index }}.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :label="__('Price')"
                                            class="sm:w-32"
                                        />

                                        <flux:input
                                            wire:model.live.debounce.500ms="equipment.{{ $index }}.resale_value"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :label="__('Resale value')"
                                            class="sm:w-32"
                                        />

                                        <flux:input
                                            wire:model.live.debounce.500ms="equipment.{{ $index }}.useful_life_years"
                                            type="number"
                                            step="1"
                                            min="1"
                                            max="50"
                                            :label="__('Useful life (years)')"
                                            class="sm:w-32"
                                        />

                                        <flux:button
                                            variant="subtle"
                                            size="sm"
                                            square
                                            icon="trash"
                                            :tooltip="__('Delete')"
                                            class="self-end sm:self-auto"
                                            wire:click="deleteItem('equipment', '{{ $item['id'] }}')"
                                            wire:confirm="{{ __('Delete this equipment?') }}"
                                        />
                                    </div>
                                </div>
                            @empty
                                <flux:text size="sm">{{ __('No equipment yet.') }}</flux:text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_8rem_8rem_8rem_auto] lg:items-end">
                                <flux:input wire:model="newEquipmentName" :label="__('Equipment name')" />
                                <flux:input wire:model="newEquipmentPrice" type="number" step="0.01" min="0" :label="__('Price')" />
                                <flux:input wire:model="newEquipmentResale" type="number" step="0.01" min="0" :label="__('Resale value')" />
                                <flux:input wire:model="newEquipmentLife" type="number" step="1" min="1" max="50" :label="__('Useful life (years)')" />
                                <flux:button variant="primary" icon="plus" wire:click="addEquipment">
                                    {{ __('Add equipment') }}
                                </flux:button>
                            </div>
                            <flux:error name="newEquipmentName" class="mt-2" />
                            <flux:error name="newEquipmentPrice" />
                            <flux:error name="newEquipmentResale" />
                            <flux:error name="newEquipmentLife" />
                        </div>

                        <div class="mt-4 grid gap-2 rounded-lg bg-zinc-50 px-4 py-3 text-sm sm:grid-cols-3 dark:bg-zinc-900">
                            <span>{{ __('Total equipment value') }}: <strong>{{ $this->formatMoney($summary['equipment_total']) }}</strong></span>
                            <span>{{ __('Monthly depreciation') }}: <strong>{{ $this->formatMoney($summary['monthly_depreciation']) }}</strong></span>
                            <span>{{ __('Depreciation per session') }}: <strong>{{ $this->formatMoney($summary['depreciation_per_session']) }}</strong></span>
                        </div>
                    </flux:tab.panel>

                    <flux:tab.panel name="time">
                        <flux:text size="sm" class="mb-4">{{ __('Hours spent on each session and on recurring monthly tasks.') }}</flux:text>

                        <flux:heading size="sm" class="mb-2">{{ __('Time per session') }}</flux:heading>

                        <div class="space-y-2">
                            @forelse ($session_tasks as $index => $item)
                                <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 sm:flex-row sm:items-center dark:border-zinc-700" wire:key="session-task-{{ $item['id'] }}">
                                    <flux:input
                                        wire:model.live.debounce.500ms="session_tasks.{{ $index }}.name"
                                        :aria-label="__('Task name')"
                                        :placeholder="__('Task name')"
                                        class="flex-1"
                                    />

                                    <flux:input
                                        wire:model.live.debounce.500ms="session_tasks.{{ $index }}.hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        :aria-label="__('Hours')"
                                        class="sm:w-32"
                                    />

                                    <flux:button
                                        variant="subtle"
                                        size="sm"
                                        square
                                        icon="trash"
                                        :tooltip="__('Delete')"
                                        class="self-end sm:self-auto"
                                        wire:click="deleteItem('session_task', '{{ $item['id'] }}')"
                                        wire:confirm="{{ __('Delete this time entry?') }}"
                                    />
                                </div>
                            @empty
                                <flux:text size="sm">{{ __('No time tracked yet.') }}</flux:text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <flux:input wire:model="newSessionTaskName" :placeholder="__('Task name')" :aria-label="__('Task name')" />
                                <flux:input wire:model="newSessionTaskHours" type="number" step="0.5" min="0" :placeholder="__('Hours')" :aria-label="__('Hours')" />
                                <flux:button variant="primary" icon="plus" wire:click="addSessionTask">
                                    {{ __('Add time') }}
                                </flux:button>
                            </div>
                            <flux:error name="newSessionTaskName" class="mt-2" />
                            <flux:error name="newSessionTaskHours" />
                        </div>

                        <flux:heading size="sm" class="mt-6 mb-2">{{ __('Time per month') }}</flux:heading>

                        <div class="space-y-2">
                            @forelse ($monthly_tasks as $index => $item)
                                <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 sm:flex-row sm:items-center dark:border-zinc-700" wire:key="monthly-task-{{ $item['id'] }}">
                                    <flux:input
                                        wire:model.live.debounce.500ms="monthly_tasks.{{ $index }}.name"
                                        :aria-label="__('Task name')"
                                        :placeholder="__('Task name')"
                                        class="flex-1"
                                    />

                                    <flux:input
                                        wire:model.live.debounce.500ms="monthly_tasks.{{ $index }}.hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        :aria-label="__('Hours per month')"
                                        class="sm:w-32"
                                    />

                                    <flux:button
                                        variant="subtle"
                                        size="sm"
                                        square
                                        icon="trash"
                                        :tooltip="__('Delete')"
                                        class="self-end sm:self-auto"
                                        wire:click="deleteItem('monthly_task', '{{ $item['id'] }}')"
                                        wire:confirm="{{ __('Delete this time entry?') }}"
                                    />
                                </div>
                            @empty
                                <flux:text size="sm">{{ __('No time tracked yet.') }}</flux:text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <flux:input wire:model="newMonthlyTaskName" :placeholder="__('Task name')" :aria-label="__('Task name')" />
                                <flux:input wire:model="newMonthlyTaskHours" type="number" step="0.5" min="0" :placeholder="__('Hours per month')" :aria-label="__('Hours per month')" />
                                <flux:button variant="primary" icon="plus" wire:click="addMonthlyTask">
                                    {{ __('Add time') }}
                                </flux:button>
                            </div>
                            <flux:error name="newMonthlyTaskName" class="mt-2" />
                            <flux:error name="newMonthlyTaskHours" />
                        </div>

                        <div class="mt-4 grid gap-2 rounded-lg bg-zinc-50 px-4 py-3 text-sm sm:grid-cols-3 dark:bg-zinc-900">
                            <span>{{ __('Session hours') }}: <strong>{{ $this->formatHours($summary['session_hours']) }}</strong></span>
                            <span>{{ __('Monthly hours') }}: <strong>{{ $this->formatHours($summary['monthly_hours']) }}</strong></span>
                            <span>{{ __('Monthly time cost per session') }}: <strong>{{ $this->formatMoney($summary['monthly_time_per_session']) }}</strong></span>
                        </div>
                    </flux:tab.panel>
                </flux:tab.group>
            </div>
        </div>

        <aside class="lg:sticky lg:top-6">
            <div class="rounded-xl border-2 border-zinc-900 bg-white p-6 dark:border-zinc-100 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Recommended price') }}</flux:heading>

                <div class="mt-5 rounded-xl bg-zinc-900 px-5 py-4 text-white dark:bg-zinc-100 dark:text-zinc-900">
                    <div class="text-xs font-medium uppercase tracking-wide opacity-70">{{ __('Client pays') }}</div>
                    <div class="mt-1 text-4xl font-bold tabular-nums">{{ $this->formatMoney($summary['client_pays']) }}</div>
                </div>

                <div class="mt-5 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Fixed costs per session') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['fixed_per_session']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Session expenses') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['session_total']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Depreciation per session') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['depreciation_per_session']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Session time cost') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['session_time_cost']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Monthly time cost per session') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['monthly_time_per_session']) }}</span>
                    </div>
                </div>

                <flux:separator class="my-4" />

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3 font-semibold">
                        <span>{{ __('Base price (before taxes)') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['base_price']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('VAT') }} ({{ $summary['vat_percent'] }}%)</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['vat_amount']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3 font-semibold">
                        <span>{{ __('Invoice total') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['invoice_total']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('IRPF withheld') }} ({{ $summary['income_tax_percent'] }}%)</span>
                        <span class="tabular-nums">-{{ $this->formatMoney($summary['income_tax_withheld']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3 font-semibold">
                        <span>{{ __('You receive now') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['net_received']) }}</span>
                    </div>
                </div>

                <div class="mt-5 rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Total time per session') }}</span>
                        <span class="tabular-nums">{{ $this->formatHours($summary['total_time_per_session']) }}</span>
                    </div>
                    <div class="mt-2 flex justify-between gap-3">
                        <span>{{ __('Gross hourly rate') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['effective_gross_hourly']) }}</span>
                    </div>
                    <div class="mt-2 flex justify-between gap-3">
                        <span>{{ __('Net hourly rate after IRPF') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['effective_net_hourly']) }}</span>
                    </div>
                </div>

                <div
                    class="mt-6 flex items-center gap-2"
                    x-data="{
                        copied: false,
                        copyFailed: false,
                        summary: @js($this->copySummary),
                        copiedLabel: @js(__('Copied!')),
                        copyLabel: @js(__('Copy summary')),
                        failedLabel: @js(__('Copy failed')),
                        copy() {
                            const finish = (success) => {
                                this.copied = success;
                                this.copyFailed = ! success;
                                setTimeout(() => {
                                    this.copied = false;
                                    this.copyFailed = false;
                                }, 2000);
                            };

                            try {
                                if (navigator.clipboard?.writeText && window.isSecureContext) {
                                    navigator.clipboard.writeText(this.summary)
                                        .then(() => finish(true))
                                        .catch(() => this.fallbackCopy());
                                } else {
                                    this.fallbackCopy();
                                }
                            } catch {
                                this.fallbackCopy();
                            }
                        },
                        fallbackCopy() {
                            try {
                                const textarea = document.createElement('textarea');
                                textarea.value = this.summary;
                                textarea.setAttribute('readonly', '');
                                textarea.style.position = 'fixed';
                                textarea.style.opacity = '0';
                                document.body.appendChild(textarea);
                                textarea.select();
                                textarea.setSelectionRange(0, textarea.value.length);
                                const copied = document.execCommand('copy');
                                document.body.removeChild(textarea);

                                if (! copied) {
                                    throw new Error('Copy failed');
                                }

                                this.copied = true;
                                this.copyFailed = false;
                            } catch {
                                this.copied = false;
                                this.copyFailed = true;
                            }

                            setTimeout(() => {
                                this.copied = false;
                                this.copyFailed = false;
                            }, 2000);
                        },
                    }"
                >
                    <flux:button
                        variant="primary"
                        icon="clipboard-document"
                        class="flex-1"
                        x-on:click="copy()"
                    >
                        <span x-text="copied ? copiedLabel : (copyFailed ? failedLabel : copyLabel)">{{ __('Copy summary') }}</span>
                    </flux:button>

                    <flux:modal.trigger name="reset-calculator">
                        <flux:button
                            variant="subtle"
                            square
                            icon="arrow-path"
                            :tooltip="__('Reset to defaults')"
                            :aria-label="__('Reset to defaults')"
                        />
                    </flux:modal.trigger>
                </div>
            </div>
        </aside>
    </div>

    <flux:modal name="reset-calculator" class="w-full sm:max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reset calculator?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will replace all calculator settings and items with the defaults.') }}
                </flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="resetCalculator">
                    {{ __('Reset') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
