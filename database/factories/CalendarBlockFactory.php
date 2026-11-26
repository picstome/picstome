<?php

namespace Database\Factories;

use App\Models\CalendarBlock;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarBlock>
 */
class CalendarBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addDay()->startOfDay();
        $endsAt = $startsAt->endOfDay();

        return [
            'team_id' => Team::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => true,
            'reason' => 'Holiday',
        ];
    }
}
