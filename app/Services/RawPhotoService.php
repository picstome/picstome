<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
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
            $result = Process::run('which exiftool');

            return $result->successful();
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
        try {
            $timeout = config('picstome.exiftool_timeout', 30);
            $command = "exiftool -PreviewImage -b {$rawFilePath} > {$outputPath}";

            $result = Process::timeout($timeout)->run($command);

            return $result->successful() && file_exists($outputPath);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Extract thumbnail image from RAW file.
     */
    protected function extractThumbnailImage(string $rawFilePath, string $outputPath): bool
    {
        try {
            $timeout = config('picstome.exiftool_timeout', 30);
            $command = "exiftool -ThumbnailImage -b {$rawFilePath} > {$outputPath}";

            $result = Process::timeout($timeout)->run($command);

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

        // Check if it's a valid image file
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return false;
        }

        // Check if it's a JPEG
        $allowedMimeTypes = ['image/jpeg', 'image/jpg'];

        return in_array($imageInfo['mime'], $allowedMimeTypes, true);
    }

    /**
     * Get all supported RAW extensions.
     */
    public function getSupportedExtensions(): array
    {
        return $this->rawExtensions;
    }

    /**
     * Clean up temporary files.
     */
    public function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}
