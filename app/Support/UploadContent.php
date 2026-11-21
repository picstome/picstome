<?php

namespace App\Support;

use App\Enums\MediaType;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
    public const IMAGE_EXTENSIONS = MediaType::IMAGE_EXTENSIONS;

    public const VIDEO_EXTENSIONS = MediaType::VIDEO_EXTENSIONS;

    /**
     * Top-level box types a playable MP4/QuickTime file may start with.
     */
    /**
     * Bytes read from a remote upload to recognise an image. Large enough to
     * reach the size marker after big EXIF/XMP blocks.
     */
    private const IMAGE_HEADER_BYTES = 2 * 1024 * 1024;

    private const MP4_LEADING_BOXES = ['ftyp', 'wide', 'free', 'skip', 'mdat', 'moov', 'pnot'];

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
        $path = self::localPath($file);

        if ($path !== null) {
            if (@getimagesize($path) !== false) {
                return true;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo === false ? '' : (string) finfo_file($finfo, $path);
            finfo_close($finfo);
        } else {
            // The bytes live on a remote disk (a Livewire temporary upload on
            // S3): inspect the leading bytes instead of a local file path.
            $header = self::headerBytes($file, self::IMAGE_HEADER_BYTES);

            if ($header === '') {
                return false;
            }

            if (@getimagesizefromstring($header) !== false) {
                return true;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo === false ? '' : (string) finfo_buffer($finfo, $header);
            finfo_close($finfo);
        }

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
        $header = self::headerBytes($file, 12);

        if ($header === '') {
            return false;
        }

        // ISO-BMFF / QuickTime (mp4) names its first box at byte 4. `ftyp` is
        // the norm, but QuickTime-style files (a renamed .mov) may open with
        // `wide`, `free`, `skip`, `mdat`, `moov` or `pnot` instead.
        if (in_array(substr($header, 4, 4), self::MP4_LEADING_BOXES, true)) {
            return true;
        }

        // WebM/Matroska and Ogg both start with a fixed magic number.
        return str_starts_with($header, "\x1A\x45\xDF\xA3")
            || str_starts_with($header, 'OggS');
    }

    /**
     * The first `$length` bytes of the upload, wherever it is stored.
     *
     * A Livewire temporary upload on S3 reports its object key as the "real
     * path", so it is read through its own disk stream instead of `fopen()`.
     */
    public static function headerBytes(UploadedFile $file, int $length): string
    {
        $stream = $file instanceof TemporaryUploadedFile && self::localPath($file) === null
            ? $file->readStream()
            : @fopen($file->getRealPath(), 'rb');

        if (! is_resource($stream)) {
            return '';
        }

        try {
            $bytes = '';

            while (strlen($bytes) < $length && ! feof($stream)) {
                $chunk = fread($stream, $length - strlen($bytes));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $bytes .= $chunk;
            }

            return $bytes;
        } finally {
            fclose($stream);
        }
    }

    /**
     * Local path of the upload, or null when its bytes are on a remote disk.
     */
    protected static function localPath(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        return is_string($path) && $path !== '' && is_file($path) ? $path : null;
    }
}
