<?php

use App\Traits\InteractsWithTallStackModals;
use TallStackUi\Traits\Interactions;
use App\Services\CalculatorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use InteractsWithTallStackModals;
    use Interactions;
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

    public ?string $pendingDeleteType = null;

    public ?string $pendingDeleteId = null;

    public function requestDeleteItem(string $type, string $id): void
    {
        $this->pendingDeleteType = $type;
        $this->pendingDeleteId = $id;

        $this->openModal('delete-calculator-item');
    }

    /**
     * Confirmation copy for the item type that is about to be deleted.
     */
    #[Computed]
    public function pendingDeleteTitle(): string
    {
        return match ($this->pendingDeleteType) {
            'fixed', 'session' => __('Delete this expense?'),
            'equipment' => __('Delete this equipment?'),
            default => __('Delete this time entry?'),
        };
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
        $this->closeModal('reset-calculator');
        $this->toast()->success(__('Calculator reset to defaults.'))->send();
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

        $lines = [
            __('Base price').': '.$this->formatMoney($summary['base_price']),
            __('VAT').' ('.$summary['vat_percent'].'%): '.$this->formatMoney($summary['vat_amount']),
        ];

        if ($summary['income_tax_withholding']) {
            $lines[] = __('Invoice total').': '.$this->formatMoney($summary['invoice_total']);
            $lines[] = __('IRPF withheld').' ('.$summary['income_tax_percent'].'%): -'.$this->formatMoney($summary['income_tax_withheld']);
        }

        $lines[] = __('Client pays').': '.$this->formatMoney($summary['client_pays']);

        return implode(PHP_EOL, $lines);
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
            <x-ts-heading size="xl" level="1">{{ __('Session price calculator') }}</x-ts-heading>
            <x-ts-subheading size="lg">
                {{ __('Price your sessions with your costs, time and taxes.') }}
            </x-ts-subheading>
        </div>

        <x-ts-badge icon="banknotes" color="zinc">{{ __('Currency') }}: {{ $this->currency }}</x-ts-badge>
    </div>

    @if (! $this->guideDismissed)
        <div
            class="border-brand-sage/70 bg-brand-sage/20 dark:border-brand-sage/20 dark:bg-brand-sage/5 relative mb-6 rounded-xl border p-5"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <x-ts-heading size="sm" class="text-brand-green dark:text-brand-sage">
                        {{ __('How this calculator works') }}
                    </x-ts-heading>
                    <x-ts-text size="sm" class="text-brand-ink/75 dark:text-brand-sage/85 mt-1">
                        {{ __('It adds your costs and the value of your time, then applies VAT and IRPF transparently.') }}
                    </x-ts-text>

                    <ul class="text-brand-ink/75 dark:text-brand-sage/85 mt-3 list-disc space-y-1 ps-4 text-sm">
                        <li>{{ __('Fixed expenses and monthly time are split across your sessions per month.') }}</li>
                        <li>{{ __('Equipment is depreciated after its resale value over its useful life.') }}</li>
                        <li>
                            {{ __('VAT is added to the invoice and remitted. When you invoice a company or professional, IRPF is withheld from your base price and credited against your income tax.') }}
                        </li>
                    </ul>
                </div>

                <x-ts-button
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
                    <x-ts-heading size="lg">{{ __('Quick settings') }}</x-ts-heading>
                    <x-ts-text size="sm">{{ __('These values drive every calculation.') }}</x-ts-text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ts-input
                        wire:model.live.debounce.500ms="settings.hourly_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('Hourly rate')"
                        :description="__('Value of one hour of your time, before taxes.')"
                    />

                    <x-ts-input
                        wire:model.live.debounce.500ms="settings.sessions_per_month"
                        type="number"
                        step="1"
                        min="1"
                        :label="__('Sessions per month')"
                        :description="__('Used to split monthly costs and monthly time.')"
                    />

                    <x-ts-input
                        wire:model.live.debounce.500ms="settings.vat_percent"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        :label="__('VAT (%)')"
                        :description="__('Added to the client invoice. Set it to 0 when it does not apply.')"
                    />

                    <x-ts-input
                        wire:model.live.debounce.500ms="settings.income_tax_percent"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        :disabled="! ($settings['income_tax_withholding'] ?? false)"
                        :label="__('IRPF (%)')"
                        :description="__('Withheld from your base price and credited against your income tax. Set it to 0 when it does not apply.')"
                    />
                </div>

                <div
                    class="mt-5 flex flex-col gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <div>
                        <x-ts-heading size="sm">{{ __('Apply IRPF withholding') }}</x-ts-heading>
                        <x-ts-text size="sm">{{ __('Only when the client is a company or professional.') }}</x-ts-text>
                    </div>

                    <x-ts-switch
                        wire:model.live="settings.income_tax_withholding"
                        :aria-label="__('Apply IRPF withholding')"
                    />
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <x-tab wire:model="tab" paddingless shadowless>
<x-tab.items tab="fixed" :title="__('Fixed expenses')">
                        <x-slot:left><x-icon name="receipt-percent" class="size-4" /></x-slot:left>
                        <x-ts-text size="sm" class="mb-4">
                            {{ __('Monthly costs that do not change per session.') }}
                        </x-ts-text>

                        <div class="space-y-2">
                            @forelse ($fixed_expenses as $index => $item)
                                <div
                                    class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 sm:flex-row sm:items-center dark:border-zinc-700"
                                    wire:key="fixed-{{ $item['id'] }}"
                                >
                                    <x-ts-input
                                        wire:model.live.debounce.500ms="fixed_expenses.{{ $index }}.name"
                                        :aria-label="__('Expense name')"
                                        :placeholder="__('Expense name')"
                                        class="flex-1"
                                    />

                                    <x-ts-input
                                        wire:model.live.debounce.500ms="fixed_expenses.{{ $index }}.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :aria-label="__('Amount')"
                                        class="sm:w-36"
                                    />

                                    <x-ts-button
                                        variant="subtle"
                                        size="sm"
                                        square
                                        icon="trash"
                                        :tooltip="__('Delete')"
                                        class="self-end sm:self-auto"
                                        wire:click="requestDeleteItem('fixed', '{{ $item['id'] }}')"
                                    />
                                </div>
                            @empty
                                <x-ts-text size="sm">{{ __('No fixed expenses yet.') }}</x-ts-text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <x-ts-input
                                    wire:model="newFixedName"
                                    :placeholder="__('Expense name')"
                                    :aria-label="__('Expense name')"
                                />
                                <x-ts-input
                                    wire:model="newFixedAmount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :placeholder="__('Amount')"
                                    :aria-label="__('Amount')"
                                />
                                <x-ts-button variant="primary" icon="plus" wire:click="addFixedExpense">
                                    {{ __('Add expense') }}
                                </x-ts-button>
                            </div>


                        </div>

                        <div
                            class="mt-4 flex flex-wrap justify-between gap-2 rounded-lg bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900"
                        >
                            <span>
                                {{ __('Total fixed costs') }}:
                                <strong>{{ $this->formatMoney($summary['fixed_total']) }}</strong>
                            </span>
                            <span>
                                {{ __('Fixed costs per session') }}:
                                <strong>{{ $this->formatMoney($summary['fixed_per_session']) }}</strong>
                            </span>
                        </div>
                    </x-tab.items>

                    <x-tab.items tab="session" :title="__('Session expenses')">
                        <x-slot:left><x-icon name="camera" class="size-4" /></x-slot:left>
                        <x-ts-text size="sm" class="mb-4">{{ __('Variable costs for each session.') }}</x-ts-text>

                        <div class="space-y-2">
                            @forelse ($session_expenses as $index => $item)
                                <div
                                    class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 sm:flex-row sm:items-center dark:border-zinc-700"
                                    wire:key="session-{{ $item['id'] }}"
                                >
                                    <x-ts-input
                                        wire:model.live.debounce.500ms="session_expenses.{{ $index }}.name"
                                        :aria-label="__('Expense name')"
                                        :placeholder="__('Expense name')"
                                        class="flex-1"
                                    />

                                    <x-ts-input
                                        wire:model.live.debounce.500ms="session_expenses.{{ $index }}.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :aria-label="__('Amount')"
                                        class="sm:w-36"
                                    />

                                    <x-ts-button
                                        variant="subtle"
                                        size="sm"
                                        square
                                        icon="trash"
                                        :tooltip="__('Delete')"
                                        class="self-end sm:self-auto"
                                        wire:click="requestDeleteItem('session', '{{ $item['id'] }}')"
                                    />
                                </div>
                            @empty
                                <x-ts-text size="sm">{{ __('No session expenses yet.') }}</x-ts-text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <x-ts-input
                                    wire:model="newSessionName"
                                    :placeholder="__('Expense name')"
                                    :aria-label="__('Expense name')"
                                />
                                <x-ts-input
                                    wire:model="newSessionAmount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :placeholder="__('Amount')"
                                    :aria-label="__('Amount')"
                                />
                                <x-ts-button variant="primary" icon="plus" wire:click="addSessionExpense">
                                    {{ __('Add expense') }}
                                </x-ts-button>
                            </div>


                        </div>

                        <div class="mt-4 rounded-lg bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900">
                            {{ __('Total per session') }}:
                            <strong>{{ $this->formatMoney($summary['session_total']) }}</strong>
                        </div>
                    </x-tab.items>

                    <x-tab.items tab="equipment" :title="__('Equipment')">
                        <x-slot:left><x-icon name="wrench-screwdriver" class="size-4" /></x-slot:left>
                        <x-ts-text size="sm" class="mb-4">{{ __('Camera gear and depreciation.') }}</x-ts-text>

                        <div class="space-y-2">
                            @forelse ($equipment as $index => $item)
                                <div
                                    class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                                    wire:key="equipment-{{ $item['id'] }}"
                                >
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                        <x-ts-input
                                            wire:model.live.debounce.500ms="equipment.{{ $index }}.name"
                                            :label="__('Equipment name')"
                                            class="flex-1"
                                        />

                                        <x-ts-input
                                            wire:model.live.debounce.500ms="equipment.{{ $index }}.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :label="__('Price')"
                                            class="sm:w-32"
                                        />

                                        <x-ts-input
                                            wire:model.live.debounce.500ms="equipment.{{ $index }}.resale_value"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :label="__('Resale value')"
                                            class="sm:w-32"
                                        />

                                        <x-ts-input
                                            wire:model.live.debounce.500ms="equipment.{{ $index }}.useful_life_years"
                                            type="number"
                                            step="1"
                                            min="1"
                                            max="50"
                                            :label="__('Useful life (years)')"
                                            class="sm:w-32"
                                        />

                                        <x-ts-button
                                            variant="subtle"
                                            size="sm"
                                            square
                                            icon="trash"
                                            :tooltip="__('Delete')"
                                            class="self-end sm:self-auto"
                                            wire:click="requestDeleteItem('equipment', '{{ $item['id'] }}')"
                                        />
                                    </div>
                                </div>
                            @empty
                                <x-ts-text size="sm">{{ __('No equipment yet.') }}</x-ts-text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div
                                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_8rem_8rem_8rem_auto] lg:items-end"
                            >
                                <x-ts-input wire:model="newEquipmentName" :label="__('Equipment name')" />
                                <x-ts-input
                                    wire:model="newEquipmentPrice"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Price')"
                                />
                                <x-ts-input
                                    wire:model="newEquipmentResale"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Resale value')"
                                />
                                <x-ts-input
                                    wire:model="newEquipmentLife"
                                    type="number"
                                    step="1"
                                    min="1"
                                    max="50"
                                    :label="__('Useful life (years)')"
                                />
                                <x-ts-button variant="primary" icon="plus" wire:click="addEquipment">
                                    {{ __('Add equipment') }}
                                </x-ts-button>
                            </div>




                        </div>

                        <div
                            class="mt-4 grid gap-2 rounded-lg bg-zinc-50 px-4 py-3 text-sm sm:grid-cols-3 dark:bg-zinc-900"
                        >
                            <span>
                                {{ __('Total equipment value') }}:
                                <strong>{{ $this->formatMoney($summary['equipment_total']) }}</strong>
                            </span>
                            <span>
                                {{ __('Monthly depreciation') }}:
                                <strong>{{ $this->formatMoney($summary['monthly_depreciation']) }}</strong>
                            </span>
                            <span>
                                {{ __('Depreciation per session') }}:
                                <strong>{{ $this->formatMoney($summary['depreciation_per_session']) }}</strong>
                            </span>
                        </div>
                    </x-tab.items>

                    <x-tab.items tab="time" :title="__('Time')">
                        <x-slot:left><x-icon name="clock" class="size-4" /></x-slot:left>
                        <x-ts-text size="sm" class="mb-4">
                            {{ __('Hours spent on each session and on recurring monthly tasks.') }}
                        </x-ts-text>

                        <x-ts-heading size="sm" class="mb-2">{{ __('Time per session') }}</x-ts-heading>

                        <div class="space-y-2">
                            @forelse ($session_tasks as $index => $item)
                                <div
                                    class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 sm:flex-row sm:items-center dark:border-zinc-700"
                                    wire:key="session-task-{{ $item['id'] }}"
                                >
                                    <x-ts-input
                                        wire:model.live.debounce.500ms="session_tasks.{{ $index }}.name"
                                        :aria-label="__('Task name')"
                                        :placeholder="__('Task name')"
                                        class="flex-1"
                                    />

                                    <x-ts-input
                                        wire:model.live.debounce.500ms="session_tasks.{{ $index }}.hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        :aria-label="__('Hours')"
                                        class="sm:w-32"
                                    />

                                    <x-ts-button
                                        variant="subtle"
                                        size="sm"
                                        square
                                        icon="trash"
                                        :tooltip="__('Delete')"
                                        class="self-end sm:self-auto"
                                        wire:click="requestDeleteItem('session_task', '{{ $item['id'] }}')"
                                    />
                                </div>
                            @empty
                                <x-ts-text size="sm">{{ __('No time tracked yet.') }}</x-ts-text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <x-ts-input
                                    wire:model="newSessionTaskName"
                                    :placeholder="__('Task name')"
                                    :aria-label="__('Task name')"
                                />
                                <x-ts-input
                                    wire:model="newSessionTaskHours"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    :placeholder="__('Hours')"
                                    :aria-label="__('Hours')"
                                />
                                <x-ts-button variant="primary" icon="plus" wire:click="addSessionTask">
                                    {{ __('Add time') }}
                                </x-ts-button>
                            </div>


                        </div>

                        <x-ts-heading size="sm" class="mt-6 mb-2">{{ __('Time per month') }}</x-ts-heading>

                        <div class="space-y-2">
                            @forelse ($monthly_tasks as $index => $item)
                                <div
                                    class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 sm:flex-row sm:items-center dark:border-zinc-700"
                                    wire:key="monthly-task-{{ $item['id'] }}"
                                >
                                    <x-ts-input
                                        wire:model.live.debounce.500ms="monthly_tasks.{{ $index }}.name"
                                        :aria-label="__('Task name')"
                                        :placeholder="__('Task name')"
                                        class="flex-1"
                                    />

                                    <x-ts-input
                                        wire:model.live.debounce.500ms="monthly_tasks.{{ $index }}.hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        :aria-label="__('Hours per month')"
                                        class="sm:w-32"
                                    />

                                    <x-ts-button
                                        variant="subtle"
                                        size="sm"
                                        square
                                        icon="trash"
                                        :tooltip="__('Delete')"
                                        class="self-end sm:self-auto"
                                        wire:click="requestDeleteItem('monthly_task', '{{ $item['id'] }}')"
                                    />
                                </div>
                            @empty
                                <x-ts-text size="sm">{{ __('No time tracked yet.') }}</x-ts-text>
                            @endforelse
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <x-ts-input
                                    wire:model="newMonthlyTaskName"
                                    :placeholder="__('Task name')"
                                    :aria-label="__('Task name')"
                                />
                                <x-ts-input
                                    wire:model="newMonthlyTaskHours"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    :placeholder="__('Hours per month')"
                                    :aria-label="__('Hours per month')"
                                />
                                <x-ts-button variant="primary" icon="plus" wire:click="addMonthlyTask">
                                    {{ __('Add time') }}
                                </x-ts-button>
                            </div>


                        </div>

                        <div
                            class="mt-4 grid gap-2 rounded-lg bg-zinc-50 px-4 py-3 text-sm sm:grid-cols-3 dark:bg-zinc-900"
                        >
                            <span>
                                {{ __('Session hours') }}:
                                <strong>{{ $this->formatHours($summary['session_hours']) }}</strong>
                            </span>
                            <span>
                                {{ __('Monthly hours') }}:
                                <strong>{{ $this->formatHours($summary['monthly_hours']) }}</strong>
                            </span>
                            <span>
                                {{ __('Monthly time cost per session') }}:
                                <strong>{{ $this->formatMoney($summary['monthly_time_per_session']) }}</strong>
                            </span>
                        </div>
                    </x-tab.items>
                </x-tab>
            </div>
        </div>

        <aside class="lg:sticky lg:top-6">
            <div
                class="border-brand-green/30 dark:border-brand-sage/30 rounded-xl border-2 bg-white p-6 dark:bg-white/5"
            >
                <x-ts-heading size="lg" class="text-brand-green dark:text-brand-sage">
                    {{ __('Recommended price') }}
                </x-ts-heading>

                <div class="bg-brand-coral text-brand-cream mt-5 rounded-xl px-5 py-4">
                    <div class="text-brand-cream text-xs font-semibold tracking-wide uppercase">
                        {{ __('Client pays') }}
                    </div>
                    <div class="mt-1 text-4xl font-bold tabular-nums">
                        {{ $this->formatMoney($summary['client_pays']) }}
                    </div>
                    <div class="text-brand-cream mt-1 text-sm font-semibold">
                        {{ $summary['income_tax_withholding'] ? __('After IRPF withholding') : __('VAT included') }}
                    </div>
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
                        <span class="tabular-nums">
                            {{ $this->formatMoney($summary['depreciation_per_session']) }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Session time cost') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['session_time_cost']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Monthly time cost per session') }}</span>
                        <span class="tabular-nums">
                            {{ $this->formatMoney($summary['monthly_time_per_session']) }}
                        </span>
                    </div>
                </div>

                <x-ts-separator class="my-4" />

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3 font-semibold">
                        <span>{{ __('Base price (without taxes)') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['base_price']) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>{{ __('VAT') }} ({{ $summary['vat_percent'] }}%)</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['vat_amount']) }}</span>
                    </div>
                    @if ($summary['income_tax_withholding'])
                        <div class="flex justify-between gap-3 font-semibold">
                            <span>{{ __('Invoice total') }}</span>
                            <span class="tabular-nums">{{ $this->formatMoney($summary['invoice_total']) }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span>{{ __('IRPF withheld') }} ({{ $summary['income_tax_percent'] }}%)</span>
                            <span class="tabular-nums">
                                -{{ $this->formatMoney($summary['income_tax_withheld']) }}
                            </span>
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('IRPF is credited against your income tax return.') }}
                        </div>
                    @endif
                </div>

                <div class="mt-5 rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                    <div class="flex justify-between gap-3">
                        <span>{{ __('Total time per session') }}</span>
                        <span class="tabular-nums">{{ $this->formatHours($summary['total_time_per_session']) }}</span>
                    </div>
                    <div class="mt-2 flex justify-between gap-3">
                        <span>{{ __('Effective hourly rate') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($summary['effective_gross_hourly']) }}</span>
                    </div>
                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Base price ÷ total time per session') }}
                    </div>
                </div>

                <div
                    class="mt-6 flex items-center gap-2"
                    x-data="{
                        copied: false,
                        copyFailed: false,
                        copiedLabel: @js(__('Copied!')),
                        copyLabel: @js(__('Copy summary')),
                        failedLabel: @js(__('Copy failed')),
                        copy(text) {
                            text = text.trim()

                            const finish = (success) => {
                                this.copied = success
                                this.copyFailed = ! success
                                setTimeout(() => {
                                    this.copied = false
                                    this.copyFailed = false
                                }, 2000)
                            }

                            try {
                                if (navigator.clipboard?.writeText && window.isSecureContext) {
                                    navigator.clipboard
                                        .writeText(text)
                                        .then(() => finish(true))
                                        .catch(() => this.fallbackCopy(text))
                                } else {
                                    this.fallbackCopy(text)
                                }
                            } catch {
                                this.fallbackCopy(text)
                            }
                        },
                        fallbackCopy(text) {
                            try {
                                const textarea = document.createElement('textarea')
                                textarea.value = text
                                textarea.setAttribute('readonly', '')
                                textarea.style.position = 'fixed'
                                textarea.style.opacity = '0'
                                document.body.appendChild(textarea)
                                textarea.select()
                                textarea.setSelectionRange(0, textarea.value.length)
                                const copied = document.execCommand('copy')
                                document.body.removeChild(textarea)

                                if (! copied) {
                                    throw new Error('Copy failed')
                                }

                                this.copied = true
                                this.copyFailed = false
                            } catch {
                                this.copied = false
                                this.copyFailed = true
                            }

                            setTimeout(() => {
                                this.copied = false
                                this.copyFailed = false
                            }, 2000)
                        },
                    }"
                >
                    <span x-ref="summary" class="hidden">{{ $this->copySummary }}</span>

                    <x-ts-button
                        variant="primary"
                        icon="clipboard-document"
                        class="flex-1"
                        x-on:click="copy($refs.summary.textContent)"
                    >
                        <span x-text="copied ? copiedLabel : copyFailed ? failedLabel : copyLabel">
                            {{ __('Copy summary') }}
                        </span>
                    </x-ts-button>

                    <x-ts-modal-trigger name="reset-calculator">
                        <x-ts-button
                            variant="subtle"
                            square
                            icon="arrow-path"
                            :tooltip="__('Reset to defaults')"
                            :aria-label="__('Reset to defaults')"
                        />
                    </x-ts-modal-trigger>
                </div>
            </div>
        </aside>
    </div>

    <x-confirm-modal
        name="delete-calculator-item"
        :title="$this->pendingDeleteTitle"
        :message="__('This action cannot be undone.')"
        :method="$this->pendingDeleteType && $this->pendingDeleteId ? 'deleteItem(\''.$this->pendingDeleteType.'\', \''.$this->pendingDeleteId.'\')' : null"
    />

    <x-ts-modal name="reset-calculator" class="w-full sm:max-w-md">
        <div class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Reset calculator?') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('This will replace all calculator settings and items with the defaults.') }}
                </x-ts-subheading>
            </div>

            <div class="flex gap-2">
                <x-ts-spacer />

                <x-ts-modal-close>
                    <x-ts-button variant="outline" class="btn-outline-brand">{{ __('Cancel') }}</x-ts-button>
                </x-ts-modal-close>

                <x-ts-button variant="primary" class="btn-coral" wire:click="resetCalculator">
                    {{ __('Reset') }}
                </x-ts-button>
            </div>
        </div>
    </x-ts-modal>
</div>
