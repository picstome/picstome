<?php

namespace App\Traits;

trait FormatsFileSize
{
    public function formatFileSize(int $bytes, int $precision = 2): string
    {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = floor(log($bytes, 1024));
        $unitIndex = min($unitIndex, count($units) - 1);
        $size = round($bytes / pow(1024, $unitIndex), $precision);

        return $size.' '.$units[$unitIndex];
    }
}
