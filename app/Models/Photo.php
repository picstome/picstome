<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Photo extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'size' => 'integer',
            'favorited_at' => 'datetime',
            'status' => 'string',
        ];
    }

    protected static function booted()
    {
        static::created(function ($photo) {
            Cache::forget("gallery:{$photo->gallery_id}:first_image");
            Cache::forget("gallery:{$photo->gallery_id}:photos_count");
            Cache::forget("gallery:{$photo->gallery_id}:photos:ids");
            Cache::forget("gallery:{$photo->gallery_id}:favorites:ids");
            Cache::forget("gallery:{$photo->gallery_id}:commented:ids");
        });

        static::deleted(function ($photo) {
            Cache::forget("gallery:{$photo->gallery_id}:first_image");
            Cache::forget("gallery:{$photo->gallery_id}:photos_count");
            Cache::forget("gallery:{$photo->gallery_id}:photos:ids");
            Cache::forget("gallery:{$photo->gallery_id}:favorites:ids");
            Cache::forget("gallery:{$photo->gallery_id}:commented:ids");
        });
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

        Cache::forget("gallery:{$this->gallery_id}:favorites:ids");
    }

    public function isFavorited()
    {
        return $this->favorited_at !== null;
    }

    public function next(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->photoIds(), 1);
    }

    public function previous(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->photoIds(), -1);
    }

    public function nextFavorite(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->favoritePhotoIds(), 1);
    }

    public function previousFavorite(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->favoritePhotoIds(), -1);
    }

    public function nextCommented(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->commentedPhotoIds(), 1);
    }

    public function previousCommented(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->commentedPhotoIds(), -1);
    }

    /**
     * Find the neighbor photo offset positions away within the given
     * naturally sorted photo id list.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $photoIds
     */
    protected function navigationNeighbor(Collection $photoIds, int $offset): ?Photo
    {
        $currentIndex = $photoIds->search($this->id);

        if ($currentIndex === false) {
            return null;
        }

        $neighborId = $photoIds->get($currentIndex + $offset);

        return $neighborId === null ? null : static::find($neighborId);
    }

    public function deleteFromDisk()
    {
        if ($this->path) {
            DeleteFromDisk::dispatch($this->path, $this->diskOrDefault());
        }

        if ($this->thumb_path) {
            DeleteFromDisk::dispatch($this->thumb_path, $this->diskOrDefault());
        }

        if ($this->raw_path) {
            DeleteFromDisk::dispatch($this->raw_path, $this->diskOrDefault());
        }

        return $this;
    }

    public function download()
    {
        $filename = $this->name;

        if ($this->raw_path) {
            $pathInfo = pathinfo($this->name);
            $filename = $pathInfo['filename'].'.jpg';
        }

        return Storage::disk($this->diskOrDefault())->download($this->path, $filename);
    }

    public function downloadRaw()
    {
        return Storage::disk($this->diskOrDefault())->download($this->raw_path, $this->name);
    }

    /**
     * Stream a PDF inline with a forced content type, so the browser's native
     * PDF reader renders it regardless of the content type the upload placed
     * on the stored object.
     */
    public function inline(): StreamedResponse
    {
        return Storage::disk($this->diskOrDefault())->response($this->path, $this->name, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function url(): Attribute
    {
        return Attribute::get(function () {
            if ($this->isImage()) {
                $originalUrl = Storage::disk($this->diskOrDefault())->url($this->path);
                $height = config('picstome.photo_resize', 2048);
                $width = config('picstome.photo_resize', 2048);

                if ($this->gallery->keep_original_size) {
                    $height = $height * 2;
                    $width = $width * 2;
                }

                return $this->generateCdnUrl($originalUrl, [
                    'h' => $height,
                    'w' => $width,
                    'q' => 93,
                    'output' => 'webp',
                ]);
            }

            if ($this->isPdf()) {
                return null;
            }

            $disk = $this->diskOrDefault();
            $diskConfig = config("filesystems.disks.$disk");
            $baseUrl = ($diskConfig['origin'] ?? null).'/'.($diskConfig['bucket'] ?? null);

            if ($baseUrl) {
                $baseUrl = rtrim($baseUrl, '/');
                $path = ltrim($this->path, '/');

                return $baseUrl.'/'.$path;
            }

            return Storage::disk($disk)->url($this->path);
        });
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->isImage()) {
                return null;
            }

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
            if (! $this->isImage()) {
                return null;
            }

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
     * Get the large thumbnail URL
     */
    protected function smallThumbnailUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->isImage()) {
                return null;
            }

            $originalUrl = Storage::disk($this->diskOrDefault())->url($this->path);
            $size = config('picstome.photo_small_thumb_resize', 500);

            return $this->generateCdnUrl($originalUrl, [
                'h' => $size,
                'w' => $size,
                'q' => 93,
                'output' => 'webp',
            ]);
        });
    }

    protected function portraitThumbnailUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->isImage()) {
                return null;
            }

            $originalUrl = Storage::disk($this->diskOrDefault())->url($this->path);

            return $this->generateCdnUrl($originalUrl, [
                'h' => 800,
                'aspect_ratio' => '2:3',
                'q' => 93,
                'output' => 'webp',
            ]);
        });
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->isImage()) {
                return null;
            }

            $originalUrl = Storage::disk($this->diskOrDefault())->url($this->path);
            $width = 2560;
            $height = 960;

            return $this->generateCdnUrl($originalUrl, [
                'w' => $width,
                'h' => $height,
                'q' => 93,
                'output' => 'webp',
                'face_crop' => "{$width},{$height}",
            ]);
        });
    }

    /**
     * Generate a CDN URL for the image, supporting wsrv.nl, i0.wp.com, and Bunny.net.
     */
    private function generateCdnUrl(string $originalUrl, array $params = [])
    {
        $cdn = config('picstome.photo_cdn_domain', 'wsrv.nl');

        // Bunny.net Dynamic Image API
        if ($cdn === 'bunny' || $cdn === 'bunny.net') {
            $bunnyParams = [];

            if (isset($params['w']) || isset($params['width'])) {
                $bunnyParams['width'] = $params['w'] ?? $params['width'];
            }

            if (isset($params['h']) || isset($params['height'])) {
                $bunnyParams['height'] = $params['h'] ?? $params['height'];
            }

            if (isset($params['q']) || isset($params['quality'])) {
                $bunnyParams['quality'] = $params['q'] ?? $params['quality'];
            }

            if (isset($params['output']) || isset($params['format'])) {
                $bunnyParams['format'] = $params['output'] ?? $params['format'];
            }

            if (isset($params['face_crop'])) {
                $bunnyParams['face_crop'] = $params['face_crop'];
            }

            if (isset($params['aspect_ratio'])) {
                $bunnyParams['aspect_ratio'] = $params['aspect_ratio'];
            }

            $query = http_build_query($bunnyParams);

            return "$originalUrl?$query";
        }

        if ($cdn === 'i0.wp.com') {
            // Rotate between i0.wp.com, i1.wp.com, i2.wp.com, i3.wp.com based on photo id
            $subdomainIndex = 0;

            if (isset($this->id) && is_numeric($this->id)) {
                $subdomainIndex = $this->id % 4;
            }

            $cdn = 'https://i'.$subdomainIndex.'.wp.com';

            // i0.wp.com expects the image URL as a path, not a query param
            // Example: https://i0.wp.com/example.com/image.jpg?w=1000&q=90
            $strippedUrl = preg_replace('/^https?:\/\//', '', $originalUrl);

            $query = http_build_query($params);

            return "$cdn/$strippedUrl".($query ? "?$query" : '');
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

    public function comments()
    {
        return $this->hasMany(PhotoComment::class);
    }

    /**
     * Determine if the photo is an image (jpg, jpeg, png, tiff)
     */
    public function isImage(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'tiff']);
    }

    /**
     * Determine if the photo is a video (mp4, mkv, avi)
     */
    public function isVideo(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm', 'ogg']);
    }

    /**
     * Determine if the photo is a PDF document
     */
    public function isPdf(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return $ext === 'pdf';
    }
}
