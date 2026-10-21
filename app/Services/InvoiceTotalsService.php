<?php

namespace App\Services;

/**
 * Calculates the amounts of an invoice.
 *
 * The discount is applied before the taxes, so the taxable base is the
 * subtotal minus the discount. All the amounts are rounded to two decimals
 * using half-up rounding.
 */
class InvoiceTotalsService
{
    /**
     * @param  array<int, array{description?: string, quantity: mixed, unit_price: mixed}>  $lineItems
     * @return array{
     *     subtotal: float,
     *     discount_total: float,
     *     base_amount: float,
     *     vat_amount: float,
     *     irpf_amount: float,
     *     total: float,
     *     amount_due: float,
     *     net_amount: float
     * }
     */
    public function calculate(
        array $lineItems,
        ?string $discountType = null,
        float $discountAmount = 0,
        float $vatPercent = 0,
        float $irpfPercent = 0,
        bool $applyIrpf = false,
    ): array {
        $subtotal = 0.0;

        foreach ($lineItems as $lineItem) {
            $quantity = (float) ($lineItem['quantity'] ?? 0);
            $unitPrice = (float) ($lineItem['unit_price'] ?? 0);

            $subtotal += $quantity * $unitPrice;
        }

        $subtotal = round($subtotal, 2);

        $discountTotal = $this->discountTotal($subtotal, $discountType, $discountAmount);
        $baseAmount = round($subtotal - $discountTotal, 2);

        $vatAmount = round($baseAmount * ($vatPercent / 100), 2);
        $irpfAmount = $applyIrpf ? round($baseAmount * ($irpfPercent / 100), 2) : 0.0;

        $total = round($baseAmount + $vatAmount, 2);
        $amountDue = round($total - $irpfAmount, 2);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'base_amount' => $baseAmount,
            'vat_amount' => $vatAmount,
            'irpf_amount' => $irpfAmount,
            'total' => $total,
            'amount_due' => $amountDue,
            'net_amount' => round($baseAmount - $irpfAmount, 2),
        ];
    }

    /**
     * Monetary amount of the discount, limited to the subtotal.
     */
    public function discountTotal(float $subtotal, ?string $discountType, float $discountAmount): float
    {
        if (blank($discountType) || $discountAmount <= 0) {
            return 0.0;
        }

        $total = match ($discountType) {
            'percentage' => round($subtotal * ($discountAmount / 100), 2),
            'fixed' => round($discountAmount, 2),
            default => 0.0,
        };

        return round(min(max($total, 0), $subtotal), 2);
    }
}
