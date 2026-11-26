<?php

namespace App\Enums;

/**
 * How the deposit of a product is calculated.
 *
 * `value` is a whole percent for Percent and an amount in minor units for
 * Fixed, matching how prices are stored across the application.
 */
enum DepositType: string
{
    case None = 'none';
    case Fixed = 'fixed';
    case Percent = 'percent';

    /**
     * Deposit amount in minor units, never higher than the price.
     */
    public function amountFor(int $price, ?int $value): int
    {
        return match ($this) {
            self::None => 0,
            self::Fixed => min($price, max(0, (int) $value)),
            self::Percent => (int) round($price * min(100, max(0, (int) $value)) / 100),
        };
    }
}
