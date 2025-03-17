<?php

namespace Database\Factories;

use App\Models\Gallery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Photos>
 */
class PhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'name' => 'photo1.jpg',
            'path' => 'galleries/gallery-id/photo1.jpg',
            'size' => 1024,
        ];
    }

    public function favorited(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'favorited_at' => Carbon::now(),
            ];
        });
    }

    public function unfavorited(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'favorited_at' => null,
            ];
        });
    }
}
