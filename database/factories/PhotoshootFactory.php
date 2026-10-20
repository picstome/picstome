<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Photoshoot;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photoshoot>
 */
class PhotoshootFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'name' => 'The Great Photoshoot',
            'customer_name' => 'The Best Customer Ever',
        ];
    }
}
