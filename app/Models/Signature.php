<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Signature extends Model
{
    /** @use HasFactory<\Database\Factories\SignatureFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'signed_at' => 'datetime',
            'birthday' => 'datetime:Y-m-d',
        ];
    }

    public static function booted()
    {
        static::creating(function (Signature $signature) {
            if (empty($signature->ulid)) {
                $signature->ulid = Str::ulid();
            }
        });
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function scopeUnsigned($query)
    {
        return $query->whereNull('signed_at');
    }

    public function scopeSigned($query)
    {
        return $query->whereNotNull('signed_at');
    }

    public function markAsSigned()
    {
        return $this->update(['signed_at' => Carbon::now()]);
    }

    public function isSigned()
    {
        return $this->signed_at !== null;
    }

    public function updateSignatureImage(UploadedFile $image)
    {
        return $this->update([
            'signature_image_path' => $image->store(
                $this->storage_path, 'public'
            ),
        ]);
    }

    protected function signatureImageUrl(): Attribute
    {
        return Attribute::get(function () {
            return $this->signature_image_path
                    ? Storage::disk('public')->url($this->signature_image_path)
                    : null;
        });
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "{$this->contract->storage_path}/signatures";
        });
    }

    protected function formattedSignedAt(): Attribute
    {
        return Attribute::get(function () {
            return $this->signed_at?->isoFormat('MMM D, YYYY');
        });
    }

    protected function formattedBirthday(): Attribute
    {
        return Attribute::get(function () {
            return $this->birthday?->isoFormat('MMM D, YYYY');
        });
    }

    public function deleteFromDisk()
    {
        if ($this->signature_image_path) {
            DeleteFromDisk::dispatch($this->signature_image_path);
        }

        return $this;
    }
}
