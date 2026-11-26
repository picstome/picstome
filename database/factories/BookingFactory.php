<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addDays(2)->setTime(10, 0);

        return [
            'team_id' => Team::factory(),
            'product_id' => null,
            'customer_id' => null,
            'product_name' => fake()->words(2, true),
            'duration_minutes' => 60,
            'status' => BookingStatus::Confirmed,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'timezone' => 'Europe/Madrid',
            'total_amount' => 15000,
            'deposit_amount' => 0,
            'balance_amount' => 15000,
            'currency' => 'EUR',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => null,
            'notes' => null,
            'source' => 'public',
            'payment_method' => null,
            'hold_expires_at' => null,
            'confirmed_at' => now(),
            'cancelled_at' => null,
            'completed_at' => null,
            'cancellation_reason' => null,
            'policy_snapshot' => null,
            'policy_accepted_at' => null,
            'balance_due_at' => null,
        ];
    }

    /**
     * A booking that reserves its slot and is waiting for payment.
     */
    public function pending(BookingStatus $status = BookingStatus::PendingPayment): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'confirmed_at' => null,
            'hold_expires_at' => now()->addMinutes(config('bookings.hold_minutes')),
        ]);
    }

    /**
     * Copy the commercial snapshot of a product into the booking.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $product->team_id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'duration_minutes' => $product->duration_minutes,
            'total_amount' => $product->price,
            'currency' => $product->currency,
        ]);
    }
}
