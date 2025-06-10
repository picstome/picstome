<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DiskSpaceService
{
    public static function isLocalPublicDisk(): bool
    {
        return config('filesystems.disks.public.driver') === 'local';
    }

    public static function getFreeDiskSpace(): ?int
    {
        if (!self::isLocalPublicDisk()) {
            return null;
        }

        $publicPath = config('filesystems.disks.public.root');

        if (!$publicPath || !is_dir($publicPath)) {
            return null;
        }

        return disk_free_space($publicPath);
    }

    public static function getTotalDiskSpace(): ?int
    {
        if (!self::isLocalPublicDisk()) {
            return null;
        }

        $publicPath = config('filesystems.disks.public.root');

        if (!$publicPath || !is_dir($publicPath)) {
            return null;
        }

        return disk_total_space($publicPath);
    }

    public static function getUsedDiskSpace(): ?int
    {
        $total = self::getTotalDiskSpace();
        $free = self::getFreeDiskSpace();

        if ($total === null || $free === null) {
            return null;
        }

        return $total - $free;
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'N/A';
        }

        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = floor(log($bytes, 1024));
        $size = round($bytes / pow(1024, $unitIndex), 1);

        return $size . ' ' . $units[$unitIndex];
    }

    public static function getUsagePercentage(): ?float
    {
        $total = self::getTotalDiskSpace();
        $used = self::getUsedDiskSpace();

        if ($total === null || $used === null || $total == 0) {
            return null;
        }

        return round(($used / $total) * 100, 1);
    }
}
