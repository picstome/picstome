<?php

use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\InvoiceSettingsForm;
use App\Models\InvoiceSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    use Interactions;
    public InvoiceSettingsForm $form;

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        $settings = InvoiceSetting::firstOrCreate(
            ['team_id' => $team->id],
            config('invoices.defaults')
        );

        $this->form->setSettings($settings);
    }

    public function save(): void
    {
        $this->form->update();

        $this->toast()->success(__('Your changes have been saved.'))->send();
    }
}; ?>

<div class="mx-auto max-w-4xl">
    <x-ts-button variant="ghost" icon="chevron-left" :href="route('invoices')" wire:navigate class="mb-4 -ml-2">
        {{ __('Invoices') }}
    </x-ts-button>

    <x-ts-heading>{{ __('Invoice settings') }}</x-ts-heading>
    <x-ts-subheading>{{ __('Your fiscal data, tax defaults and invoice numbering.') }}</x-ts-subheading>

    <form wire:submit="save" class="mt-8 space-y-10">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
            <x-ts-heading level="2" size="lg">{{ __('Issuer') }}</x-ts-heading>
            <x-ts-subheading>{{ __('The data that identifies you as the invoice issuer.') }}</x-ts-subheading>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-ts-input wire:model="form.legal_name" :label="__('Legal name')" />
                <x-ts-input wire:model="form.tax_id" :label="__('Tax ID')" />
                <x-ts-input wire:model="form.address" :label="__('Address')" class="sm:col-span-2" />
                <x-ts-input wire:model="form.city" :label="__('City')" />
                <x-ts-input wire:model="form.state" :label="__('State / Province')" />
                <x-ts-input wire:model="form.postal_code" :label="__('Postal code')" />
                <x-ts-input
                    wire:model="form.country"
                    :label="__('Country')"
                    :description="__('Two letter country code, for example ES.')"
                    maxlength="2"
                />
                <x-ts-input wire:model="form.email" :label="__('Email')" type="email" />
                <x-ts-input wire:model="form.phone" :label="__('Phone')" />
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
            <x-ts-heading level="2" size="lg">{{ __('Taxes and currency') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Default values applied to every new invoice.') }}</x-ts-subheading>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-ts-select wire:model="form.currency" :label="__('Currency')">
                    @foreach (config('invoices.currencies') as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </x-ts-select>

                <x-ts-select wire:model="form.timezone" :label="__('Timezone')">
                    @foreach ([
                        'Europe/Madrid' => 'Europe/Madrid',
                        'Europe/Lisbon' => 'Europe/Lisbon',
                        'Europe/London' => 'Europe/London',
                        'Europe/Paris' => 'Europe/Paris',
                        'Europe/Berlin' => 'Europe/Berlin',
                        'America/Mexico_City' => 'America/Mexico City',
                        'America/Bogota' => 'America/Bogota',
                        'America/Lima' => 'America/Lima',
                        'America/Santiago' => 'America/Santiago',
                        'America/Argentina/Buenos_Aires' => 'America/Argentina/Buenos Aires',
                        'America/Sao_Paulo' => 'America/Sao Paulo',
                        'America/New_York' => 'America/New York',
                        'UTC' => 'UTC',
                    ] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ts-select>

                <x-ts-select wire:model="form.vat_percent" :label="__('VAT rate')">
                    @foreach (config('invoices.vat_rates') as $rate)
                        <option value="{{ $rate }}"><x-vat-rate-label :rate="$rate" /></option>
                    @endforeach
                </x-ts-select>

                <x-ts-input wire:model="form.irpf_percent" :label="__('IRPF withholding')" type="number" step="0.01" min="0" max="100" />
            </div>

            <div class="mt-6 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-white/10 dark:bg-white/5">
                <x-ts-switch
                    wire:model="form.apply_irpf"
                    :label="__('Apply IRPF withholding by default')"
                    :description="__('The withholding is applied to the taxable base and deducted from the amount the client pays.')"
                />
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-ts-input
                    wire:model="form.simplified_invoice_limit"
                    :label="__('Simplified invoice limit')"
                    type="number"
                    step="0.01"
                    min="0"
                    :description="__('Maximum amount in euros for a simplified invoice.')"
                />
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
            <x-ts-heading level="2" size="lg">{{ __('Numbering') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Invoices are numbered by series, exercise and consecutive number.') }}</x-ts-subheading>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-ts-input
                    wire:model="form.series"
                    :label="__('Series')"
                    :description="__('For example F, for invoices F-2026-0001.')"
                />
                <x-ts-input
                    wire:model="form.rectification_series"
                    :label="__('Rectification series')"
                    :description="__('Series used by the corrective invoices.')"
                />
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
            <x-ts-heading level="2" size="lg">{{ __('Payment terms and notes') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Default values printed on your invoices.') }}</x-ts-subheading>

            <div class="mt-5 space-y-4">
                <x-ts-textarea wire:model="form.payment_terms" :label="__('Payment terms')" rows="2" />
                <x-ts-textarea wire:model="form.notes" :label="__('Invoice footer')" rows="3" />
            </div>
        </section>

        <div class="flex items-center justify-end gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-white/10 dark:bg-white/5">
            <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
        </div>
    </form>
</div>
