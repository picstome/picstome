<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition()
    {
        return [
            'team_id' => Team::factory(),
            'amount' => $this->faker->numberBetween(1000, 100000), // cents
            'currency' => 'usd',
            'description' => $this->faker->sentence,
            'photoshoot_id' => null,
            'customer_email' => $this->faker->unique()->safeEmail(),
            'completed_at' => now(),
            'status' => PaymentStatus::Completed,
            'payment_method' => PaymentMethod::Card,
        ];
    }

    /**
     * A payment link waiting for a Bizum transfer.
     */
    public function pendingBizum(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::PendingBizum,
            'payment_method' => PaymentMethod::Bizum,
            'reference' => 'Bea-'.$this->faker->unique()->numberBetween(1, 28),
            'completed_at' => null,
            'expires_at' => now()->addDay(),
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->phoneNumber(),
        ]);
    }
}
