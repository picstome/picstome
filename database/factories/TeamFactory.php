<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Services\HandleGenerationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $handleGenerator = new HandleGenerationService();
        $name = 'Default Team';
        $handle = $handleGenerator->generateUniqueHandle($name);

        return [
            'name' => $name,
            'handle' => $handle,
            'user_id' => User::factory(),
            'personal_team' => true,
            'brand_color' => 'blue',
            'brand_watermark_position' => 'top',
        ];
    }

    /**
     * Indicate that the team should have watermark branding.
     *
     * @return $this
     */
    public function withWatermark(): static
    {
        return $this->state(fn (array $attributes) => [
            'brand_watermark_path' => 'teams/watermark.png',
            'brand_watermark_transparency' => 50,
        ]);
    }
}
