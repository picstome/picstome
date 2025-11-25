<?php

namespace App\Services\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isRawFile(string $filename)
 * @method static bool isExifToolAvailable()
 * @method static bool extractJpgFromRaw(string $rawFilePath, string $outputPath)
 * @method static array getSupportedExtensions()
 * @method static void cleanupTempFile(string $filePath)
 *
 * @see \App\Services\RawPhotoService
 */
class RawPhoto extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\RawPhotoService::class;
    }
}
