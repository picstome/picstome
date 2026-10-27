<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\Photo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
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
            'version_count' => 1,
            'uploaded_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Photo $photo): void {
            $photo->source_name ??= $photo->name;
            $photo->stored_size ??= $photo->size;
        });
    }

    public function favorited(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'favorited_at' => Carbon::now(),
            ];
        });
    }

    public function pdf(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'document.pdf',
                'path' => 'galleries/gallery-id/document.pdf',
            ];
        });
    }

    public function video(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'video.mp4',
                'path' => 'galleries/gallery-id/video.mp4',
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
