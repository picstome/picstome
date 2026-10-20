<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PhotoVersion>
 */
class PhotoVersionFactory extends Factory
{
    protected $model = PhotoVersion::class;

    public function configure(): static
    {
        return $this->afterMaking(function (PhotoVersion $version): void {
            $version->stored_size ??= $version->size;
        });
    }

    public function definition(): array
    {
        return [
            'photo_id' => Photo::factory(),
            'version_number' => $this->faker->numberBetween(1, 5),
            'name' => Str::slug($this->faker->words(2, true)).'.jpg',
            'source_name' => Str::slug($this->faker->words(2, true)).'.jpg',
            'path' => 'galleries/'.$this->faker->uuid().'/photos/'.$this->faker->uuid().'.jpg',
            'raw_path' => null,
            'disk' => 's3',
            'size' => $this->faker->numberBetween(1000, 100000),
            'status' => 'processed',
            'uploaded_at' => now(),
        ];
    }
}
