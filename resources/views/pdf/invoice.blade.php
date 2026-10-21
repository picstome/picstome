<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->full_number ?? __('Invoice') }}</title>

    <style>
        body {
            background: #fff none;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1c1917;
        }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #57534e; }
        .right { text-align: right; }
        .totals td { padding: 3px 0; }
        .totals .grand td {
            padding-top: 8px;
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid {{ $accentColor }};
        }
    </style>
</head>
<body>

<table>
    <tr>
        <td style="width: 55%; vertical-align: top;">
            @if ($logo)
                <img src="{{ $logo }}" style="max-height: 60px; max-width: 220px;" />
            @endif

            <div style="margin-top: 8px;">
                <strong>{{ $issuer['legal_name'] ?? '' }}</strong><br>
                @if (filled($issuer['tax_id'] ?? null))
                    <span class="muted">{{ __('Tax ID') }}: {{ $issuer['tax_id'] }}</span><br>
                @endif
                @if (filled($issuer['address'] ?? null))
                    {{ $issuer['address'] }}<br>
                @endif
                @if (filled($issuer['city'] ?? null))
                    {{ $issuer['city'] }}@if (filled($issuer['postal_code'] ?? null)), {{ $issuer['postal_code'] }}@endif<br>
                @endif
                @if (filled($issuer['country'] ?? null))
                    {{ $issuer['country'] }}<br>
                @endif
                @if (filled($issuer['email'] ?? null))
                    {{ $issuer['email'] }}<br>
                @endif
                @if (filled($issuer['phone'] ?? null))
                    {{ $issuer['phone'] }}<br>
                @endif
            </div>
        </td>

        <td style="width: 45%; vertical-align: top;" class="right">
            <div style="font-size: 20px; font-weight: bold; color: {{ $accentColor }};">
                @if ($invoice->isRectificative())
                    {{ __('Corrective invoice') }}
                @elseif ($invoice->isSimplified())
                    {{ __('Simplified invoice') }}
                @else
                    {{ __('Invoice') }}
                @endif
            </div>

            @if ($invoice->full_number)
                <div style="font-size: 13px; margin-top: 4px;">{{ $invoice->full_number }}</div>
            @endif

            <div style="margin-top: 8px;" class="muted">
                {{ __('Issue date') }}: {{ $invoice->issue_date?->format('Y-m-d') }}<br>
                @if ($invoice->due_date)
                    {{ __('Due date') }}: {{ $invoice->due_date->format('Y-m-d') }}<br>
                @endif
            </div>

            @if ($invoice->isRectificative() && $invoice->rectifiesInvoice)
                <div style="margin-top: 8px;" class="muted">
                    {{ __('Corrects invoice') }}: {{ $invoice->rectifiesInvoice->full_number }}<br>
                    @if (filled($invoice->rectification_reason))
                        {{ __('Reason') }}: {{ $invoice->rectification_reason }}<br>
                    @endif
                </div>
            @endif
        </td>
    </tr>
</table>

<table style="margin-top: 22px;">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            <div class="muted" style="text-transform: uppercase; font-size: 9px;">{{ __('Billed to') }}</div>
            <div style="margin-top: 4px;">
                <strong>{{ $customer['legal_name'] ?? ($customer['name'] ?? '') }}</strong>
                @if ($invoice->isSimplified())
                    <div class="muted" style="margin-top: 4px;">{{ __('Recipient not identified') }}</div>
                @else
                    @if (filled($customer['tax_id'] ?? null))
                        <div>{{ __('Tax ID') }}: {{ $customer['tax_id'] }}</div>
                    @endif
                    @if (filled($customer['address'] ?? null))
                        <div>{{ $customer['address'] }}</div>
                    @endif
                    @if (filled($customer['city'] ?? null))
                        <div>{{ $customer['city'] }}@if (filled($customer['postal_code'] ?? null)), {{ $customer['postal_code'] }}@endif</div>
                    @endif
                    @if (filled($customer['country'] ?? null))
                        <div>{{ $customer['country'] }}</div>
                    @endif
                @endif
            </div>
        </td>

        <td style="width: 45%; vertical-align: top;" class="right">
            @if ($qr)
                <img src="{{ $qr }}" style="width: 90px; height: 90px;" />
                <div style="font-size: 9px; margin-top: 3px;">{{ __('Picstome verification') }}</div>
            @endif
        </td>
    </tr>
</table>

<table style="margin-top: 18px;">
    <thead>
        <tr style="border-bottom: 1px solid #d6d3d1;">
            <th style="text-align: left; padding: 6px 0;">{{ __('Description') }}</th>
            <th style="text-align: right; padding: 6px 0; width: 60px;">{{ __('Qty') }}</th>
            <th style="text-align: right; padding: 6px 0; width: 90px;">{{ __('Price') }}</th>
            <th style="text-align: right; padding: 6px 0; width: 100px;">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lineItems as $lineItem)
            <tr>
                <td style="padding: 6px 0; vertical-align: top;">{{ $lineItem['description'] ?? '' }}</td>
                <td style="text-align: right; padding: 6px 0;">{{ rtrim(rtrim(number_format((float) ($lineItem['quantity'] ?? 0), 2, ',', ''), '0'), ',') }}</td>
                <td style="text-align: right; padding: 6px 0;">{{ number_format((float) ($lineItem['unit_price'] ?? 0), 2, ',', '.') }}</td>
                <td style="text-align: right; padding: 6px 0;">{{ number_format((float) ($lineItem['quantity'] ?? 0) * (float) ($lineItem['unit_price'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="muted" style="padding: 6px 0;">{{ __('No line items') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals" style="margin-top: 14px; width: 55%; margin-left: 45%;">
    <tr>
        <td class="muted">{{ __('Subtotal') }}</td>
        <td class="right">{{ number_format((float) $invoice->subtotal, 2, ',', '.') }} {{ $invoice->currency }}</td>
    </tr>

    @if ((float) $invoice->discount_total > 0)
        <tr>
            <td class="muted">{{ __('Discount') }}</td>
            <td class="right">-{{ number_format((float) $invoice->discount_total, 2, ',', '.') }} {{ $invoice->currency }}</td>
        </tr>
    @endif

    <tr>
        <td class="muted">{{ __('Taxable base') }}</td>
        <td class="right">{{ number_format((float) $invoice->base_amount, 2, ',', '.') }} {{ $invoice->currency }}</td>
    </tr>

    <tr>
        <td class="muted">{{ __('VAT') }} {{ rtrim(rtrim(number_format((float) $invoice->vat_percent, 2, ',', ''), '0'), ',') }}%</td>
        <td class="right">{{ number_format((float) $invoice->vat_amount, 2, ',', '.') }} {{ $invoice->currency }}</td>
    </tr>

    @if ($invoice->vat_amount_eur !== null && $invoice->currency !== 'EUR')
        <tr>
            <td class="muted">{{ __('VAT in euros') }}</td>
            <td class="right">{{ number_format((float) $invoice->vat_amount_eur, 2, ',', '.') }} EUR</td>
        </tr>
    @endif

    @if (filled($invoice->vat_exemption_reason))
        <tr>
            <td class="muted" colspan="2">{{ __('Exempt') }}: {{ $invoice->vat_exemption_reason }}</td>
        </tr>
    @endif

    <tr class="grand">
        <td>{{ __('Total') }}</td>
        <td class="right">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td>
    </tr>

    @if ((float) $invoice->irpf_amount > 0)
        <tr>
            <td class="muted">{{ __('IRPF withheld') }} {{ rtrim(rtrim(number_format((float) $invoice->irpf_percent, 2, ',', ''), '0'), ',') }}%</td>
            <td class="right">-{{ number_format((float) $invoice->irpf_amount, 2, ',', '.') }} {{ $invoice->currency }}</td>
        </tr>

        <tr>
            <td><strong>{{ __('Amount to pay') }}</strong></td>
            <td class="right"><strong>{{ number_format((float) $invoice->amount_due, 2, ',', '.') }} {{ $invoice->currency }}</strong></td>
        </tr>
    @endif
</table>

@if (filled($paymentTerms))
    <div style="margin-top: 18px;">
        <strong>{{ __('Payment terms') }}</strong><br>
        <span class="muted">{{ $paymentTerms }}</span>
    </div>
@endif

@if (filled($notes))
    <div style="margin-top: 10px;">
        <span class="muted">{{ $notes }}</span>
    </div>
@endif

@if ($publicUrl)
    <div style="margin-top: 18px; font-size: 9px;" class="muted">
        <div>{{ __('Verify this invoice at') }}: {{ $publicUrl }}</div>
        @if (filled($invoice->fingerprint))
            <div style="margin-top: 2px;">{{ __('Fingerprint') }}: {{ $invoice->fingerprint }}</div>
        @endif
    </div>
@endif

@unlesssubscribed($invoice->team)
    <div style="margin-top: 14px; font-size: 9px;" class="muted">{{ __('Powered by') }} Picstome</div>
@endsubscribed

</body>
</html>
