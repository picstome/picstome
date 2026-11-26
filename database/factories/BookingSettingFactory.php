<?php

namespace Database\Factories;

use App\Models\BookingSetting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingSetting>
 */
class BookingSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge(config('bookings.defaults'), [
            'team_id' => Team::factory(),
        ]);
    }
}
