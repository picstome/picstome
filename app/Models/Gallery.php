<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use App\Jobs\ProcessPhoto;
use App\Notifications\GalleryExpirationReminder;
use App\Notifications\SelectionLimitReached;
use App\Services\GalleryArchiveService;
use App\Services\PhotoIdentityService;
use App\Services\StorageUsageService;
use App\Traits\FormatsFileSize;
use Carbon\CarbonInterface;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipStream\ZipStream;

class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use FormatsFileSize, HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'is_shared' => 'boolean',
            'is_share_selectable' => 'boolean',
            'is_share_downloadable' => 'boolean',
            'is_share_watermarked' => 'boolean',
            'is_share_versions_visible' => 'boolean',
            'is_public' => 'boolean',
            'daily_shoot_date' => 'date',
            'keep_original_size' => 'boolean',
            'share_selection_limit' => 'integer',
            'share_description' => 'string',
            'expiration_date' => 'date',
            'selection_limit_notification_sent_at' => 'datetime',
            'portfolio_order' => 'integer',
            'are_comments_enabled' => 'boolean',
            'are_annotations_enabled' => 'boolean',
            'cover_focal_x' => 'float',
            'cover_focal_y' => 'float',
        ];
    }

    protected function sharePassword(): Attribute
    {
        return Attribute::set(function (?string $value) {
            if ($value === null || trim($value) === '') {
                return null;
            }

            return $value;
        });
    }

    public static function booted()
    {
        static::creating(function (Gallery $gallery) {
            if (empty($gallery->ulid)) {
                $gallery->ulid = Str::ulid();
            }
        });

        static::updated(function (Gallery $gallery) {
            if (! $gallery->wasChanged(['name', 'keep_original_size'])) {
                return;
            }

            try {
                app(GalleryArchiveService::class)->invalidate($gallery, ['all', 'favorites']);
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        // Editing the expiration date must re-arm the reminder email: otherwise
        // moving the date hides it and the owner is never warned again.
        static::updated(function (Gallery $gallery) {
            if (! $gallery->wasChanged('expiration_date')) {
                return;
            }

            if ($gallery->expiration_date === null || $gallery->reminder_sent_at === null) {
                return;
            }

            $gallery->forceFill(['reminder_sent_at' => null])->saveQuietly();
        });

        // The gallery header shows its own total; moving galleries between
        // teams would leave both cached totals stale.
        static::saved(function (Gallery $gallery) {
            if (! $gallery->wasChanged('team_id')) {
                return;
            }

            StorageUsageService::forgetForGallery($gallery->id);
            StorageUsageService::forgetForTeam($gallery->getOriginal('team_id'));
        });

        static::deleting(function (Gallery $gallery) {
            try {
                app(GalleryArchiveService::class)->deleteForGallery($gallery);
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        static::deleted(function (Gallery $gallery) {
            foreach (Photo::galleryCacheKeys((int) $gallery->id) as $key) {
                Cache::forget($key);
            }

            // The row is already gone, so the team id must come from the
            // in-memory model instead of another lookup.
            StorageUsageService::forgetForTeam($gallery->team_id);
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function archives()
    {
        return $this->hasMany(GalleryArchive::class);
    }

    /**
     * First displayable image in the gallery, exposed as a relation so it can
     * be eager loaded without hydrating every photo in the gallery.
     */
    public function firstImagePhoto(): HasOne
    {
        return $this->hasOne(Photo::class)->ofMany(
            ['id' => 'min'],
            fn (Builder $query) => $query->images()->ready(),
        );
    }

    /**
     * All historical versions across the gallery's logical photos.
     */
    public function photoVersions()
    {
        return $this->hasManyThrough(PhotoVersion::class, Photo::class);
    }

    public function photoshoot()
    {
        return $this->belongsTo(Photoshoot::class);
    }

    public function coverPhoto()
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    public function favorites()
    {
        return $this->photos()->favorited();
    }

    /**
     * Cached, naturally sorted by name photo ids of the gallery.
     *
     * Archived photos are excluded: they are hidden from every visitor-facing
     * list, including the public gallery, and only the owner's archived tab
     * reads them through `archivedPhotoIds()`.
     *
     * @return Collection<int, int>
     */
    public function photoIds(): Collection
    {
        return Cache::remember("gallery:{$this->id}:photos:ids", now()->addHours(1), function () {
            return $this->photos()->notArchived()->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
        });
    }

    /**
     * Cached, naturally sorted ids of photos that finished processing and may
     * be shown to visitors. Pending, processing and CDN-warming photos are
     * intentionally excluded, and so are archived ones.
     *
     * @return Collection<int, int>
     */
    public function readyPhotoIds(): Collection
    {
        return Cache::remember("gallery:{$this->id}:photos:ready:ids", now()->addHours(1), function () {
            return $this->photos()->ready()->notArchived()->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
        });
    }

    /**
     * Cached, naturally sorted ids of the images visitors can page through.
     *
     * The portfolio viewer needs the previous and next image of the current
     * one. Answering that from `readyPhotoIds()` alone would still hydrate
     * every row twice per visit just to test the extension, so the filter is
     * applied in SQL once and cached like the other visitor-facing lists.
     *
     * @return Collection<int, int>
     */
    public function readyImagePhotoIds(): Collection
    {
        return Cache::remember("gallery:{$this->id}:photos:ready:image:ids", now()->addHours(1), function () {
            return $this->photos()->ready()->notArchived()->images()->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
        });
    }

    /**
     * Cached, naturally sorted by name ids of the gallery's favorited photos.
     * Archived photos never appear in the client's favorite tab.
     *
     * @return Collection<int, int>
     */
    public function favoritePhotoIds(): Collection
    {
        return Cache::remember("gallery:{$this->id}:favorites:ids", now()->addHours(1), function () {
            return $this->favorites()->ready()->notArchived()->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
        });
    }

    /**
     * Cached, naturally sorted by name ids of the gallery's commented photos.
     *
     * @return Collection<int, int>
     */
    public function commentedPhotoIds(): Collection
    {
        return Cache::remember("gallery:{$this->id}:commented:ids", now()->addHours(1), function () {
            return $this->photos()->ready()->notArchived()->whereHas('comments')->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
        });
    }

    /**
     * Naturally sorted ids of the photos the photographer archived.
     * Owner-only: this list is never exposed to clients.
     *
     * Deliberately **not cached**. This is the one gallery list whose
     * *presence* decides whether the Archived tab exists, so a one-hour cached
     * copy made the tab appear late (or not at all) right after archiving, which
     * is exactly when the photographer looks for it. The query is a single
     * pluck on an indexed column and only the owner ever runs it, so caching
     * buys nothing and only adds a staleness window.
     *
     * @return Collection<int, int>
     */
    public function archivedPhotoIds(): Collection
    {
        return $this->photos()->archived()->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
    }

    /**
     * Hydrate the photos for the given ids, in the given order, with comment counts.
     *
     * @param  Collection<int, int>  $ids
     * @return EloquentCollection<int, Photo>
     */
    public function photosInOrder(Collection $ids): EloquentCollection
    {
        $photos = $this->photos()
            ->whereKey($ids)
            ->withCount('comments', 'versions')
            ->get()
            ->keyBy('id');

        $ordered = $ids->map(fn ($id) => $photos->get($id))->filter()->values();

        // PhotoUrlService reads `$photo->gallery->keep_original_size` for every
        // CDN URL. The relation is lazy, so a 60-tile wall used to fire 60
        // duplicate `select * from galleries` queries. They all belong here.
        return EloquentCollection::make(
            $ordered->each(fn (Photo $photo) => $photo->setRelation('gallery', $this))->all()
        );
    }

    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->whereNotNull('expiration_date')
            ->where('expiration_date', '<', now()->subDay());
    }

    #[Scope]
    protected function expiringSoon(Builder $query, int $days = 3): void
    {
        $threshold = now()->addDays($days);
        $query->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', $threshold);
    }

    #[Scope]
    protected function reminderNotSent(Builder $query): void
    {
        $query->whereNull('reminder_sent_at');
    }

    #[Scope]
    protected function public(Builder $query): void
    {
        $query->where('is_public', true)
            ->orderBy('portfolio_order')
            ->orderBy('created_at', 'desc');
    }

    #[Scope]
    protected function private(Builder $query): void
    {
        $query->where('is_public', false);
    }

    public function sendExpirationReminder(): void
    {
        $this->team->owner->notify(new GalleryExpirationReminder($this));
        $this->reminder_sent_at = now();
        $this->save();
    }

    public function isSelectionLimitReached(): bool
    {
        return $this->photos()->favorited()->count() === $this->share_selection_limit;
    }

    public function notifyOwnerWhenSelectionLimitReached(): void
    {
        if ($this->isSelectionLimitReached() && ! $this->selection_limit_notification_sent_at) {
            Notification::send($this->team->owner, new SelectionLimitReached($this));

            $this->update(['selection_limit_notification_sent_at' => now()]);
        }
    }

    public function download($favorites = false)
    {
        $zipName = Str::of($this->name)->slug()->append('.zip');

        $headers = [
            'Content-Disposition' => "attachment; filename=\"{$zipName}\"",
            'Content-Type' => 'application/octet-stream',
        ];

        $photos = $favorites ? $this->favorites : $this->photos;

        set_time_limit(1200); // 20 minutes

        return new StreamedResponse(fn () => $this->getPhotosZipStream($photos, $zipName), 200, $headers);
    }

    protected function getPhotosZipStream($photos, $zipName)
    {
        $zip = new ZipStream(outputName: $zipName);

        $photos->each(function ($photo) use ($zip) {
            if ($photo->raw_path) {
                $pathInfo = pathinfo($photo->name);
                $jpgFilename = $pathInfo['filename'].'.jpg';

                $jpgStream = Storage::disk($photo->disk)->readStream($photo->path);
                $zip->addFileFromStream($jpgFilename, $jpgStream);

                if (is_resource($jpgStream)) {
                    fclose($jpgStream);
                }

                $rawStream = Storage::disk($photo->disk)->readStream($photo->raw_path);
                $zip->addFileFromStream($photo->name, $rawStream);

                if (is_resource($rawStream)) {
                    fclose($rawStream);
                }

                return;
            }

            $stream = Storage::disk($photo->disk)->readStream($photo->path);
            $zip->addFileFromStream($photo->name, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        });

        $zip->finish();

        return $zip;
    }

    public function addPhoto(UploadedFile $photo): Photo
    {
        $team = $this->team;

        $photoSize = $photo->getSize();

        if ($team->storage_limit !== null && ! $team->canStoreFile($photoSize)) {
            throw new \Exception('Not enough storage');
        }

        $sourceName = static::sanitizeFileName($photo->getClientOriginalName());

        $photoModel = $this->photos()->create([
            'name' => $sourceName,
            'source_name' => $sourceName,
            'identity_key' => static::identityKey($sourceName),
            'size' => $photoSize,
            'stored_size' => $photoSize,
            'path' => $this->storeUploadedPhoto($photo, $sourceName),
            'disk' => 's3',
            'status' => 'pending',
            'version_count' => 1,
            'uploaded_at' => now(),
        ]);

        return $photoModel;
    }

    /**
     * Add a new version of an existing logical photo, retaining all previous
     * files on disk as PhotoVersion rows. Disabled when the feature flag is off.
     */
    public function addVersion(UploadedFile $photo, Photo $existing): Photo
    {
        if (! config('picstome.file_versions_enabled')) {
            throw new \RuntimeException('File versions are currently disabled.');
        }

        $team = $this->team;

        $photoSize = $photo->getSize();

        if ($team->storage_limit !== null && ! $team->canStoreFile($photoSize)) {
            throw new \Exception('Not enough storage');
        }

        $sourceName = static::sanitizeFileName($photo->getClientOriginalName());

        // Store first so a failed S3 transfer cannot bump version_count or
        // snapshot the previous version without a usable replacement.
        $newPath = $this->storeUploadedPhoto($photo, $sourceName);

        $this->prepareNewVersion($existing);

        $existing->update([
            'name' => $sourceName,
            'source_name' => $sourceName,
            'identity_key' => $existing->identity_key ?: static::identityKey($sourceName),
            'size' => $photoSize,
            'stored_size' => $photoSize,
            'stored_hash' => null,
            'path' => $newPath,
            'thumb_path' => null,
            'raw_path' => null,
            'disk' => 's3',
            'status' => 'pending',
            'current_version_id' => null,
            'uploaded_at' => now(),
        ]);

        return $existing;
    }

    /**
     * Replace the current version in place without creating a new version.
     * Older versions are retained and only unreferenced files are removed.
     */
    public function replaceCurrentVersion(UploadedFile $photo, Photo $existing): Photo
    {
        $team = $this->team;

        $photoSize = $photo->getSize();

        if ($team->storage_limit !== null && ! $team->canStoreFile(max(0, $photoSize - $existing->stored_size))) {
            throw new \Exception('Not enough storage');
        }

        $sourceName = static::sanitizeFileName($photo->getClientOriginalName());
        $newPath = $this->canReuseCurrentPath($existing->path, $sourceName)
            ? $this->replaceStoredPhoto($photo, $existing->path)
            : $this->storeUploadedPhoto($photo, $sourceName);
        $oldPath = $existing->path;
        $oldRawPath = $existing->raw_path;
        $oldDisk = $existing->diskOrDefault();
        $currentVersion = $existing->current_version_id
            ? $existing->versions()->whereKey($existing->current_version_id)->first()
            : null;

        DB::transaction(function () use ($existing, $currentVersion, $sourceName, $photoSize, $newPath, $oldPath, $oldRawPath, $oldDisk): void {
            $existing->update([
                'name' => $sourceName,
                'source_name' => $sourceName,
                'identity_key' => $existing->identity_key ?: static::identityKey($sourceName),
                'size' => $photoSize,
                'stored_size' => $photoSize,
                'stored_hash' => null,
                'path' => $newPath,
                'thumb_path' => null,
                'raw_path' => null,
                'disk' => 's3',
                'status' => 'pending',
                'uploaded_at' => now(),
            ]);

            if ($currentVersion) {
                $currentVersion->update([
                    'name' => $sourceName,
                    'source_name' => $sourceName,
                    'size' => $photoSize,
                    'stored_size' => $photoSize,
                    'stored_hash' => null,
                    'path' => $newPath,
                    'raw_path' => null,
                    'disk' => 's3',
                    'status' => 'pending',
                    'uploaded_at' => now(),
                ]);
            }

            $this->deleteUnreferencedPath($existing, $oldPath, $oldRawPath, $oldDisk);
        });

        return $existing;
    }

    /**
     * Snapshot the current photo (when needed) and reserve the next version
     * sequence number.
     */
    protected function prepareNewVersion(Photo $existing): void
    {
        $previousCount = $existing->version_count ?: 1;

        DB::transaction(function () use ($existing, $previousCount): void {
            $currentVersionMatches = $existing->current_version_id
                && $existing->currentVersion
                && $existing->currentVersion->path === $existing->path;

            if (! $currentVersionMatches) {
                $existing->snapshotAsVersion($previousCount);
                $existing->current_version_id = null;
            }

            $existing->version_count = $previousCount + 1;
            $existing->save();
        });
    }

    /**
     * A replacement keeps the same public path when the file extension does
     * not change, so pages already rendered by the browser keep resolving.
     */
    protected function canReuseCurrentPath(string $currentPath, string $sourceName): bool
    {
        return strtolower(pathinfo($currentPath, PATHINFO_EXTENSION))
            === strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));
    }

    protected function replaceStoredPhoto(UploadedFile $photo, string $path): string
    {
        $sourceSize = (int) @filesize($photo->getRealPath());

        if (FileUploadConfiguration::isUsingS3()) {
            $moved = Storage::disk('s3')->move($photo->getRealPath(), $path);

            if (! $moved) {
                throw new \RuntimeException('Could not replace the current photo in storage.');
            }

            $this->verifyStoredPhoto($path, $sourceSize);

            return $path;
        }

        $stored = Storage::disk('s3')->putFileAs(
            path: dirname($path),
            file: $photo,
            name: basename($path),
        );

        if (! is_string($stored) || $stored === '') {
            throw new \RuntimeException('Could not replace the current photo.');
        }

        $this->verifyStoredPhoto($stored, $sourceSize);

        return $stored;
    }

    /**
     * Detect the silent S3 failures where move()/putFileAs() return success but
     * the object is missing or incomplete. Without this the API stores a row
     * that ProcessPhoto can never read, leaving a broken photo in the gallery.
     */
    protected function verifyStoredPhoto(string $path, int $sourceSize): void
    {
        try {
            $exists = Storage::disk('s3')->exists($path);
            $storedSize = $exists ? (int) Storage::disk('s3')->size($path) : 0;
        } catch (Throwable $exception) {
            throw new \RuntimeException(
                'Could not verify the uploaded photo in S3.',
                0,
                $exception,
            );
        }

        if (! $exists) {
            throw new \RuntimeException('The uploaded photo is missing from S3.');
        }

        if ($sourceSize > 0 && $storedSize !== $sourceSize) {
            throw new \RuntimeException('The uploaded photo is incomplete in S3.');
        }
    }

    protected function storeUploadedPhoto(UploadedFile $photo, string $sourceName): string
    {
        $sourceSize = (int) @filesize($photo->getRealPath());

        if (FileUploadConfiguration::isUsingS3()) {
            $path = $this->storage_path.'/'.Str::ulid().'-'.$sourceName;

            // Laravel's move() returns false when the S3 transfer fails instead
            // of throwing. Without this check the database would point at a
            // missing object and ProcessPhoto would fail much later.
            $moved = Storage::disk('s3')->move($photo->getRealPath(), $path);

            if (! $moved) {
                throw new \RuntimeException('Could not store the uploaded photo in S3.');
            }

            $this->verifyStoredPhoto($path, $sourceSize);

            return $path;
        }

        $stored = $photo->store(path: $this->storage_path, options: ['disk' => 's3']);

        if (! is_string($stored) || $stored === '') {
            throw new \RuntimeException('Could not store the uploaded photo.');
        }

        $this->verifyStoredPhoto($stored, $sourceSize);

        return $stored;
    }

    /**
     * Queue deletion only for paths no longer referenced by any version.
     */
    protected function deleteUnreferencedPath(Photo $photo, ?string $path, ?string $rawPath, string $disk): void
    {
        foreach (array_filter([$path, $rawPath]) as $candidate) {
            // Split by column instead of `where(...)->orWhere(...)`: MySQL
            // cannot use an index across an OR of different columns.
            $referenced = $photo->versions()->where('path', $candidate)->exists()
                || $photo->versions()->where('raw_path', $candidate)->exists();

            if (! $referenced) {
                DeleteFromDisk::dispatch($candidate, $disk);
            }
        }
    }

    /**
     * Find the newest logical photo matching a normalized upload identity.
     */
    public function findLogicalPhotoBySourceName(string $clientName): ?Photo
    {
        $sourceName = static::sanitizeFileName($clientName);
        $identityKey = static::identityKey($sourceName);

        return $this->photos()
            ->where(function ($query) use ($identityKey, $sourceName) {
                $query->where('identity_key', $identityKey)
                    ->orWhere(function ($fallback) use ($sourceName) {
                        $fallback->whereNull('identity_key')
                            ->where(function ($legacy) use ($sourceName) {
                                $legacy->where('source_name', $sourceName)
                                    ->orWhere('name', $sourceName);
                            });
                    });
            })
            ->latest('updated_at')
            ->first();
    }

    /**
     * Normalize a client filename into the photo identity used for versioning.
     */
    public static function identityKey(string $filename): string
    {
        return PhotoIdentityService::key($filename);
    }

    /**
     * Deep link the gallery UI uses to ask the Picstome Sync desktop app to
     * open this gallery.
     */
    public function syncDeepLink(): string
    {
        return 'picstome://sync?'.http_build_query([
            'gallery' => (string) $this->ulid,
            'name' => $this->name,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Serialize version resolution for the same gallery + identity so parallel
     * uploads cannot create duplicate logical photos.
     */
    public function withPhotoIdentityLock(string $identityKey, \Closure $callback): mixed
    {
        return Cache::lock("gallery:{$this->id}:photo-identity:{$identityKey}", 60)
            ->block(30, $callback);
    }

    public function addPhotoFromStoredPath(string $path, string $clientName, int $size): Photo
    {
        $team = $this->team;

        if ($team->storage_limit !== null && ! $team->canStoreFile($size)) {
            throw new \Exception('Not enough storage');
        }

        $sourceName = static::sanitizeFileName($clientName);

        return $this->photos()->create([
            'name' => $sourceName,
            'source_name' => $sourceName,
            'identity_key' => static::identityKey($sourceName),
            'size' => $size,
            'stored_size' => $size,
            'path' => $path,
            'thumb_path' => null,
            'raw_path' => null,
            'disk' => config('picstome.disk', 's3'),
            'status' => 'pending',
            'version_count' => 1,
            'uploaded_at' => now(),
        ]);
    }

    public function addVersionFromStoredPath(Photo $existing, string $path, string $clientName, int $size): Photo
    {
        if (! config('picstome.file_versions_enabled')) {
            throw new \RuntimeException('File versions are currently disabled.');
        }

        $team = $this->team;

        if ($team->storage_limit !== null && ! $team->canStoreFile($size)) {
            throw new \Exception('Not enough storage');
        }

        $sourceName = static::sanitizeFileName($clientName);

        $this->prepareNewVersion($existing);

        $existing->update([
            'name' => $sourceName,
            'source_name' => $sourceName,
            'identity_key' => $existing->identity_key ?: static::identityKey($sourceName),
            'size' => $size,
            'stored_size' => $size,
            'path' => $path,
            'thumb_path' => null,
            'raw_path' => null,
            'disk' => config('picstome.disk', 's3'),
            'status' => 'pending',
            'current_version_id' => null,
            'uploaded_at' => now(),
        ]);

        return $existing;
    }

    /**
     * Normalize a client-provided file name to a safe subset of characters.
     */
    public static function sanitizeFileName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "{$this->team->storage_path}/galleries/{$this->ulid}/photos";
        });
    }

    /**
     * CSS `object-position` value that mirrors the stored cover focal point.
     *
     * Every cover thumbnail is rendered with `object-cover`, so the browser
     * crops it around this point instead of its own centre. Returns null when
     * the gallery has no explicit cover, letting the fallback image keep the
     * default centred crop.
     */
    protected function coverFocalPosition(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->cover_photo_id) {
                return null;
            }

            $x = max(0.0, min(1.0, (float) ($this->cover_focal_x ?? 0.5)));
            $y = max(0.0, min(1.0, (float) ($this->cover_focal_y ?? 0.5)));

            return round($x * 100).'% '.round($y * 100).'%';
        });
    }

    public function deletePhotos()
    {
        $this->photos()->with('versions')->cursor()->each(
            fn (Photo $photo) => $photo->deleteFromDisk()->delete()
        );

        return $this;
    }

    public function getTotalStorageSize(): int
    {
        return StorageUsageService::forGallery($this);
    }

    public function getFormattedStorageSize()
    {
        return $this->formatFileSize($this->getTotalStorageSize());
    }

    public function setCoverPhoto(Photo $photo)
    {
        if (! $this->is($photo->gallery)) {
            throw new \Exception('Photo does not belong to this gallery');
        }

        if (! $photo->isImage()) {
            throw new \InvalidArgumentException('Only images can be used as a gallery cover.');
        }

        $this->update([
            'cover_photo_id' => $photo->id,
            'cover_focal_x' => 0.5,
            'cover_focal_y' => 0.5,
        ]);
    }

    public function setCoverFocalPoint(float $x, float $y): void
    {
        $this->update([
            'cover_focal_x' => max(0.0, min(1.0, $x)),
            'cover_focal_y' => max(0.0, min(1.0, $y)),
        ]);
    }

    public function removeCoverPhoto()
    {
        $this->update(['cover_photo_id' => null]);
        $this->setRelation('coverPhoto', null);
    }

    public function togglePublic()
    {
        if (! $this->is_public) {
            $this->makePublic();

            return;
        }

        $this->makePrivate();
    }

    public function makePublic()
    {
        $maxOrder = $this->team->galleries()->public()->whereNotNull('portfolio_order')->max('portfolio_order') ?? 0;

        $updateData = [
            'is_public' => true,
            'portfolio_order' => $maxOrder + 1,
            'expiration_date' => null,
        ];

        $shouldProcessPhotos = false;

        if ($this->keep_original_size) {
            $updateData['keep_original_size'] = false;
            $shouldProcessPhotos = true;
        }

        $this->update($updateData);

        if ($shouldProcessPhotos) {
            foreach ($this->photos()->cursor() as $photo) {
                ProcessPhoto::dispatch($photo, $photo->version_count);
            }
        }
    }

    public function makePrivate()
    {
        // A gallery may not outlive one year from its creation unless the team
        // has an active subscription, so turning a public gallery private
        // cannot be used to reset the clock indefinitely.
        $cap = $this->retentionCap();
        $expiration = now()->addMonth();

        if ($cap !== null && $expiration->gt($cap)) {
            $expiration = $cap;
        }

        $this->update([
            'is_public' => false,
            'portfolio_order' => null,
            'expiration_date' => $expiration,
        ]);
    }

    /**
     * Latest date this gallery may expire, or null when the team keeps
     * galleries forever.
     *
     * Free and lifetime galleries are capped at one year from creation. A
     * gallery that already passed its cap keeps its current date, and one with
     * no date gets a month of grace, so enabling the rule never deletes
     * something the owner did not choose.
     */
    public function retentionCap(): ?CarbonInterface
    {
        if ($this->team?->hasUnlimitedRetention()) {
            return null;
        }

        $cap = $this->created_at->copy()->addYear();

        // Galleries created before the retention rule may already be past it.
        if ($cap->isPast()) {
            $current = $this->expiration_date;

            return $current?->isFuture() ? $current->copy() : now()->addMonth();
        }

        $current = $this->expiration_date;

        if ($current?->gt($cap)) {
            return $current->copy();
        }

        return $cap;
    }

    public function reorder(int $newOrder): void
    {
        $currentOrder = $this->portfolio_order;

        if (is_null($currentOrder)) {
            $this->update(['portfolio_order' => $newOrder]);

            return;
        }

        if ($newOrder > $currentOrder) {
            $this->team->galleries()
                ->public()
                ->whereNotNull('portfolio_order')
                ->where('portfolio_order', '>', $currentOrder)
                ->where('portfolio_order', '<=', $newOrder)
                ->decrement('portfolio_order');
        } elseif ($newOrder < $currentOrder) {
            $this->team->galleries()
                ->public()
                ->whereNotNull('portfolio_order')
                ->where('portfolio_order', '>=', $newOrder)
                ->where('portfolio_order', '<', $currentOrder)
                ->increment('portfolio_order');
        }

        $this->update(['portfolio_order' => $newOrder]);
    }

    /**
     * Get the first image (not video) in the gallery.
     */
    public function firstImage()
    {
        $photo = Cache::remember("gallery:{$this->id}:first_image", now()->addHours(24), function () {
            if ($this->relationLoaded('photos')) {
                return $this->photos->first(fn (Photo $photo) => $photo->isImage() && ! $photo->isArchived());
            }

            return $this->firstImagePhoto;
        });

        // The relation is attached after the cache read, never inside it: the
        // cached row must not embed a snapshot of this gallery, or a later
        // `keep_original_size` change would keep resolving the old CDN variant.
        return $photo?->setRelation('gallery', $this);
    }

    /**
     * Get the cached photo count for the gallery
     */
    public function photosCount()
    {
        return Cache::remember("gallery:{$this->id}:photos_count", now()->addHours(24), function () {
            return $this->photos()->count();
        });
    }

    /**
     * Media totals used by grid tabs and the download panel.
     *
     * @return array{all: int, images: int, videos: int, pdfs: int}
     */
    public function mediaCounts(): array
    {
        return Cache::remember("gallery:{$this->id}:media_counts", now()->addHours(1), function (): array {
            return [
                'all' => $this->photos()->ready()->notArchived()->count(),
                'images' => $this->photos()->ready()->notArchived()->images()->count(),
                'videos' => $this->photos()->ready()->notArchived()->where(function (Builder $query) {
                    foreach (['mp4', 'webm', 'ogg'] as $extension) {
                        $query->orWhere('path', 'like', '%.'.$extension);
                    }
                })->count(),
                'pdfs' => $this->photos()->ready()->notArchived()->where('path', 'like', '%.pdf')->count(),
            ];
        });
    }

    /**
     * Rough ZIP size, answered by one aggregate query over the photo rows.
     * The exact size is stored on `gallery_archives.size_bytes` once the ZIP
     * is built, so the UI never needs to look at the bucket.
     */
    public function downloadSize(): int
    {
        return Cache::remember("gallery:{$this->id}:download_size", now()->addHours(1), function (): int {
            return app(GalleryArchiveService::class)->estimatedSize($this, 'all');
        });
    }

    public function commentsCount(): int
    {
        return Cache::remember("gallery:{$this->id}:comments_count", now()->addHours(1), function (): int {
            return PhotoComment::query()
                ->whereHas('photo', fn (Builder $query) => $query->where('gallery_id', $this->id))
                ->count();
        });
    }

    /**
     * Rated photos in this gallery, for the owner header summary.
     *
     * Cached for an hour like the rest of the gallery counters. The key is
     * dropped by `Photo::galleryCacheKeys()` on every photo write, so a rating
     * is visible immediately; the TTL is only a safety net.
     */
    public function ratedPhotoCount(): int
    {
        return Cache::remember("gallery:{$this->id}:rated_count", now()->addHours(1), function (): int {
            return $this->photos()->rated()->count();
        });
    }

    #[Attribute]
    protected function slug(): Attribute
    {
        return Attribute::get(fn () => Str::slug($this->name) ?: $this->ulid);
    }
}
