<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\GalleryArchive;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryArchive>
 */
class GalleryArchiveFactory extends Factory
{
    protected $model = GalleryArchive::class;

    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'team_id' => fn (array $attributes) => Gallery::find($attributes['gallery_id'])->team_id,
            'variant' => 'all',
            'status' => 'pending',
            'disk' => 's3',
            'path' => null,
            'filename' => 'gallery.zip',
            'size_bytes' => null,
            'photos_total' => 0,
            'photos_processed' => 0,
            'photos_skipped' => 0,
            'images_count' => 0,
            'videos_count' => 0,
            'pdfs_count' => 0,
            'fingerprint' => Str::random(64),
            'downloads_count' => 0,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => 'ready',
            'path' => 'teams/1/galleries/1/archives/gallery.zip',
            'size_bytes' => 1024,
            'expires_at' => now()->addDays(7),
            'completed_at' => now(),
        ]);
    }
}
