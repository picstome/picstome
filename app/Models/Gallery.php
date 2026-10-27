<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use App\Jobs\ProcessPhoto;
use App\Notifications\GalleryExpirationReminder;
use App\Notifications\SelectionLimitReached;
use App\Services\PhotoIdentityService;
use App\Services\StorageUsageService;
use App\Traits\FormatsFileSize;
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
            'keep_original_size' => 'boolean',
            'share_selection_limit' => 'integer',
            'share_description' => 'string',
            'expiration_date' => 'date',
            'selection_limit_notification_sent_at' => 'datetime',
            'portfolio_order' => 'integer',
            'are_comments_enabled' => 'boolean',
            'are_annotations_enabled' => 'boolean',
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

        static::deleted(function (Gallery $gallery) {
            Cache::forget("gallery:{$gallery->id}:first_image");
            Cache::forget("gallery:{$gallery->id}:photos_count");
            Cache::forget("gallery:{$gallery->id}:photos:ids");
            Cache::forget("gallery:{$gallery->id}:favorites:ids");
            Cache::forget("gallery:{$gallery->id}:commented:ids");
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

    /**
     * First displayable image in the gallery, exposed as a relation so it can
     * be eager loaded without hydrating every photo in the gallery.
     */
    public function firstImagePhoto(): HasOne
    {
        return $this->hasOne(Photo::class)->ofMany(
            ['id' => 'min'],
            fn (Builder $query) => $query->images(),
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
     * @return Collection<int, int>
     */
    public function photoIds(): Collection
    {
        return Cache::remember("gallery:{$this->id}:photos:ids", now()->addHours(1), function () {
            return $this->photos()->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
        });
    }

    /**
     * Cached, naturally sorted by name ids of the gallery's favorited photos.
     *
     * @return Collection<int, int>
     */
    public function favoritePhotoIds(): Collection
    {
        return Cache::remember("gallery:{$this->id}:favorites:ids", now()->addHours(1), function () {
            return $this->favorites()->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
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
            return $this->photos()->whereHas('comments')->pluck('name', 'id')->sort(SORT_NATURAL)->keys();
        });
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
            ->withCount('comments')
            ->get()
            ->keyBy('id');

        return EloquentCollection::make($ids->map(fn ($id) => $photos->get($id))->filter()->values()->all());
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

        $this->prepareNewVersion($existing);

        $existing->update([
            'name' => $sourceName,
            'source_name' => $sourceName,
            'identity_key' => $existing->identity_key ?: static::identityKey($sourceName),
            'size' => $photoSize,
            'stored_size' => $photoSize,
            'path' => $this->storeUploadedPhoto($photo, $sourceName),
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
        $newPath = $this->storeUploadedPhoto($photo, $sourceName);
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

    protected function storeUploadedPhoto(UploadedFile $photo, string $sourceName): string
    {
        if (FileUploadConfiguration::isUsingS3()) {
            $path = $this->storage_path.'/'.Str::ulid().'-'.$sourceName;
            Storage::disk('s3')->move($photo->getRealPath(), $path);

            return $path;
        }

        return $photo->store(path: $this->storage_path, options: ['disk' => 's3']);
    }

    /**
     * Queue deletion only for paths no longer referenced by any version.
     */
    protected function deleteUnreferencedPath(Photo $photo, ?string $path, ?string $rawPath, string $disk): void
    {
        foreach (array_filter([$path, $rawPath]) as $candidate) {
            $referenced = $photo->versions()
                ->where(function ($query) use ($candidate) {
                    $query->where('path', $candidate)->orWhere('raw_path', $candidate);
                })
                ->exists();

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

        $this->update(['cover_photo_id' => $photo->id]);
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
        $this->update([
            'is_public' => false,
            'portfolio_order' => null,
            'expiration_date' => now()->addMonth(),
        ]);
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
        return Cache::remember("gallery:{$this->id}:first_image", now()->addHours(24), function () {
            if ($this->relationLoaded('photos')) {
                return $this->photos->first(fn (Photo $photo) => $photo->isImage());
            }

            return $this->firstImagePhoto;
        });
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

    #[Attribute]
    protected function slug(): Attribute
    {
        return Attribute::get(fn () => Str::slug($this->name) ?: $this->ulid);
    }
}
