<?php

namespace App\Services;

use App\Models\Gallery;

class PhotoIdentityService
{
    public const RAW_EXTENSIONS = [
        'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'rw2', 'pef', 'srw', 'mos', 'mrw', '3fr',
    ];

    protected const IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'tiff', 'tif',
        ...self::RAW_EXTENSIONS,
    ];

    protected const VIDEO_EXTENSIONS = [
        'mp4', 'webm', 'ogg',
    ];

    /**
     * Build a stable identity for grouping different representations of the
     * same logical file. File extensions are intentionally ignored for
     * images, so JPG/JPEG and edited/RAW exports share the same identity.
     */
    public static function key(string $filename): string
    {
        $sanitized = Gallery::sanitizeFileName($filename);
        $extension = strtolower(pathinfo($sanitized, PATHINFO_EXTENSION));
        $stem = strtolower(pathinfo($sanitized, PATHINFO_FILENAME));

        $family = match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => 'image',
            in_array($extension, self::VIDEO_EXTENSIONS, true) => 'video',
            $extension === 'pdf' => 'pdf',
            default => 'file',
        };

        return $family.':'.$stem;
    }
}
