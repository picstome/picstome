<?php

namespace App\Models;

use App\Enums\DepositType;
use App\Enums\LightMode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Something a client can book: a session type with its duration, price and
 * optional deposit.
 *
 * Prices are stored in minor units, like payments and invoices. The deposit
 * may be defined per product and falls back to the team default.
 *
 * @property int $id
 * @property string $ulid
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $price
 * @property string $currency
 * @property DepositType|null $deposit_type
 * @property int|null $deposit_value
 * @property int|null $buffer_minutes
 * @property string|null $location_name
 * @property float|null $latitude
 * @property float|null $longitude
 * @property LightMode $light_mode
 * @property string|null $color
 * @property bool $is_active
 * @property int $sort_order
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->ulid ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'integer',
            'deposit_type' => DepositType::class,
            'deposit_value' => 'integer',
            'buffer_minutes' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'light_mode' => LightMode::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Effective deposit type: the product override or the team default.
     */
    public function effectiveDepositType(BookingSetting $settings): DepositType
    {
        return $this->deposit_type ?? $settings->default_deposit_type ?? DepositType::None;
    }

    /**
     * Effective deposit value: minor units for Fixed, whole percent for
     * Percent.
     */
    public function effectiveDepositValue(BookingSetting $settings): int
    {
        return (int) ($this->deposit_value ?? $settings->default_deposit_value);
    }

    /**
     * Deposit amount in minor units for this product.
     */
    public function depositAmount(BookingSetting $settings): int
    {
        return $this->effectiveDepositType($settings)
            ->amountFor($this->price, $this->effectiveDepositValue($settings));
    }

    /**
     * Gap kept free after a session of this product.
     */
    public function effectiveBufferMinutes(BookingSetting $settings): int
    {
        return (int) ($this->buffer_minutes ?? $settings->buffer_minutes);
    }
}
