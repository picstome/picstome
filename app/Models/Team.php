<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Team extends Model
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    public function photoshoots()
    {
        return $this->hasMany(Photoshoot::class);
    }

    public function galleries()
    {
        return $this->hasMany(Gallery::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function contractTemplates()
    {
        return $this->hasMany(ContractTemplate::class);
    }

    public function updateBrandLogo(UploadedFile $image)
    {
        tap($this->brand_logo_path, function ($previous) use ($image) {
            $this->update([
                'brand_logo_path' => $image->store(
                    $this->storage_path, ['disk' => 'public']
                ),
            ]);

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        });
    }

    protected function brandLogoUrl(): Attribute
    {
        return Attribute::get(function () {
            return $this->brand_logo_path
                    ? Storage::disk('public')->url($this->brand_logo_path)
                    : null;
        });
    }

    public function updateBrandWatermark(UploadedFile $image)
    {
        tap($this->brand_watermark_path, function ($previous) use ($image) {
            $this->update([
                'brand_watermark_path' => $image->store(
                    $this->storage_path, ['disk' => 'public']
                ),
            ]);

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        });
    }

    protected function brandWatermarkUrl(): Attribute
    {
        return Attribute::get(function () {
            return $this->brand_watermark_path
                    ? Storage::disk('public')->url($this->brand_watermark_path)
                    : null;
        });
    }

    public function updateBrandLogoIcon(UploadedFile $image)
    {
        tap($this->brand_logo_icon_path, function ($previous) use ($image) {
            $this->update([
                'brand_logo_icon_path' => $image->store(
                    $this->storage_path, ['disk' => 'public']
                ),
            ]);

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        });
    }

    protected function brandLogoIconUrl(): Attribute
    {
        return Attribute::get(function () {
            return $this->brand_logo_icon_path
                    ? Storage::disk('public')->url($this->brand_logo_icon_path)
                    : null;
        });
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "teams/{$this->id}";
        });
    }
}
