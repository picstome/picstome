<?php

namespace App\Enums;

/**
 * Media type derived from a file path or extension.
 *
 * Keeps the "what is an image / video / PDF" decision in one place so the
 * model, the URL service, the processing job and the upload API agree.
 */
enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Pdf = 'pdf';
    case Other = 'other';

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tiff'];

    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg'];

    public const PDF_EXTENSIONS = ['pdf'];

    /**
     * Extensions accepted by the upload API. `tif` is accepted for uploads
     * even though the image pipeline only recognises `tiff`.
     */
    public const UPLOAD_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tiff', 'tif'];

    public static function fromPath(?string $path): self
    {
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => self::Image,
            in_array($extension, self::VIDEO_EXTENSIONS, true) => self::Video,
            in_array($extension, self::PDF_EXTENSIONS, true) => self::Pdf,
            default => self::Other,
        };
    }

    public static function isImagePath(?string $path): bool
    {
        return self::fromPath($path) === self::Image;
    }

    public static function isVideoPath(?string $path): bool
    {
        return self::fromPath($path) === self::Video;
    }

    public static function isPdfPath(?string $path): bool
    {
        return self::fromPath($path) === self::Pdf;
    }
}
