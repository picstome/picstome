<?php

namespace App\Models;

use App\Jobs\ProcessPhoto;
use App\Notifications\GalleryExpirationReminder;
use App\Notifications\SelectionLimitReached;
use App\Traits\FormatsFileSize;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class Gallery extends Model
{
    /** @use HasFactory<\Database\Factories\GalleryFactory> */
    use FormatsFileSize, HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'is_shared' => 'boolean',
            'is_share_selectable' => 'boolean',
            'is_share_downloadable' => 'boolean',
            'is_share_watermarked' => 'boolean',
            'is_public' => 'boolean',
            'keep_original_size' => 'boolean',
            'share_selection_limit' => 'integer',
            'share_description' => 'string',
            'expiration_date' => 'date',
            'selection_limit_notification_sent_at' => 'datetime',
            'portfolio_order' => 'integer',
            'are_comments_enabled' => 'boolean',
        ];
    }

    public static function booted()
    {
        static::creating(function (Gallery $gallery) {
            if (empty($gallery->ulid)) {
                $gallery->ulid = Str::ulid();
            }
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
            $stream = Storage::disk($photo->disk)->readStream($photo->path);

            $zip->addFileFromStream($photo->name, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        });

        $zip->finish();

        return $zip;
    }

    public function addPhoto(UploadedFile $photo)
    {
        $team = $this->team;

        $photoSize = $photo->getSize();

        if ($team->storage_limit !== null && ! $team->canStoreFile($photoSize)) {
            throw new \Exception('Not enough storage');
        }

        $photoModel = $this->photos()->create([
            'name' => $photo->getClientOriginalName(),
            'size' => $photoSize,
            'path' => FileUploadConfiguration::isUsingS3()
                ? tap($this->storage_path.'/'.$photo->getFilename(), function ($path) use ($photo) {
                    Storage::disk('s3')->move($photo->getRealPath(), $path);
                })
                : $photo->store(path: $this->storage_path, options: ['disk' => 's3']),
            'disk' => 's3',
            'status' => 'pending',
        ]);

        return $photoModel;
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "{$this->team->storage_path}/galleries/{$this->ulid}/photos";
        });
    }

    public function deletePhotos()
    {
        $this->photos()->cursor()->each(
            fn (Photo $photo) => $photo->deleteFromDisk()->delete()
        );

        return $this;
    }

    public function getTotalStorageSize()
    {
        return $this->photos()->sum('size');
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
                ProcessPhoto::dispatch($photo);
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
}
