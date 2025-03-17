<?php

namespace Database\Factories;

use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
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
            'title' => 'The Contract',
            'description' => 'A short contract description',
            'location' => 'Barcelon',
            'shooting_date' => Carbon::now()->addDays(2),
            'markdown_body' => '# Contract boy in markdown',
        ];
    }

    public function executed(): Factory
    {
        return $this->state(function () {
            return [
                'executed_at' => Carbon::now()->subHour(),
            ];
        });
    }
}
