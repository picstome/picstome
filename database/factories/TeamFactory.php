<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Services\HandleGenerationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
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
        $handleGenerator = new HandleGenerationService;
        $name = $this->faker->words(2, true);
        $handle = $handleGenerator->generateUniqueHandle($name);

        return [
            'name' => $name,
            'handle' => $handle,
            'user_id' => User::factory(),
            'personal_team' => true,
            'brand_color' => 'blue',
            'brand_watermark_position' => 'top',
            'brand_watermark_type' => 'image',
            'brand_watermark_size' => 'md',
        ];
    }

    /**
     * Indicate that the team should have an image watermark.
     *
     * @return $this
     */
    public function withWatermark(): static
    {
        return $this->state(fn (array $attributes) => [
            'brand_watermark_type' => 'image',
            'brand_watermark_path' => 'teams/watermark.png',
            'brand_watermark_transparency' => 50,
        ]);
    }

    /**
     * Indicate that the team should have a text watermark.
     *
     * @return $this
     */
    public function withTextWatermark(string $text = 'Picstome', ?string $font = null, string $size = 'md'): static
    {
        return $this->state(fn (array $attributes) => [
            'brand_watermark_type' => 'text',
            'brand_watermark_text' => $text,
            'brand_watermark_font' => $font,
            'brand_watermark_size' => $size,
            'brand_watermark_transparency' => 50,
        ]);
    }
}
