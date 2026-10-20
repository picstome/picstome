<?php

namespace Database\Factories;

use App\Models\CalculatorState;
use App\Models\Team;
use App\Services\CalculatorService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalculatorState>
 */
class CalculatorStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'schema_version' => CalculatorState::SCHEMA_VERSION,
            'state' => CalculatorService::defaults(),
        ];
    }
}
