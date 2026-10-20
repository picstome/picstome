<?php

namespace App\Models;

use Database\Factories\CalculatorStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculatorState extends Model
{
    /** @use HasFactory<CalculatorStateFactory> */
    use HasFactory;

    public const SCHEMA_VERSION = 2;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'state' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
