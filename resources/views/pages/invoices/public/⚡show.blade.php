<?php

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Services\InvoiceFingerprintService;
use App\Traits\FormatsMoney;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use FormatsMoney;

    public Invoice $invoice;

    public function mount(string $token)
    {
        $invoice = Invoice::query()
            ->where('public_token', $token)
            ->whereNull('public_token_revoked_at')
            ->first();

        abort_if(
            ! $invoice || ! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_RECTIFIED], true),
            404
        );

        $this->invoice = $invoice;

        $invoice->recordEvent(InvoiceEvent::PUBLIC_VIEWED);
    }

    #[Computed]
    public function chain()
    {
        return app(InvoiceFingerprintService::class)->verify($this->invoice);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-center gap-3">
        <x-heading level="1" size="xl">
            {{ $this->invoice->full_number }}
        </x-heading>

        <flux:badge
            :color="match ($this->invoice->status) {
                'issued' => 'green',
                'rectified' => 'orange',
                default => 'zinc',
            }"
            inset
        >
            {{ $this->invoice->status_label }}
        </flux:badge>
    </div>

    <x-subheading>
        {{ $this->invoice->isRectificative() ? __('Corrective invoice') : ($this->invoice->isSimplified() ? __('Simplified invoice') : __('Invoice')) }}
        · {{ $this->invoice->issue_date?->format('Y-m-d') }}
    </x-subheading>

    @if ($this->invoice->isRectificative() && $this->invoice->rectifiesInvoice)
        <flux:callout icon="information-circle" variant="outline" class="mt-6">
            <flux:callout.text>
                {{ __('This invoice corrects :number.', ['number' => $this->invoice->rectifiesInvoice->full_number]) }}
                @if (filled($this->invoice->rectification_reason))
                    {{ __('Reason: :reason', ['reason' => $this->invoice->rectification_reason]) }}
                @endif
                @if (filled($this->invoice->rectifiesInvoice->public_token))
                    <a href="{{ route('invoices.public.show', $this->invoice->rectifiesInvoice->public_token) }}" class="font-medium underline">
                        {{ __('View the original invoice') }}
                    </a>
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->invoice->rectification)
        <flux:callout icon="check-circle" variant="outline" class="mt-6">
            <flux:callout.text>
                {{ __('This invoice was corrected by :number.', ['number' => $this->invoice->rectification->full_number]) }}
                @if (filled($this->invoice->rectification->public_token))
                    <a href="{{ route('invoices.public.show', $this->invoice->rectification->public_token) }}" class="font-medium underline">
                        {{ __('View the corrective invoice') }}
                    </a>
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
                <flux:heading level="3" size="sm">{{ __('Issuer') }}</flux:heading>
                <dl class="mt-3 space-y-1 text-sm">
                    <div>{{ $this->invoice->invoice_data['issuer']['legal_name'] ?? '' }}</div>
                    @if (filled($this->invoice->invoice_data['issuer']['tax_id'] ?? null))
                        <div class="text-zinc-500">{{ __('Tax ID') }}: {{ $this->invoice->invoice_data['issuer']['tax_id'] }}</div>
                    @endif
                    @if (filled($this->invoice->invoice_data['issuer']['address'] ?? null))
                        <div class="text-zinc-500">
                            {{ $this->invoice->invoice_data['issuer']['address'] }},
                            {{ $this->invoice->invoice_data['issuer']['city'] ?? '' }}
                            {{ $this->invoice->invoice_data['issuer']['postal_code'] ?? '' }}
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
                <flux:heading level="3" size="sm">{{ __('Client') }}</flux:heading>
                <dl class="mt-3 space-y-1 text-sm">
                    <div>{{ $this->invoice->invoice_data['customer']['legal_name'] ?? ($this->invoice->customer?->name ?? '—') }}</div>
                    @if (filled($this->invoice->invoice_data['customer']['tax_id'] ?? null))
                        <div class="text-zinc-500">{{ __('Tax ID') }}: {{ $this->invoice->invoice_data['customer']['tax_id'] }}</div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
                <flux:heading level="3" size="sm">{{ __('Traceability') }}</flux:heading>
                <dl class="mt-3 space-y-1 text-xs text-zinc-500">
                    <div class="break-all font-mono">{{ $this->invoice->fingerprint }}</div>
                    <div class="mt-2">
                        {{ __('Position in the chain') }}: {{ $this->chain['position'] }} / {{ $this->chain['total'] }}
                    </div>
                    <div>
                        {{ __('Chain') }}:
                        <span class="{{ $this->chain['valid'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $this->chain['valid'] ? __('Verified') : __('Broken') }}
                        </span>
                    </div>
                </dl>
            </div>
        </div>

        <div class="space-y-6">
            <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-white/10 dark:bg-white/5">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-zinc-600 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300">
                            <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->invoice->invoice_data['line_items'] ?? [] as $lineItem)
                            <tr wire:key="public-line-{{ $loop->index }}" class="border-b border-zinc-100 last:border-0 dark:border-white/5">
                                <td class="px-4 py-3">{{ $lineItem['description'] ?? '' }}</td>
                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-300">{{ $lineItem['quantity'] ?? 0 }}</td>
                                <td class="px-4 py-3 text-right font-medium">
                                    {{ $this->money(($lineItem['quantity'] ?? 0) * ($lineItem['unit_price'] ?? 0)) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Subtotal') }}</dt>
                        <dd>{{ $this->money($this->invoice->subtotal) }} {{ $this->invoice->currency }}</dd>
                    </div>
                    @if ((float) $this->invoice->discount_total > 0)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Discount') }}</dt>
                            <dd>-{{ $this->money($this->invoice->discount_total) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Taxable base') }}</dt>
                        <dd>{{ $this->money($this->invoice->base_amount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('VAT') }} {{ (float) $this->invoice->vat_percent }}%</dt>
                        <dd>{{ $this->money($this->invoice->vat_amount) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-white/10">
                        <dt>{{ __('Total') }}</dt>
                        <dd>{{ $this->money($this->invoice->total) }} {{ $this->invoice->currency }}</dd>
                    </div>
                    @if ((float) $this->invoice->irpf_amount > 0)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('IRPF withheld') }} {{ (float) $this->invoice->irpf_percent }}%</dt>
                            <dd>-{{ $this->money($this->invoice->irpf_amount) }}</dd>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <dt>{{ __('Amount to pay') }}</dt>
                            <dd>{{ $this->money($this->invoice->amount_due) }} {{ $this->invoice->currency }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($this->invoice->pdf_path)
                <flux:button variant="outline" icon="document-arrow-down" :href="route('invoices.public.pdf', $this->invoice->public_token)">
                    {{ __('Download PDF') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if (filled($this->invoice->invoice_data['payment_terms'] ?? null) || filled($this->invoice->invoice_data['notes'] ?? null))
        <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
            @if (filled($this->invoice->invoice_data['payment_terms'] ?? null))
                <div class="text-sm">
                    <span class="font-medium">{{ __('Payment terms') }}</span>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $this->invoice->invoice_data['payment_terms'] }}</p>
                </div>
            @endif

            @if (filled($this->invoice->invoice_data['notes'] ?? null))
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $this->invoice->invoice_data['notes'] }}</p>
            @endif
        </div>
    @endif

    <p class="mt-8 text-xs text-zinc-500">
        {{ __('This invoice was issued with Picstome. The verification above relies on the fingerprint chain stored by the issuer.') }}
    </p>
</div>
