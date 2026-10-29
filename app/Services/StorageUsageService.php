<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Calculates billable storage for galleries and teams.
 *
 * Current files live on the photos row; historical files live on
 * photo_versions. Rows referenced by photos.current_version_id are excluded
 * from the version total because the current file is already counted once.
 */
class StorageUsageService
{
    public static function forGallery(Gallery $gallery): int
    {
        $current = DB::table('photos')
            ->where('gallery_id', $gallery->id)
            ->sum('stored_size');

        $versions = DB::table('photo_versions')
            ->join('photos', 'photo_versions.photo_id', '=', 'photos.id')
            ->where('photos.gallery_id', $gallery->id)
            ->whereNotIn('photo_versions.id', self::currentVersionIds($gallery->id))
            ->sum('photo_versions.stored_size');

        return (int) $current + (int) $versions;
    }

    public static function archivesForGallery(Gallery $gallery): int
    {
        return (int) DB::table('gallery_archives')
            ->where('gallery_id', $gallery->id)
            ->whereNotNull('path')
            ->sum('size_bytes');
    }

    public static function archivesForTeam(Team $team): int
    {
        return (int) DB::table('gallery_archives')
            ->where('team_id', $team->id)
            ->whereNotNull('path')
            ->sum('size_bytes');
    }

    public static function forTeam(Team $team): int
    {
        $current = DB::table('photos')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->where('galleries.team_id', $team->id)
            ->sum('photos.stored_size');

        $versions = DB::table('photo_versions')
            ->join('photos', 'photo_versions.photo_id', '=', 'photos.id')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->where('galleries.team_id', $team->id)
            ->whereNotIn('photo_versions.id', self::currentVersionIds())
            ->sum('photo_versions.stored_size');

        $invoices = DB::table('invoices')
            ->where('team_id', $team->id)
            ->sum('pdf_size');

        return (int) $current + (int) $versions + (int) $invoices;
    }

    /**
     * Total billable storage across every team, including retained versions.
     */
    public static function total(): int
    {
        $current = DB::table('photos')->sum('stored_size');

        $versions = DB::table('photo_versions')
            ->whereNotIn('id', self::currentVersionIds())
            ->sum('stored_size');

        $invoices = DB::table('invoices')->sum('pdf_size');

        return (int) $current + (int) $versions + (int) $invoices;
    }

    protected static function currentVersionIds(?int $galleryId = null)
    {
        return DB::table('photos')
            ->select('current_version_id')
            ->whereNotNull('current_version_id')
            ->when($galleryId, fn ($query) => $query->where('gallery_id', $galleryId));
    }
}
