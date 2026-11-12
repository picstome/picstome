<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\PhotoComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoComment>
 */
class PhotoCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photo_id' => Photo::factory(),
            'comment' => $this->faker->sentence(),
        ];
    }

    /**
     * A comment anchored to a second of a video.
     */
    public function atTime(float $seconds, ?int $versionNumber = 1): static
    {
        return $this->state(fn () => [
            'time_seconds' => $seconds,
            'annotated_version_number' => $versionNumber,
        ]);
    }
}
