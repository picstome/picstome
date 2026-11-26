<?php

namespace Database\Factories;

use App\Models\AvailabilityWindow;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityWindow>
 */
class AvailabilityWindowFactory extends Factory
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
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];
    }

    /**
     * A window for a specific ISO weekday.
     */
    public function forWeekday(int $weekday, string $start = '09:00:00', string $end = '17:00:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'weekday' => $weekday,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
}
