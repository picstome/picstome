<?php

namespace App\Services;

use App\Traits\FormatsFileSize;

class DiskSpaceService
{
    use FormatsFileSize;

    public static function isLocalPublicDisk(): bool
    {
        return config('filesystems.disks.public.driver') === 'local';
    }

    private static function getPublicDiskPath(): ?string
    {
        if (! self::isLocalPublicDisk()) {
            return null;
        }

        $publicPath = config('filesystems.disks.public.root');

        if (! $publicPath || ! is_dir($publicPath)) {
            return null;
        }

        return $publicPath;
    }

    public static function getFreeDiskSpace(): ?int
    {
        $path = self::getPublicDiskPath();
        if (! $path) {
            return null;
        }

        $freeSpace = disk_free_space($path);

        return $freeSpace !== false ? $freeSpace : null;
    }

    public static function getTotalDiskSpace(): ?int
    {
        $path = self::getPublicDiskPath();
        if (! $path) {
            return null;
        }

        $totalSpace = disk_total_space($path);

        return $totalSpace !== false ? $totalSpace : null;
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

        return (new self)->formatFileSize($bytes, 1);
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
