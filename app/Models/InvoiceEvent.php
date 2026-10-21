<?php

namespace App\Models;

use Database\Factories\InvoiceEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail for invoices.
 */
class InvoiceEvent extends Model
{
    /** @use HasFactory<InvoiceEventFactory> */
    use HasFactory;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const ISSUED = 'issued';

    public const RECTIFIED = 'rectified';

    public const PDF_DOWNLOADED = 'pdf_downloaded';

    public const PUBLIC_VIEWED = 'public_viewed';

    public const PUBLIC_PDF_DOWNLOADED = 'public_pdf_downloaded';

    public const LINK_REVOKED = 'link_revoked';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Invoice events are append-only.');
        });

        static::deleting(function () {
            throw new LogicException('Invoice events are append-only.');
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
