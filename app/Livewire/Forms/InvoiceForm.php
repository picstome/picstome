<?php

namespace App\Livewire\Forms;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\Team;
use App\Services\InvoiceService;
use App\Services\InvoiceTotalsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class InvoiceForm extends Form
{
    public ?Invoice $invoice = null;

    public ?int $customer_id = null;

    public string $invoice_type = Invoice::TYPE_FULL;

    public string $issue_date = '';

    public string $due_date = '';

    public string $vat_percent = '21';

    public string $irpf_percent = '15';

    public bool $apply_irpf = false;

    public string $discount_type = '';

    public string $discount_amount = '0';

    /** @var array<int, array{description: string, quantity: float|string, unit_price: float|string}> */
    public array $lineItems = [];

    public string $payment_terms = '';

    public string $notes = '';

    public string $exchange_rate = '';

    public string $vat_exemption_reason = '';

    public function rules(): array
    {
        $teamId = Auth::user()?->currentTeam?->id;

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('team_id', $teamId)),
            ],
            'invoice_type' => ['required', Rule::in([Invoice::TYPE_FULL, Invoice::TYPE_SIMPLIFIED])],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'vat_percent' => ['required', 'numeric', 'in:0,4,10,21'],
            'irpf_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'apply_irpf' => ['boolean'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lineItems' => ['required', 'array', 'min:1'],
            'lineItems.*.description' => ['required', 'string', 'max:255'],
            'lineItems.*.quantity' => ['required', 'numeric', 'min:0'],
            'lineItems.*.unit_price' => ['required', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'vat_exemption_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function setSettings(InvoiceSetting $settings): void
    {
        $this->vat_percent = (string) (float) $settings->vat_percent;
        $this->irpf_percent = (string) (float) $settings->irpf_percent;
        $this->apply_irpf = (bool) $settings->apply_irpf;
        $this->payment_terms = (string) $settings->payment_terms;
        $this->notes = (string) $settings->notes;
        $this->issue_date = $this->issue_date ?: now()->toDateString();
    }

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice;

        $this->customer_id = $invoice->customer_id;
        $this->invoice_type = $invoice->invoice_type;
        $this->issue_date = $invoice->issue_date?->format('Y-m-d') ?? now()->toDateString();
        $this->due_date = $invoice->due_date?->format('Y-m-d') ?? '';
        $this->vat_percent = (string) (float) $invoice->vat_percent;
        $this->irpf_percent = (string) (float) $invoice->irpf_percent;
        $this->apply_irpf = (bool) ($invoice->invoice_data['apply_irpf'] ?? false);
        $this->discount_type = (string) $invoice->discount_type;
        $this->discount_amount = (string) (float) $invoice->discount_amount;
        $this->lineItems = $invoice->invoice_data['line_items'] ?? [];
        $this->payment_terms = (string) ($invoice->invoice_data['payment_terms'] ?? '');
        $this->notes = (string) ($invoice->invoice_data['notes'] ?? '');
        $this->exchange_rate = $invoice->exchange_rate ? (string) (float) $invoice->exchange_rate : '';
        $this->vat_exemption_reason = (string) $invoice->vat_exemption_reason;
    }

    /**
     * Amounts of the invoice, calculated live.
     *
     * @return array<string, float>
     */
    public function totals(): array
    {
        return app(InvoiceTotalsService::class)->calculate(
            $this->lineItems,
            $this->discount_type ?: null,
            (float) $this->discount_amount,
            (float) $this->vat_percent,
            (float) $this->irpf_percent,
            $this->apply_irpf,
        );
    }

    /**
     * Create or update the draft invoice.
     */
    public function persist(Team $team): Invoice
    {
        $this->validate();

        $settings = $team->invoiceSetting;
        $snapshots = app(InvoiceService::class);
        $customer = $team->customers()->find($this->customer_id);
        $totals = $this->totals();

        $rate = filled($this->exchange_rate) ? (float) $this->exchange_rate : null;

        $data = [
            'customer_id' => $this->customer_id,
            'invoice_type' => $this->invoice_type,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date ?: null,
            'currency' => $settings->currency,
            'exchange_rate' => $rate,
            'exchange_rate_date' => $rate ? now()->toDateString() : null,
            'vat_percent' => $this->vat_percent,
            'vat_amount' => $totals['vat_amount'],
            'vat_amount_eur' => $rate ? round($totals['vat_amount'] * $rate, 2) : null,
            'vat_exemption_reason' => $this->vat_exemption_reason ?: null,
            'irpf_percent' => $this->irpf_percent,
            'irpf_amount' => $totals['irpf_amount'],
            'irpf_amount_eur' => $rate ? round($totals['irpf_amount'] * $rate, 2) : null,
            'discount_type' => $this->discount_type ?: null,
            'discount_amount' => (float) $this->discount_amount,
            'discount_total' => $totals['discount_total'],
            'subtotal' => $totals['subtotal'],
            'base_amount' => $totals['base_amount'],
            'total' => $totals['total'],
            'amount_due' => $totals['amount_due'],
            'invoice_data' => [
                'line_items' => $this->lineItems,
                'issuer' => $snapshots->issuerSnapshot($settings),
                'customer' => $snapshots->customerSnapshot($customer),
                'payment_terms' => $this->payment_terms,
                'notes' => $this->notes,
                'apply_irpf' => $this->apply_irpf,
                'locale' => app()->getLocale(),
            ],
        ];

        if ($this->invoice) {
            if ($this->invoice->isRectificative() && $this->invoice->rectifiesInvoice) {
                $data['invoice_type'] = $this->invoice->rectifiesInvoice->invoice_type;
            }

            $this->invoice->update($data);

            $this->invoice->recordEvent(InvoiceEvent::UPDATED, Auth::user());

            return $this->invoice;
        }

        $this->invoice = $team->invoices()->create($data);

        $this->invoice->recordEvent(InvoiceEvent::CREATED, Auth::user());

        return $this->invoice;
    }
}
