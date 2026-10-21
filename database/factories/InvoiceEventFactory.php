<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceEvent>
 */
class InvoiceEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'team_id' => fn (array $attributes) => Invoice::find($attributes['invoice_id'])?->team_id,
            'event' => InvoiceEvent::CREATED,
            'context' => null,
        ];
    }
}
