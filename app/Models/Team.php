<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Laravel\Cashier\Billable;
use Stevebauman\Purify\Casts\PurifyHtmlOnGet;

class Team extends Model
{
    use Billable;

    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    protected $guarded = [];

    public function canStoreFile(int $size): bool
    {
        if ($this->storage_limit === null) {
            return true;
        }

        return ($this->calculateStorageUsed() + $size) <= $this->storage_limit;
    }

    protected function casts()
    {
        return [
            'personal_team' => 'boolean',
            'lifetime' => 'boolean',
            'bio' => PurifyHtmlOnGet::class,
            'other_social_links' => 'array',
            'portfolio_public_disabled' => 'boolean',
            'stripe_onboarded' => 'boolean',
            'show_pay_button' => 'boolean',
            'stripe_test_mode' => 'boolean',
            'stripe_test_onboarded' => 'boolean',
            'dismissed_setup_steps' => 'array',
        ];
    }

    public function getInstagramUrlAttribute()
    {
        return $this->instagram_handle ? "https://instagram.com/{$this->instagram_handle}" : null;
    }

    public function getYoutubeUrlAttribute()
    {
        return $this->youtube_handle ? "https://youtube.com/{$this->youtube_handle}" : null;
    }

    public function getFacebookUrlAttribute()
    {
        return $this->facebook_handle ? "https://facebook.com/{$this->facebook_handle}" : null;
    }

    public function getXUrlAttribute()
    {
        return $this->x_handle ? "https://x.com/{$this->x_handle}" : null;
    }

    public function getTiktokUrlAttribute()
    {
        return $this->tiktok_handle ? "https://tiktok.com/@{$this->tiktok_handle}" : null;
    }

    public function getTwitchUrlAttribute()
    {
        return $this->twitch_handle ? "https://twitch.tv/{$this->twitch_handle}" : null;
    }

    public function hasSocialLinks(): bool
    {
        return $this->instagram_url || $this->youtube_url || $this->facebook_url || $this->x_url || $this->tiktok_url || $this->twitch_url || $this->website_url || $this->other_social_links;
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function photoshoots()
    {
        return $this->hasMany(Photoshoot::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function galleries()
    {
        return $this->hasMany(Gallery::class);
    }

    public function moodboards()
    {
        return $this->hasMany(Moodboard::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function contractTemplates()
    {
        return $this->hasMany(ContractTemplate::class);
    }

    public function bioLinks()
    {
        return $this->hasMany(BioLink::class)->orderBy('order');
    }

    public function updateBrandLogo(UploadedFile $image)
    {
        tap($this->brand_logo_path, function ($previous) use ($image) {
            $this->update([
                'brand_logo_path' => $image->store(
                    $this->storage_path, ['disk' => config('picstome.disk')]
                ),
            ]);

            if ($previous) {
                Storage::disk(config('picstome.disk'))->delete($previous);
            }
        });
    }

    protected function brandLogoUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->brand_logo_path) {
                return null;
            }

            $originalUrl = Storage::disk(config('picstome.disk'))->url($this->brand_logo_path);

            return Str::of($originalUrl)
                ->when(config('picstome.photo_cdn_domain') === 'wsrv.nl', function (Stringable $string) {
                    return Str::of('https://wsrv.nl/')
                        ->append('?url=', urlencode($string->toString()))
                        ->append('&q=90&output=webp');
                })
                ->when(config('picstome.photo_cdn_domain') === 'bunny', function (Stringable $string) {
                    return $string->append('?quality=90&format=webp');
                });
        });
    }

    public function updateBrandWatermark(UploadedFile $image)
    {
        tap($this->brand_watermark_path, function ($previous) use ($image) {
            $this->update([
                'brand_watermark_path' => $image->store(
                    $this->storage_path, ['disk' => config('picstome.disk')]
                ),
            ]);

            if ($previous) {
                Storage::disk(config('picstome.disk'))->delete($previous);
            }
        });
    }

    protected function brandWatermarkUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->brand_watermark_path) {
                return null;
            }

            $originalUrl = Storage::disk(config('picstome.disk'))->url($this->brand_watermark_path);

            return Str::of($originalUrl)
                ->when(config('picstome.photo_cdn_domain') === 'wsrv.nl', function (Stringable $string) {
                    return Str::of('https://wsrv.nl/')
                        ->append('?url=', urlencode($string->toString()))
                        ->append('&q=90&output=webp');
                })
                ->when(config('picstome.photo_cdn_domain') === 'bunny', function (Stringable $string) {
                    return $string->append('?quality=90&format=webp');
                });
        });
    }

    public function updateBrandLogoIcon(UploadedFile $image)
    {
        tap($this->brand_logo_icon_path, function ($previous) use ($image) {
            $this->update([
                'brand_logo_icon_path' => $image->store(
                    $this->storage_path, ['disk' => config('picstome.disk')]
                ),
            ]);

            if ($previous) {
                Storage::disk(config('picstome.disk'))->delete($previous);
            }
        });
    }

    protected function brandLogoIconUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->brand_logo_icon_path) {
                return null;
            }

            $originalUrl = Storage::disk(config('picstome.disk'))->url($this->brand_logo_icon_path);

            return Str::of($originalUrl)
                ->when(config('picstome.photo_cdn_domain') === 'wsrv.nl', function (Stringable $string) {
                    return Str::of('https://wsrv.nl/')
                        ->append('?url=', urlencode($string->toString()))
                        ->append('&q=90&output=webp');
                })
                ->when(config('picstome.photo_cdn_domain') === 'bunny', function (Stringable $string) {
                    return $string->append('?quality=90&format=webp');
                });
        });
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "teams/{$this->id}";
        });
    }

    protected function storageUsedGb(): Attribute
    {
        return Attribute::get(function () {
            $gb = $this->calculateStorageUsed() / 1073741824;

            return number_format($gb, 2).' GB';
        });
    }

    protected function storageLimitGb(): Attribute
    {
        return Attribute::get(function () {
            $gb = $this->storage_limit / 1073741824;

            return number_format($gb, 0).' GB';
        });
    }

    protected function storageUsedPercent(): Attribute
    {
        return Attribute::get(function () {
            if ($this->storage_limit === null) {
                return null;
            }
            if ($this->storage_limit == 0) {
                return 100;
            }

            $percent = ($this->calculateStorageUsed() / $this->storage_limit) * 100;

            return number_format($percent);
        });
    }

    protected function hasUnlimitedStorage(): Attribute
    {
        return Attribute::get(fn () => is_null($this->storage_limit));
    }

    protected function storageLimit(): Attribute
    {
        return Attribute::get(function () {
            return $this->custom_storage_limit ?? null;
        });
    }

    protected function hasUnlimitedContracts(): Attribute
    {
        return Attribute::get(function () {
            return is_null($this->monthly_contract_limit);
        });
    }

    public function stripeName()
    {
        return $this->owner->name ?? null;
    }

    public function stripeEmail()
    {
        return $this->owner->email ?? null;
    }

    /**
     * Dynamically calculate total storage used by all galleries/photos and moodboard photos for this team.
     *
     * @return int Total bytes used
     */
    public function calculateStorageUsed(): int
    {
        $galleryStorage = DB::table('photos')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->where('galleries.team_id', $this->id)
            ->sum('photos.size');

        $moodboardStorage = DB::table('moodboard_photos')
            ->join('moodboards', 'moodboard_photos.moodboard_id', '=', 'moodboards.id')
            ->where('moodboards.team_id', $this->id)
            ->sum('moodboard_photos.size');

        return $galleryStorage + $moodboardStorage;
    }

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string  $type
     * @param  string|null  $price
     * @return bool
     */
    public function subscribed($type = 'default', $price = null)
    {
        if ($this->lifetime) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Check if Stripe onboarding is complete for this team.
     */
    public function hasCompletedOnboarding(): bool
    {
        if ($this->stripe_test_mode) {
            return (bool) $this->stripe_test_onboarded;
        }

        return (bool) $this->stripe_onboarded;
    }

    /**
     * Mark this team as Stripe onboarded.
     */
    public function markOnboarded(): void
    {
        if ($this->stripe_test_mode) {
            $this->update(['stripe_test_onboarded' => true]);

            return;
        }

        $this->update(['stripe_onboarded' => true]);
    }

    /**
     * Dismiss a dashboard setup step for this team.
     */
    public function dismissSetupStep(string $step): void
    {
        $steps = $this->dismissed_setup_steps ?? [];

        if (! in_array($step, $steps)) {
            $steps[] = $step;
            $this->update(['dismissed_setup_steps' => $steps]);
        }
    }
}
