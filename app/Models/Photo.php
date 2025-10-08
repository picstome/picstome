<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    /** @use HasFactory<\Database\Factories\PhotosFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'size' => 'integer',
            'favorited_at' => 'datetime',
        ];
    }

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    public function scopeFavorited($query)
    {
        return $query->whereNotNull('favorited_at');
    }

    public function scopeUnfavorited($query)
    {
        return $query->whereNull('favorited_at');
    }

    public function toggleFavorite()
    {
        $this->update(['favorited_at' => $this->favorited_at ? null : Carbon::now()]);
    }

    public function isFavorited()
    {
        return $this->favorited_at !== null;
    }

    public function next()
    {
        $photos = $this->gallery->photos()->with('gallery')->get()->naturalSortBy('name');
        $currentIndex = $photos->search(fn($photo) => $photo->id === $this->id);

        return $photos->get($currentIndex + 1);
    }

    public function previous()
    {
        $photos = $this->gallery->photos()->with('gallery')->get()->naturalSortBy('name');
        $currentIndex = $photos->search(fn($photo) => $photo->id === $this->id);

        return $photos->get($currentIndex - 1);
    }

    public function nextFavorite()
    {
        $favorites = $this->gallery->photos()->favorited()->with('gallery')->get()->naturalSortBy('name');
        $currentIndex = $favorites->search(fn($photo) => $photo->id === $this->id);

        return $favorites->get($currentIndex + 1);
    }

    public function previousFavorite()
    {
        $favorites = $this->gallery->photos()->favorited()->with('gallery')->get()->naturalSortBy('name');
        $currentIndex = $favorites->search(fn($photo) => $photo->id === $this->id);

        return $favorites->get($currentIndex - 1);
    }

    public function deleteFromDisk()
    {
        if ($this->path) {
            DeleteFromDisk::dispatch($this->path, $this->diskOrDefault());
        }

        if ($this->thumb_path) {
            DeleteFromDisk::dispatch($this->thumb_path, $this->diskOrDefault());
        }

        return $this;
    }

    public function download()
    {
        return Storage::disk($this->diskOrDefault())->download($this->path, $this->name);
    }

    protected function url(): Attribute
    {
        return Attribute::get(function () {
            $originalUrl = Storage::disk($this->diskOrDefault())->url($this->path);
            return $this->generateCdnUrl($originalUrl, [
                'q' => 95,
                'output' => 'webp',
            ]);
        });
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(function () {
            $originalUrl = Storage::disk($this->diskOrDefault())->url($this->path);
            $height = config('picstome.photo_thumb_resize', 1000);
            $width = config('picstome.photo_thumb_resize', 1000);
            return $this->generateCdnUrl($originalUrl, [
                'h' => $height,
                'w' => $width,
                'q' => 93,
                'output' => 'webp',
            ]);
        });
    }

    /**
     * Get the large thumbnail URL
     */
    protected function largeThumbnailUrl(): Attribute
    {
        return Attribute::get(function () {
            $originalUrl = Storage::disk($this->diskOrDefault())->url($this->path);
            $size = config('picstome.photo_resize', 2048);
            return $this->generateCdnUrl($originalUrl, [
                'h' => $size,
                'w' => $size,
                'q' => 93,
                'output' => 'webp',
            ]);
        });
    }

    /**
     * Generate a CDN URL for the image, supporting wsrv.nl and i0.wp.com.
     */
    private function generateCdnUrl(string $originalUrl, array $params = [])
    {
        $cdn = config('picstome.photo_cdn_domain', 'wsrv.nl');

        if ($cdn === 'i0.wp.com') {
            $cdn = 'https://' . config('picstome.photo_cdn_domain');
            // i0.wp.com expects the image URL as a path, not a query param
            // Example: https://i0.wp.com/example.com/image.jpg?w=1000&q=90
            $strippedUrl = preg_replace('/^https?:\\/\\//', '', $originalUrl);
            $query = http_build_query($params);
            return "$cdn/$strippedUrl" . ($query ? "?$query" : '');
        }

        // wsrv.nl expects ?url=...&params
        $query = http_build_query(array_merge(['url' => $originalUrl], $params));

        return "https://$cdn/?$query";
    }

    protected function diskOrDefault(): string
    {
        return $this->disk ?? 'public';
    }

    public function isOnPublicDisk()
    {
        return $this->diskOrDefault() === 'public';
    }
}
