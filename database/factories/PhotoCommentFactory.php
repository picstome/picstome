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
}
