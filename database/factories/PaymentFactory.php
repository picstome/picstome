<?php

namespace Database\Factories;

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
        ];
    }
}
