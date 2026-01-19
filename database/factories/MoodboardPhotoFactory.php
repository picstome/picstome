<?php

namespace Database\Factories;

use App\Models\Moodboard;
use Illuminate\Database\Eloquent\Factories\Factory;

class MoodboardPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'moodboard_id' => Moodboard::factory(),
            'name' => 'moodboard-photo1.jpg',
            'path' => 'moodboards/moodboard-id/moodboard-photo1.jpg',
            'size' => 1024,
        ];
    }
}
