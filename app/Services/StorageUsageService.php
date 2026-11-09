<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;
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
    /**
     * Cached billable bytes for one gallery, used by the gallery header.
     *
     * Same pattern as the team total: a page reads it once, but the gallery
     * page also renders the individual media lists that share the invalidation.
     */
    public static function forGallery(Gallery $gallery): int
    {
        return Cache::remember(self::galleryCacheKey($gallery->id), now()->addMinutes(10), function () use ($gallery): int {
            return self::calculateForGallery($gallery->id);
        });
    }

    /**
     * Uncached gallery total.
     */
    protected static function calculateForGallery(int|string $galleryId): int
    {
        $current = DB::table('photos')
            ->where('gallery_id', $galleryId)
            ->sum('stored_size');

        $versions = DB::table('photo_versions')
            ->join('photos', 'photo_versions.photo_id', '=', 'photos.id')
            ->where('photos.gallery_id', $galleryId)
            ->whereNotIn('photo_versions.id', self::currentVersionIds($galleryId))
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

    /**
     * Per-request memo. Reading the same team's total five times on one page
     * must not hit the cache store five times (the cache can be the database).
     *
     * @var array<int|string, int>
     */
    protected static array $requestMemo = [];

    public static function forTeam(Team $team): int
    {
        // Bounded: queue workers are long-lived and touch many teams.
        if (count(self::$requestMemo) >= 100) {
            self::$requestMemo = [];
        }

        return self::$requestMemo[$team->id] ??= Cache::remember(
            self::teamCacheKey($team->id),
            now()->addMinutes(10),
            fn (): int => self::calculateForTeam($team->id),
        );
    }

    /**
     * Uncached team total.
     *
     * Storage quota decisions (`Team::canStoreFile()`, the almost-full email,
     * invoice issuance) must never run on a ten-minute-old number: a stale
     * total is the difference between accepting and rejecting an upload that
     * overflows the plan. Everything that only *displays* bytes uses
     * `forTeam()`.
     */
    public static function forTeamFresh(Team $team): int
    {
        $teamId = $team->id;

        unset(self::$requestMemo[$teamId]);

        $total = self::calculateForTeam($teamId);

        self::$requestMemo[$teamId] = $total;

        return $total;
    }

    /**
     * Drop the cached team total. Called on every write that moves bytes:
     * photo created/updated/deleted, gallery deleted and invoices issued.
     */
    public static function forgetForTeam(int|string|null $teamId): void
    {
        if ($teamId === null || $teamId === '') {
            return;
        }

        unset(self::$requestMemo[$teamId]);

        Cache::forget(self::teamCacheKey($teamId));
    }

    /**
     * Drop the cached total of the team owning the gallery, plus the cached
     * gallery total itself (the header reads it on every gallery view).
     */
    public static function forgetForGallery(int|string|null $galleryId): void
    {
        if ($galleryId === null || $galleryId === '') {
            return;
        }

        Cache::forget(self::galleryCacheKey($galleryId));

        self::forgetForTeam(self::teamIdForGallery($galleryId));
    }

    protected static function galleryCacheKey(int|string $galleryId): string
    {
        return "gallery:{$galleryId}:storage_used";
    }

    /**
     * Gallery to team lookup, memoised for the request. A sync job writing
     * hundreds of photos of the same gallery must not pay one query each.
     *
     * @var array<int|string, int|string|null>
     */
    protected static array $galleryTeamMemo = [];

    protected static function teamIdForGallery(int|string $galleryId): int|string|null
    {
        if (array_key_exists($galleryId, self::$galleryTeamMemo)) {
            return self::$galleryTeamMemo[$galleryId];
        }

        // Long-lived queue workers process photos of many galleries, so the
        // memo is bounded instead of growing for the lifetime of the process.
        if (count(self::$galleryTeamMemo) >= 100) {
            self::$galleryTeamMemo = [];
        }

        return self::$galleryTeamMemo[$galleryId] = DB::table('galleries')
            ->where('id', $galleryId)
            ->value('team_id');
    }

    /**
     * Drop the cached total of the team owning the photo. Used by photo
     * versions, which do not carry the gallery id.
     */
    public static function forgetForPhoto(int|string|null $photoId): void
    {
        if ($photoId === null || $photoId === '') {
            return;
        }

        $galleryId = DB::table('photos')->where('id', $photoId)->value('gallery_id');

        if ($galleryId === null) {
            return;
        }

        self::forgetForGallery($galleryId);
    }

    /**
     * Sprinkled between tests so a memoised total cannot leak across cases.
     */
    public static function flushRequestMemo(): void
    {
        self::$requestMemo = [];
        self::$galleryTeamMemo = [];
    }

    protected static function teamCacheKey(int|string $teamId): string
    {
        return "team:{$teamId}:storage_used";
    }

    /**
     * Uncached total. Three aggregates in one query round trip each; callers
     * on a render path must go through `forTeam()` instead.
     */
    protected static function calculateForTeam(int|string $teamId): int
    {
        $current = DB::table('photos')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->where('galleries.team_id', $teamId)
            ->sum('photos.stored_size');

        $versions = DB::table('photo_versions')
            ->join('photos', 'photo_versions.photo_id', '=', 'photos.id')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->where('galleries.team_id', $teamId)
            ->whereNotIn('photo_versions.id', self::currentVersionIds())
            ->sum('photo_versions.stored_size');

        $invoices = DB::table('invoices')
            ->where('team_id', $teamId)
            ->sum('pdf_size');

        return (int) $current + (int) $versions + (int) $invoices;
    }

    /**
     * Billable bytes for many teams at once.
     *
     * The admin users table renders a storage bar per row. Calling `forTeam()`
     * per row meant three aggregates per user (36 queries on 18 users); this
     * collapses them into three grouped queries regardless of the page size.
     *
     * @param  list<int|string>  $teamIds
     * @return array<int|string, int>
     */
    public static function forTeams(array $teamIds): array
    {
        $teamIds = array_values(array_unique($teamIds));

        if ($teamIds === []) {
            return [];
        }

        $totals = array_fill_keys($teamIds, 0);

        DB::table('photos')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->whereIn('galleries.team_id', $teamIds)
            ->selectRaw('galleries.team_id as team_id, SUM(photos.stored_size) as bytes')
            ->groupBy('galleries.team_id')
            ->get()
            ->each(function ($row) use (&$totals): void {
                $totals[$row->team_id] = (int) ($totals[$row->team_id] ?? 0) + (int) $row->bytes;
            });

        DB::table('photo_versions')
            ->join('photos', 'photo_versions.photo_id', '=', 'photos.id')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->whereIn('galleries.team_id', $teamIds)
            ->whereNotIn('photo_versions.id', self::currentVersionIds())
            ->selectRaw('galleries.team_id as team_id, SUM(photo_versions.stored_size) as bytes')
            ->groupBy('galleries.team_id')
            ->get()
            ->each(function ($row) use (&$totals): void {
                $totals[$row->team_id] = (int) ($totals[$row->team_id] ?? 0) + (int) $row->bytes;
            });

        DB::table('invoices')
            ->whereIn('team_id', $teamIds)
            ->selectRaw('team_id, SUM(pdf_size) as bytes')
            ->groupBy('team_id')
            ->get()
            ->each(function ($row) use (&$totals): void {
                $totals[$row->team_id] = (int) ($totals[$row->team_id] ?? 0) + (int) $row->bytes;
            });

        return $totals;
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
