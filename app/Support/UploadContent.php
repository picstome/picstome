<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Content checks for uploaded files.
 *
 * The extension only describes what the sender claims: the gallery stores the
 * bytes on a public disk, so a `.jpg` holding HTML would become a broken photo
 * and wasted quota, and a `.mp4` holding junk would never play. These helpers
 * inspect the bytes instead of trusting the name.
 */
final class UploadContent
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tiff'];

    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg'];

    public static function isImage(string $filename): bool
    {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    public static function isVideo(string $filename): bool
    {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS, true);
    }

    /**
     * Whether the bytes are a raster image PHP can decode.
     *
     * `getimagesize()` reads the header itself, so a renamed HTML file is
     * rejected. TIFF falls back to the detected MIME type because not every
     * build parses it; SVG is refused on purpose.
     */
    public static function isReadableImage(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        if (@getimagesize($path) !== false) {
            return true;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? '' : (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        return str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';
    }

    /**
     * Whether the bytes start with a container this gallery advertises.
     *
     * Every supported format carries a fixed magic number, so the header is
     * enough to separate a real video from arbitrary bytes.
     */
    public static function isReadableVideo(UploadedFile $file): bool
    {
        $stream = @fopen($file->getRealPath(), 'rb');

        if (! is_resource($stream)) {
            return false;
        }

        try {
            $header = (string) fread($stream, 12);
        } finally {
            fclose($stream);
        }

        if ($header === '') {
            return false;
        }

        // ISO-BMFF (mp4) keeps the `ftyp` box at byte 4.
        if (str_starts_with(substr($header, 4, 4), 'ftyp')) {
            return true;
        }

        // WebM/Matroska and Ogg both start with a fixed magic number.
        return str_starts_with($header, "\x1A\x45\xDF\xA3")
            || str_starts_with($header, 'OggS');
    }
}
