<?php

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\Payment;
use App\Services\InvoiceFingerprintService;
use App\Services\InvoiceDuplicationService;
use App\Services\InvoiceIssuanceService;
use App\Services\InvoicePdfService;
use App\Traits\FormatsMoney;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use FormatsMoney;

    public Invoice $invoice;

    public string $rectificationReason = '';

    public function mount(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $this->invoice = $invoice->load(['rectifiesInvoice', 'rectification', 'payments']);
    }

    #[Computed]
    public function chain()
    {
        return app(InvoiceFingerprintService::class)->verify($this->invoice);
    }

    /**
     * Amount of the invoice already settled by its payments.
     */
    #[Computed]
    public function paidAmount(): float
    {
        return round($this->invoice->payments->sum(fn (Payment $payment) => $payment->amount / 100), 2);
    }

    /**
     * Amount of the invoice still pending.
     */
    #[Computed]
    public function pendingAmount(): float
    {
        return max(0, round((float) $this->invoice->total - $this->paidAmount(), 2));
    }

    #[Computed]
    public function isPaid(): bool
    {
        return (float) $this->invoice->total > 0 && $this->pendingAmount() <= 0;
    }

    #[Computed]
    public function events()
    {
        return $this->invoice->events()->with('user')->latest('id')->get();
    }

    public function revokeLink(): void
    {
        $this->authorize('managePublicLink', $this->invoice);

        if (blank($this->invoice->public_token)) {
            return;
        }

        $this->invoice->update([
            'public_token' => null,
            'public_token_revoked_at' => now(),
        ]);

        $this->invoice->recordEvent(InvoiceEvent::LINK_REVOKED, Auth::user());

        Flux::toast(__('The public link has been deactivated.'), variant: 'success');
    }

    /**
     * Create a new public link for an invoice whose link was revoked.
     */
    public function regenerateLink(): void
    {
        $this->authorize('managePublicLink', $this->invoice);

        $this->invoice->update([
            'public_token' => app(InvoicePdfService::class)->newPublicToken(),
            'public_token_revoked_at' => null,
        ]);

        $this->invoice->recordEvent(InvoiceEvent::LINK_REGENERATED, Auth::user());

        Flux::toast(__('A new public link has been generated.'), variant: 'success');
    }

    /**
     * Create the corrective invoice. It is issued straight away, unless the
     * user prefers to review it first.
     */
    public function rectify(bool $review = false): void
    {
        $this->authorize('rectify', $this->invoice);

        if ($this->invoice->rectifications()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => __('This invoice has already been rectified.'),
            ]);
        }

        $this->validate([
            'rectificationReason' => ['required', 'string', 'max:1000'],
        ]);

        $original = $this->invoice;

        $draft = $original->team->invoices()->create([
            'customer_id' => $original->customer_id,
            'invoice_type' => $original->invoice_type,
            'issue_date' => now()->toDateString(),
            'currency' => $original->currency,
            'exchange_rate' => $original->exchange_rate,
            'exchange_rate_date' => $original->exchange_rate_date,
            'vat_percent' => $original->vat_percent,
            'vat_exemption_reason' => $original->vat_exemption_reason,
            'irpf_percent' => $original->irpf_percent,
            'discount_type' => $original->discount_type,
            'discount_amount' => $original->discount_amount,
            'discount_total' => $original->discount_total,
            'subtotal' => $original->subtotal,
            'base_amount' => $original->base_amount,
            'vat_amount' => $original->vat_amount,
            'vat_amount_eur' => $original->vat_amount_eur,
            'irpf_amount' => $original->irpf_amount,
            'irpf_amount_eur' => $original->irpf_amount_eur,
            'total' => $original->total,
            'amount_due' => $original->amount_due,
            'rectifies_invoice_id' => $original->id,
            'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
            'rectification_reason' => $this->rectificationReason,
            'invoice_data' => $original->invoice_data,
        ]);

        $draft->recordEvent(InvoiceEvent::CREATED, Auth::user(), [
            'rectifies' => $original->full_number,
        ]);

        if ($review) {
            $this->redirect(route('invoices.create', ['invoice' => $draft->ulid]), navigate: true);

            return;
        }

        app(InvoiceIssuanceService::class)->issue($draft, Auth::user());

        $this->redirect(route('invoices.show', $draft), navigate: true);
    }

    /**
     * Copy an issued invoice into a new draft, to make recurring invoices
     * faster to prepare.
     */
    public function duplicate()
    {
        $this->authorize('create', Invoice::class);

        $draft = app(InvoiceDuplicationService::class)->duplicate($this->invoice, Auth::user());

        $this->redirect(route('invoices.create', ['invoice' => $draft->ulid]), navigate: true);
    }

    public function delete()
    {
        $this->authorize('delete', $this->invoice);

        $this->invoice->delete();

        return $this->redirect(route('invoices'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center gap-2">
        <flux:button variant="ghost" icon="chevron-left" :href="route('invoices')" wire:navigate class="-ml-2">
            {{ __('Invoices') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-heading level="1" size="xl">
                {{ $this->invoice->full_number ?? __('Draft') }}
            </x-heading>
            <x-subheading>
                {{ $this->invoice->isRectificative() ? __('Corrective invoice') : __('Invoice') }}
                @if ($this->invoice->issue_date)
                    · {{ $this->invoice->issue_date->format('Y-m-d') }}
                @endif
            </x-subheading>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <flux:badge :color="$this->invoice->isIssued() ? 'green' : ($this->invoice->isRectified() ? 'orange' : 'zinc')" inset>
                {{ $this->invoice->status_label }}
            </flux:badge>

            @if ($this->isPaid)
                <flux:badge color="lime" inset>{{ __('Paid') }}</flux:badge>
            @endif

            @if ($this->invoice->pdf_path)
                <flux:button variant="primary" class="btn-ink" icon="document-arrow-down" :href="route('invoices.pdf', $this->invoice)">
                    {{ __('Download PDF') }}
                </flux:button>
            @endif

            @if ($this->invoice->isIssued())
                <flux:button variant="primary" class="btn-ink" icon="document-duplicate" wire:click="duplicate">
                    {{ __('Duplicate') }}
                </flux:button>
            @endif

            @if ($this->invoice->isIssued() && ! $this->invoice->isRectificative() && ! $this->invoice->rectifications()->exists())
                <flux:modal.trigger name="rectify-invoice">
                    <flux:button variant="primary" class="btn-coral" icon="pencil-square">{{ __('Rectify') }}</flux:button>
                </flux:modal.trigger>
            @endif

            @if ($this->invoice->isDraft())
                <flux:button variant="primary" class="btn-ink" icon="pencil-square" :href="route('invoices.create', ['invoice' => $this->invoice->ulid])">
                    {{ __('Edit') }}
                </flux:button>

                <flux:modal.trigger name="delete-invoice">
                    <flux:button variant="primary" class="btn-coral" icon="trash">{{ __('Delete') }}</flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    </div>

    @if ($this->invoice->isRectificative() && $this->invoice->rectifiesInvoice)
        <flux:callout icon="information-circle" variant="outline" class="mt-6">
            <flux:callout.text>
                {{ __('This invoice corrects :number.', ['number' => $this->invoice->rectifiesInvoice->full_number]) }}
                @if (filled($this->invoice->rectification_reason))
                    {{ __('Reason: :reason', ['reason' => $this->invoice->rectification_reason]) }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->invoice->rectification)
        <flux:callout icon="check-circle" variant="outline" class="mt-6">
            <flux:callout.text>
                {{ __('This invoice was corrected by :number.', ['number' => $this->invoice->rectification->full_number]) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
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
                    @if (filled($this->invoice->invoice_data['customer']['address'] ?? null))
                        <div class="text-zinc-500">
                            {{ $this->invoice->invoice_data['customer']['address'] }},
                            {{ $this->invoice->invoice_data['customer']['city'] ?? '' }}
                            {{ $this->invoice->invoice_data['customer']['postal_code'] ?? '' }}
                        </div>
                    @endif
                </dl>
            </div>

            @if (! $this->invoice->isDraft())
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
                    <flux:heading level="3" size="sm">{{ __('Public link') }}</flux:heading>
                    <flux:subheading size="sm">
                        {{ __('Share it with your client so they can check the invoice online.') }}
                    </flux:subheading>

                    @if (filled($this->invoice->public_token))
                        <flux:input
                            icon="link"
                            :value="route('invoices.public.show', $this->invoice->public_token)"
                            readonly
                            copyable
                            class="mt-3"
                        />

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <flux:button
                                size="sm"
                                variant="primary"
                                class="btn-ink"
                                icon="arrow-top-right-on-square"
                                :href="route('invoices.public.show', $this->invoice->public_token)"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ __('View invoice') }}
                            </flux:button>

                            <flux:modal.trigger name="revoke-link">
                                <flux:button size="sm" variant="ghost" icon="link-slash">
                                    {{ __('Deactivate link') }}
                                </flux:button>
                            </flux:modal.trigger>
                        </div>
                    @else
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <flux:button size="sm" variant="primary" class="btn-ink" icon="link" wire:click="regenerateLink">
                                {{ __('Generate a new link') }}
                            </flux:button>

                            @if ($this->invoice->public_token_revoked_at)
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ __('Deactivated on :date', ['date' => $this->invoice->public_token_revoked_at->format('Y-m-d H:i')]) }}
                                </flux:text>
                            @endif
                        </div>
                    @endif

                    <flux:text size="sm" class="mt-3 text-zinc-500">
                        {{ __('The link printed in the PDF cannot be changed: the already delivered documents keep the previous one.') }}
                    </flux:text>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-white/10 dark:bg-white/5">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-zinc-600 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300">
                            <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Price') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->invoice->invoice_data['line_items'] ?? [] as $lineItem)
                            <tr wire:key="line-{{ $loop->index }}" class="border-b border-zinc-100 last:border-0 dark:border-white/5">
                                <td class="px-4 py-3">{{ $lineItem['description'] ?? '' }}</td>
                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-300">{{ $lineItem['quantity'] ?? 0 }}</td>
                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-300">{{ $this->money($lineItem['unit_price'] ?? 0) }}</td>
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
                    @if ($this->invoice->vat_amount_eur !== null && $this->invoice->currency !== 'EUR')
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('VAT in euros') }}</dt>
                            <dd>{{ $this->money($this->invoice->vat_amount_eur) }} EUR</dd>
                        </div>
                    @endif
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

            @if ($this->invoice->payments->isNotEmpty())
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
                    <flux:heading level="3" size="sm">{{ __('Payments') }}</flux:heading>

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Paid') }}</dt>
                            <dd>{{ $this->money($this->paidAmount) }} {{ $this->invoice->currency }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Pending') }}</dt>
                            <dd>{{ $this->money($this->pendingAmount) }} {{ $this->invoice->currency }}</dd>
                        </div>
                    </dl>

                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($this->invoice->payments as $payment)
                            <li class="flex items-center justify-between gap-4">
                                <span class="text-zinc-600 dark:text-zinc-300">{{ $payment->description }}</span>
                                <span class="whitespace-nowrap">
                                    {{ $payment->formattedAmount }}
                                    @if ($payment->completed_at)
                                        <span class="ms-2 text-xs text-zinc-500">{{ $payment->completed_at->format('Y-m-d') }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <flux:text size="sm" class="mt-3 text-zinc-500">
                        {{ __('A payment does not replace the invoice: it is issued by the photographer.') }}
                    </flux:text>
                </div>
            @endif

            @if (filled($this->invoice->fingerprint))
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
            @endif

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
                <flux:heading level="3" size="sm">{{ __('Activity') }}</flux:heading>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($this->events as $event)
                        <li wire:key="event-{{ $event->id }}" class="flex items-center justify-between gap-4">
                            <span>{{ str($event->event)->replace('_', ' ')->title() }}</span>
                            <span class="text-xs text-zinc-500">{{ $event->created_at->format('Y-m-d H:i') }}</span>
                        </li>
                    @empty
                        <li class="text-zinc-500">{{ __('No activity yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <flux:modal name="rectify-invoice" class="w-full sm:max-w-lg">
        <form wire:submit="rectify" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Rectify invoice') }}</flux:heading>
                <flux:subheading>
                    {{ __('The corrective invoice is issued with the same client and line items. The original invoice keeps its number and its PDF.') }}
                </flux:subheading>
            </div>

            <flux:textarea wire:model="rectificationReason" :label="__('Reason')" rows="3" />

            @php
                $rectificationErrors = $errors->get('invoice');
            @endphp

            @if ($rectificationErrors !== [])
                <flux:callout icon="exclamation-triangle" variant="danger">
                    <flux:callout.text>
                        @foreach ($rectificationErrors as $rectificationError)
                            <div>{{ $rectificationError }}</div>
                        @endforeach
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-2">
                <flux:button type="button" variant="outline" class="btn-outline-brand" wire:click="rectify(true)">
                    {{ __('Review before issuing') }}
                </flux:button>

                <flux:button type="submit" variant="primary" class="btn-coral">
                    {{ __('Issue corrective invoice') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <x-confirm-modal
        name="delete-invoice"
        :title="__('Delete this draft?')"
        :message="__('Draft invoices can be deleted without any trace. Issued invoices can never be deleted.')"
        :confirm-label="__('Delete')"
        method="delete"
    />

    <x-confirm-modal
        name="revoke-link"
        :title="__('Deactivate the public link?')"
        :message="__('Whoever has the link will stop seeing the invoice. The invoice and its PDF do not change.')"
        :confirm-label="__('Deactivate link')"
        method="revokeLink"
    />
</div>
