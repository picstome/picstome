<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'invoice_type' => Invoice::TYPE_FULL,
            'currency' => 'EUR',
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
            'vat_percent' => 21,
            'subtotal' => 0,
            'base_amount' => 0,
            'vat_amount' => 0,
            'total' => 0,
            'amount_due' => 0,
            'invoice_data' => [
                'line_items' => [],
                'issuer' => [],
                'customer' => [],
            ],
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_ISSUED,
            'series' => $attributes['series'] ?? 'F',
            'year' => $attributes['year'] ?? (int) now()->year,
            'number' => $attributes['number'] ?? 1,
            'issued_at' => now(),
        ]);
    }
}
