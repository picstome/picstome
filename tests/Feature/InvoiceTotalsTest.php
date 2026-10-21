<?php

use App\Services\InvoiceTotalsService;

it('calculates the totals applying the discount before the taxes', function () {
    $totals = app(InvoiceTotalsService::class)->calculate(
        [
            ['quantity' => 2, 'unit_price' => 50],
            ['quantity' => 1, 'unit_price' => 25],
        ],
        'percentage',
        10,
        21,
        15,
        true,
    );

    expect($totals['subtotal'])->toBe(125.0)
        ->and($totals['discount_total'])->toBe(12.5)
        ->and($totals['base_amount'])->toBe(112.5)
        ->and($totals['vat_amount'])->toBe(23.63)
        ->and($totals['total'])->toBe(136.13)
        ->and($totals['irpf_amount'])->toBe(16.88)
        ->and($totals['amount_due'])->toBe(119.25)
        ->and($totals['net_amount'])->toBe(95.62);
});

it('limits a fixed discount to the subtotal', function () {
    $totals = app(InvoiceTotalsService::class)->calculate(
        [['quantity' => 1, 'unit_price' => 100]],
        'fixed',
        250,
        21,
    );

    expect($totals['discount_total'])->toBe(100.0)
        ->and($totals['base_amount'])->toBe(0.0)
        ->and($totals['total'])->toBe(0.0);
});

it('ignores the IRPF withholding when it is not applied', function () {
    $totals = app(InvoiceTotalsService::class)->calculate(
        [['quantity' => 1, 'unit_price' => 100]],
        null,
        0,
        21,
        15,
        false,
    );

    expect($totals['irpf_amount'])->toBe(0.0)
        ->and($totals['amount_due'])->toBe(121.0)
        ->and($totals['net_amount'])->toBe(100.0);
});

it('rounds every amount to two decimals', function () {
    $totals = app(InvoiceTotalsService::class)->calculate(
        [['quantity' => 3, 'unit_price' => 33.33]],
        null,
        0,
        21,
    );

    expect($totals['subtotal'])->toBe(99.99)
        ->and($totals['vat_amount'])->toBe(21.0)
        ->and($totals['total'])->toBe(120.99);
});
