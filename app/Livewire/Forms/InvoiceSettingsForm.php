<?php

namespace App\Livewire\Forms;

use App\Models\InvoiceSetting;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class InvoiceSettingsForm extends Form
{
    public ?InvoiceSetting $settings = null;

    public string $legal_name = '';

    public string $tax_id = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $postal_code = '';

    public string $country = 'ES';

    public string $phone = '';

    public string $email = '';

    public string $currency = 'EUR';

    public string $timezone = 'Europe/Madrid';

    public string $vat_percent = '21';

    public string $irpf_percent = '15';

    public bool $apply_irpf = false;

    public string $series = 'F';

    public string $rectification_series = 'R';

    public string $simplified_invoice_limit = '3000';

    public string $payment_terms = '';

    public string $notes = '';

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
            'vat_percent' => ['required', 'numeric', 'in:0,4,10,21'],
            'irpf_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'apply_irpf' => ['boolean'],
            'series' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'rectification_series' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'simplified_invoice_limit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_terms' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function setSettings(InvoiceSetting $settings): void
    {
        $this->settings = $settings;

        $this->legal_name = (string) $settings->legal_name;
        $this->tax_id = (string) $settings->tax_id;
        $this->address = (string) $settings->address;
        $this->city = (string) $settings->city;
        $this->state = (string) $settings->state;
        $this->postal_code = (string) $settings->postal_code;
        $this->country = (string) $settings->country;
        $this->phone = (string) $settings->phone;
        $this->email = (string) $settings->email;
        $this->currency = (string) $settings->currency;
        $this->timezone = (string) $settings->timezone;
        $this->vat_percent = $this->decimal($settings->vat_percent);
        $this->irpf_percent = $this->decimal($settings->irpf_percent);
        $this->apply_irpf = (bool) $settings->apply_irpf;
        $this->series = (string) $settings->series;
        $this->rectification_series = (string) $settings->rectification_series;
        $this->simplified_invoice_limit = $this->decimal($settings->simplified_invoice_limit);
        $this->payment_terms = (string) $settings->payment_terms;
        $this->notes = (string) $settings->notes;
    }

    /**
     * Format a decimal amount without trailing zeroes.
     */
    protected function decimal(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    public function update(): void
    {
        $this->validate();

        $settings = $this->settings ?? new InvoiceSetting;

        $settings->team_id = Auth::user()->currentTeam->id;
        $settings->fill([
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => strtoupper($this->country),
            'phone' => $this->phone,
            'email' => $this->email,
            'currency' => strtoupper($this->currency),
            'timezone' => $this->timezone,
            'vat_percent' => $this->vat_percent,
            'irpf_percent' => $this->irpf_percent,
            'apply_irpf' => $this->apply_irpf,
            'series' => strtoupper($this->series),
            'rectification_series' => strtoupper($this->rectification_series),
            'simplified_invoice_limit' => $this->simplified_invoice_limit,
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
        ])->save();

        $this->settings = $settings;
    }

    /**
     * Mark the guided setup as finished.
     */
    public function completeSetup(): void
    {
        $this->update();

        $this->settings?->update(['setup_completed_at' => now()]);
    }

    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }
}
