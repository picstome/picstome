<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Owner-grid filter and sort state.
 *
 * One immutable value object feeds the gallery wall, the single-photo viewer
 * and the reveal-on-return logic, so the photo you open is always the next one
 * you saw on the wall. It also keeps the filter semantics in one place:
 *
 *  - the rating filter is exact (3 means exactly 3 stars); photographers found
 *    the >= / <= comparisons hard to read at a glance.
 *  - unrated and unfavourited photos always sort last, regardless of direction,
 *    so "lowest rated first" starts at 1 star instead of a wall of blanks.
 *  - name ordering is natural (photo2 before photo10), matching the existing
 *    gallery lists that sort with SORT_NATURAL.
 */
final readonly class GalleryPhotoQuery
{
    public const SORTS = ['name', 'date', 'rating', 'favorites', 'size', 'versions'];

    public const DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        public ?int $rating = null,
        public bool $favoritesOnly = false,
        public string $sort = 'name',
        public string $direction = 'asc',
    ) {}

    /**
     * Build a sanitised query from URL/Livewire input. Unknown values fall back
     * to the defaults instead of reaching SQL.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5],
        ]);

        $sort = in_array($input['sort'] ?? null, self::SORTS, true)
            ? $input['sort']
            : 'name';

        $direction = in_array($input['direction'] ?? null, self::DIRECTIONS, true)
            ? $input['direction']
            : self::defaultDirection($sort);

        return new self(
            rating: $rating === false ? null : $rating,
            favoritesOnly: filter_var($input['favorites'] ?? false, FILTER_VALIDATE_BOOLEAN),
            sort: $sort,
            direction: $direction,
        );
    }

    /**
     * The direction a field opens with when the photographer picks it. Dates,
     * ratings, favourites, sizes and versions all read best highest-first;
     * names read best A-Z.
     */
    public static function defaultDirection(string $sort): string
    {
        return $sort === 'name' ? 'asc' : 'desc';
    }

    /**
     * Compact query string for tile links, the viewer URL and the Back link.
     *
     * @return array<string, int|string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'rating' => $this->rating,
            'favorites' => $this->favoritesOnly ? 1 : null,
            'sort' => $this->sort === 'name' ? null : $this->sort,
            'direction' => $this->direction === self::defaultDirection($this->sort) ? null : $this->direction,
        ], fn ($value) => $value !== null);
    }

    public function hasFilters(): bool
    {
        return $this->rating !== null || $this->favoritesOnly;
    }

    /**
     * Ordered photo ids for one gallery tab.
     *
     * Cached under the gallery's monotonic cache version so rapid culling
     * through the viewer does not re-pluck thousands of ids per keystroke.
     *
     * @param  'all'|'favorited'|'commented'|'archived'  $tab
     * @return Collection<int, int>
     */
    public function idsFor(Gallery $gallery, string $tab = 'all'): Collection
    {
        return Cache::remember(
            $this->cacheKey($gallery, $tab),
            now()->addHours(1),
            fn () => $this->build($gallery, $tab),
        );
    }

    /**
     * @param  'all'|'favorited'|'commented'|'archived'  $tab
     * @return Collection<int, int>
     */
    protected function build(Gallery $gallery, string $tab): Collection
    {
        $query = $this->baseQuery($gallery, $tab);

        $this->applyFilters($query);

        if ($this->sort === 'name') {
            $names = $query->pluck('name', 'id');

            if ($this->direction === 'desc') {
                $names = $names->sort(SORT_NATURAL)->reverse();
            } else {
                $names = $names->sort(SORT_NATURAL);
            }

            return $names->keys()->values();
        }

        $this->applyOrder($query);

        return $query->pluck('id')->values();
    }

    /**
     * @param  'all'|'favorited'|'commented'|'archived'  $tab
     */
    protected function baseQuery(Gallery $gallery, string $tab): Builder
    {
        $query = $gallery->photos()->getQuery();

        return match ($tab) {
            'favorited' => $query->favorited()->ready()->notArchived(),
            'commented' => $query->ready()->notArchived()->whereHas('comments'),
            'archived' => $query->archived(),
            // The Favorites tab applies `favoritesOnly` on the All photos
            // grid. Match the old favourites view by excluding photos that are
            // still processing, so the tab count and the export list agree.
            default => $query->notArchived()->when($this->favoritesOnly, fn (Builder $query) => $query->ready()),
        };
    }

    protected function applyFilters(Builder $query): void
    {
        if ($this->favoritesOnly) {
            $query->whereNotNull('favorited_at');
        }

        if ($this->rating === null) {
            return;
        }

        $query->where('rating', $this->rating);
    }

    protected function applyOrder(Builder $query): void
    {
        match ($this->sort) {
            'date' => $query
                ->orderByRaw('COALESCE(uploaded_at, created_at) '.$this->direction)
                ->orderBy('id', $this->direction),
            'rating' => $query
                ->orderByRaw('CASE WHEN rating IS NULL THEN 1 ELSE 0 END')
                ->orderBy('rating', $this->direction)
                ->orderBy('id'),
            'favorites' => $query
                ->orderByRaw('CASE WHEN favorited_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('favorited_at', $this->direction)
                ->orderBy('id'),
            'size' => $query
                ->orderBy('size', $this->direction)
                ->orderBy('id'),
            'versions' => $query
                // Sort by how many versions are really stored, not by the
                // `version_count` sequence, which stays high after deletions.
                ->orderBy(
                    PhotoVersion::query()
                        ->selectRaw('count(*)')
                        ->whereColumn('photo_versions.photo_id', 'photos.id'),
                    $this->direction,
                )
                ->orderBy('id'),
            default => $query->orderBy('id'),
        };
    }

    /**
     * @param  'all'|'favorited'|'commented'|'archived'  $tab
     */
    protected function cacheKey(Gallery $gallery, string $tab): string
    {
        $signature = md5(json_encode([
            $tab,
            $this->rating,
            $this->favoritesOnly,
            $this->sort,
            $this->direction,
        ]));

        return sprintf(
            'gallery:%d:q:%d:%s',
            $gallery->id,
            Photo::galleryCacheVersion($gallery->id),
            $signature,
        );
    }
}
