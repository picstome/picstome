<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Process;

class RawPhotoService
{
    /**
     * Supported RAW file extensions.
     */
    protected array $rawExtensions = [
        'cr2', 'cr3',  // Canon
        'nef',        // Nikon
        'arw',        // Sony
        'dng',        // Adobe
        'orf',        // Olympus
        'rw2',        // Panasonic
        'pef',        // Pentax
        'srw',        // Samsung
        'mos',        // Leaf
        'mrw',        // Minolta
        '3fr',        // Hasselblad
    ];

    /**
     * Check if a file extension is a supported RAW format.
     */
    public function isRawFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, $this->rawExtensions, true);
    }

    /**
     * Check if ExifTool is available on the system.
     */
    public function isExifToolAvailable(): bool
    {
        try {
            return Process::run('which exiftool')->successful();
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Extract JPG preview from a RAW file.
     */
    public function extractJpgFromRaw(string $rawFilePath, string $outputPath): bool
    {
        if (! $this->isExifToolAvailable()) {
            return false;
        }

        // Try to extract preview image first (higher quality)
        if ($this->extractPreviewImage($rawFilePath, $outputPath)) {
            return $this->validateExtractedImage($outputPath);
        }

        // Fallback to thumbnail image (always present but lower quality)
        if ($this->extractThumbnailImage($rawFilePath, $outputPath)) {
            return $this->validateExtractedImage($outputPath);
        }

        return false;
    }

    /**
     * Extract preview image from RAW file.
     */
    protected function extractPreviewImage(string $rawFilePath, string $outputPath): bool
    {
        return $this->runExifToolCommand("-PreviewImage -b {$rawFilePath} > {$outputPath}", $outputPath);
    }

    /**
     * Extract thumbnail image from RAW file.
     */
    protected function extractThumbnailImage(string $rawFilePath, string $outputPath): bool
    {
        return $this->runExifToolCommand("-ThumbnailImage -b {$rawFilePath} > {$outputPath}", $outputPath);
    }

    /**
     * Run an ExifTool command with timeout and error handling.
     */
    protected function runExifToolCommand(string $command, string $outputPath): bool
    {
        try {
            $timeout = config('picstome.exiftool_timeout', 30);
            $fullCommand = "exiftool {$command}";

            $result = Process::timeout($timeout)->run($fullCommand);

            return $result->successful() && file_exists($outputPath);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Validate that the extracted image is a valid JPG.
     */
    protected function validateExtractedImage(string $imagePath): bool
    {
        if (! file_exists($imagePath)) {
            return false;
        }

        $fileSize = filesize($imagePath);
        if ($fileSize === 0 || $fileSize > 50 * 1024 * 1024) { // Max 50MB
            return false;
        }

        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return false;
        }

        return in_array($imageInfo['mime'], ['image/jpeg', 'image/jpg'], true);
    }

    /**
     * Get all supported RAW extensions.
     */
    public function getSupportedExtensions(): array
    {
        return $this->rawExtensions;
    }

    /**
     * Get EXIF orientation from a RAW file.
     */
    public function getExifOrientation(string $rawFilePath): ?int
    {
        if (! $this->isExifToolAvailable()) {
            return null;
        }

        try {
            $timeout = config('picstome.exiftool_timeout', 30);
            $command = "exiftool -Orientation -n {$rawFilePath}";

            $result = Process::timeout($timeout)->run($command);

            if (! $result->successful()) {
                return null;
            }

            $output = trim($result->output());

            preg_match('/Orientation\s*:\s*(\d+)/', $output, $matches);

            return $matches[1] ?? null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get Spatie Image Orientation enum from EXIF orientation value.
     */
    public function getOrientationEnum(int $orientation): ?\Spatie\Image\Enums\Orientation
    {
        return match ($orientation) {
            1 => \Spatie\Image\Enums\Orientation::Rotate0,
            3 => \Spatie\Image\Enums\Orientation::Rotate180,
            6 => \Spatie\Image\Enums\Orientation::Rotate90,
            8 => \Spatie\Image\Enums\Orientation::Rotate270,
            default => null,
        };
    }

    /**
     * Clean up temporary files.
     */
    public function cleanupTempFile(string $filePath): void
    {
        @unlink($filePath);
    }
}
