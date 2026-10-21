<?php

namespace Database\Factories;

use App\Models\InvoiceSetting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceSetting>
 */
class InvoiceSettingFactory extends Factory
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
            'legal_name' => 'Studio '.fake()->company(),
            'tax_id' => 'B12345678',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'ES',
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'currency' => 'EUR',
            'timezone' => 'Europe/Madrid',
            'vat_percent' => 21,
            'irpf_percent' => 15,
            'apply_irpf' => false,
            'series' => 'F',
            'rectification_series' => 'R',
            'simplified_invoice_limit' => 3000,
        ];
    }
}
