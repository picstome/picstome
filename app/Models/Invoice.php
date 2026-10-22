<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

/**
 * An invoice issued by a team.
 *
 * Once an invoice is issued it becomes immutable: the model only allows
 * writing the rectification status, the public link revocation and the
 * stored PDF metadata. The fingerprint chain is the second barrier at the
 * data level.
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    public const TYPE_FULL = 'full';

    public const TYPE_SIMPLIFIED = 'simplified';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_RECTIFIED = 'rectified';

    public const RECTIFICATION_SUBSTITUTION = 'substitution';

    /**
     * Attributes that may still be written once the invoice has been issued.
     *
     * @var list<string>
     */
    protected const MUTABLE_AFTER_ISSUE = [
        'status',
        'public_token',
        'public_token_revoked_at',
        'updated_at',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'number' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'exchange_rate_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'vat_percent' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_amount_eur' => 'decimal:2',
            'irpf_percent' => 'decimal:2',
            'irpf_amount' => 'decimal:2',
            'irpf_amount_eur' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'rectified_amount' => 'decimal:2',
            'invoice_data' => 'array',
            'issued_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
            'public_token_revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->ulid)) {
                $invoice->ulid = (string) Str::ulid();
            }

            if (blank($invoice->status)) {
                $invoice->status = self::STATUS_DRAFT;
            }

            if (blank($invoice->invoice_type)) {
                $invoice->invoice_type = self::TYPE_FULL;
            }
        });

        static::saving(function (Invoice $invoice) {
            if (! $invoice->exists) {
                return;
            }

            $originalStatus = $invoice->getOriginal('status');

            if (! in_array($originalStatus, [self::STATUS_ISSUED, self::STATUS_RECTIFIED], true)) {
                return;
            }

            $forbidden = array_diff(
                array_keys($invoice->getDirty()),
                self::MUTABLE_AFTER_ISSUE
            );

            if ($forbidden !== []) {
                throw new LogicException(
                    'Issued invoices are immutable. Forbidden attributes: '.implode(', ', $forbidden)
                );
            }

            if ($originalStatus === self::STATUS_RECTIFIED && $invoice->status !== self::STATUS_RECTIFIED) {
                throw new LogicException('A rectified invoice cannot change its status.');
            }

            if (
                $originalStatus === self::STATUS_ISSUED
                && ! in_array($invoice->status, [self::STATUS_ISSUED, self::STATUS_RECTIFIED], true)
            ) {
                throw new LogicException('An issued invoice can only be marked as rectified.');
            }
        });

        static::deleting(function (Invoice $invoice) {
            if ($invoice->status !== self::STATUS_DRAFT) {
                throw new LogicException('Only draft invoices can be deleted.');
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class);
    }

    /**
     * The invoice this one rectifies, when it is a rectificative.
     */
    public function rectifiesInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rectifies_invoice_id');
    }

    /**
     * The rectificative issued against this invoice.
     */
    public function rectification(): HasOne
    {
        return $this->hasOne(self::class, 'rectifies_invoice_id');
    }

    /**
     * The payments that settle this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Every corrective invoice of this invoice, issued or still a draft.
     */
    public function rectifications(): HasMany
    {
        return $this->hasMany(self::class, 'rectifies_invoice_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isRectified(): bool
    {
        return $this->status === self::STATUS_RECTIFIED;
    }

    public function isSimplified(): bool
    {
        return $this->invoice_type === self::TYPE_SIMPLIFIED;
    }

    public function isRectificative(): bool
    {
        return $this->rectifies_invoice_id !== null;
    }

    /**
     * Append an entry to the audit trail.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordEvent(string $event, ?User $user = null, array $context = []): InvoiceEvent
    {
        return $this->events()->create([
            'team_id' => $this->team_id,
            'user_id' => $user?->id,
            'event' => $event,
            'context' => $context === [] ? null : $context,
        ]);
    }

    /**
     * Formatted number, e.g. F-2026-0001.
     */
    protected function fullNumber(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (blank($this->series) || blank($this->year) || blank($this->number)) {
                return null;
            }

            return sprintf('%s-%d-%s', $this->series, $this->year, str_pad((string) $this->number, 4, '0', STR_PAD_LEFT));
        });
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(function (): string {
            return match ($this->status) {
                self::STATUS_ISSUED => __('Issued'),
                self::STATUS_RECTIFIED => __('Rectified'),
                default => __('Draft'),
            };
        });
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function (): string {
            return "{$this->team->storage_path}/invoices/{$this->ulid}";
        });
    }

    /**
     * Name used when the PDF is downloaded.
     */
    protected function pdfFileName(): Attribute
    {
        return Attribute::get(function (): string {
            return ($this->full_number ?? $this->ulid).'.pdf';
        });
    }
}
