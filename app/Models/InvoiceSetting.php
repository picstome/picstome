<?php

namespace App\Models;

use Database\Factories\InvoiceSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoicing configuration for a team: issuer fiscal data, tax defaults,
 * currency and the numbering sequences.
 */
class InvoiceSetting extends Model
{
    /** @use HasFactory<InvoiceSettingFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'vat_percent' => 'decimal:2',
            'irpf_percent' => 'decimal:2',
            'simplified_invoice_limit' => 'decimal:2',
            'apply_irpf' => 'boolean',
            'invoice_sequence_state' => 'array',
            'setup_completed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Numbering state for a given series and exercise.
     *
     * @return array{last_number: int, last_fingerprint: ?string}
     */
    public function sequenceState(string $series, int $year): array
    {
        $state = $this->invoice_sequence_state[$series][$year] ?? [];

        return [
            'last_number' => (int) ($state['last_number'] ?? 0),
            'last_fingerprint' => $state['last_fingerprint'] ?? null,
        ];
    }

    /**
     * Store the numbering state for a given series and exercise.
     */
    public function rememberSequence(string $series, int $year, int $number, ?string $fingerprint): void
    {
        $state = $this->invoice_sequence_state ?? [];

        $state[$series][$year] = [
            'last_number' => $number,
            'last_fingerprint' => $fingerprint,
        ];

        $this->invoice_sequence_state = $state;
    }

    /**
     * Whether the issuer has enough data to issue an invoice.
     */
    public function hasCompleteIssuerData(): bool
    {
        return filled($this->legal_name)
            && filled($this->tax_id)
            && filled($this->address)
            && filled($this->city)
            && filled($this->postal_code)
            && filled($this->country);
    }
}
