<?php

namespace App\Services;

use App\Models\Gallery;
use Illuminate\Support\Facades\Storage;

/**
 * Centralizes photo and photo-version URL generation, including CDN resizing.
 * Keeping this logic here prevents divergence between current photos and
 * historical versions.
 */
class PhotoUrlService
{
    public function __construct(
        protected string $path,
        protected string $disk,
        protected ?int $id = null,
        protected bool $keepOriginalSize = false,
    ) {}

    public static function make(object $photoLike, ?Gallery $gallery = null): self
    {
        $disk = method_exists($photoLike, 'diskOrDefault')
            ? $photoLike->diskOrDefault()
            : ($photoLike->disk ?? config('picstome.disk', 's3'));

        $keep = false;

        if ($gallery) {
            $keep = (bool) $gallery->keep_original_size;
        } elseif (isset($photoLike->gallery) && is_object($photoLike->gallery)) {
            $keep = (bool) $photoLike->gallery->keep_original_size;
        } elseif (isset($photoLike->photo) && isset($photoLike->photo->gallery)) {
            $keep = (bool) $photoLike->photo->gallery->keep_original_size;
        }

        return new self(
            $photoLike->path,
            $disk,
            $photoLike->id ?? null,
            $keep,
        );
    }

    public function thumbnail(): string
    {
        if (! $this->isImage()) {
            return '';
        }

        return $this->cdn([
            'h' => config('picstome.photo_thumb_resize', 1000),
            'w' => config('picstome.photo_thumb_resize', 1000),
            'q' => 93,
            'output' => 'webp',
        ]);
    }

    public function smallThumbnail(): string
    {
        if (! $this->isImage()) {
            return '';
        }

        $size = config('picstome.photo_small_thumb_resize', 500);

        return $this->cdn([
            'h' => $size,
            'w' => $size,
            'q' => 93,
            'output' => 'webp',
        ]);
    }

    public function largeThumbnail(): string
    {
        if (! $this->isImage()) {
            return '';
        }

        $size = config('picstome.photo_resize', 2048);

        return $this->cdn([
            'h' => $size,
            'w' => $size,
            'q' => 93,
            'output' => 'webp',
        ]);
    }

    public function portraitThumbnail(): string
    {
        if (! $this->isImage()) {
            return '';
        }

        return $this->cdn([
            'h' => 800,
            'aspect_ratio' => '2:3',
            'q' => 93,
            'output' => 'webp',
        ]);
    }

    public function coverImage(): string
    {
        if (! $this->isImage()) {
            return '';
        }

        $width = 2560;
        $height = 960;

        return $this->cdn([
            'w' => $width,
            'h' => $height,
            'q' => 93,
            'output' => 'webp',
            'face_crop' => "{$width},{$height}",
        ]);
    }

    /**
     * Return the untouched storage URL, used by the full-resolution version
     * comparison UI.
     */
    public function original(): string
    {
        if (! $this->isImage()) {
            return '';
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function url(): string
    {
        if (! $this->isImage()) {
            return '';
        }

        $height = $this->keepOriginalSize
            ? config('picstome.photo_resize', 2048) * 2
            : config('picstome.photo_resize', 2048);
        $width = $height;

        return $this->cdn([
            'h' => $height,
            'w' => $width,
            'q' => 93,
            'output' => 'webp',
        ]);
    }

    protected function isImage(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'tiff'], true);
    }

    protected function cdn(array $params): string
    {
        $originalUrl = Storage::disk($this->disk)->url($this->path);
        $cdn = config('picstome.photo_cdn_domain', 'wsrv.nl');

        if ($cdn === 'bunny' || $cdn === 'bunny.net') {
            $bunnyParams = [];

            foreach (['w', 'h', 'q', 'output', 'face_crop', 'aspect_ratio'] as $key) {
                if (isset($params[$key])) {
                    $map = [
                        'w' => 'width',
                        'h' => 'height',
                        'q' => 'quality',
                        'output' => 'format',
                        'face_crop' => 'face_crop',
                        'aspect_ratio' => 'aspect_ratio',
                    ];
                    $bunnyParams[$map[$key]] = $params[$key];
                }
            }

            return $originalUrl.'?'.http_build_query($bunnyParams);
        }

        if ($cdn === 'i0.wp.com') {
            $subdomainIndex = $this->id ? $this->id % 4 : 0;
            $cdn = 'https://i'.$subdomainIndex.'.wp.com';
            $strippedUrl = preg_replace('/^https?:\/\//', '', $originalUrl);

            return $cdn.'/'.$strippedUrl.'?'.http_build_query($params);
        }

        return 'https://'.$cdn.'/?'.http_build_query(array_merge(['url' => $originalUrl], $params));
    }
}
