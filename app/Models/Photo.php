<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    /** @use HasFactory<\Database\Factories\PhotosFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'size' => 'integer',
            'favorited_at' => 'datetime',
        ];
    }

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    public function scopeFavorited($query)
    {
        return $query->whereNotNull('favorited_at');
    }

    public function scopeUnfavorited($query)
    {
        return $query->whereNull('favorited_at');
    }

    public function toggleFavorite()
    {
        $this->update(['favorited_at' => $this->favorited_at ? null : Carbon::now()]);
    }

    public function isFavorited()
    {
        return $this->favorited_at !== null;
    }

    public function next()
    {
        return $this->orderBy('name')
            ->where('gallery_id', $this->gallery->id)
            ->where('name', '>', $this->name)
            ->first();
    }

    public function previous()
    {
        return $this->orderByDesc('name')
            ->where('gallery_id', $this->gallery->id)
            ->where('name', '<', $this->name)
            ->first();
    }

    public function nextFavorite()
    {
        return $this->favorited()
            ->orderBy('name')
            ->where('gallery_id', $this->gallery->id)
            ->where('name', '>', $this->name)
            ->first();
    }

    public function previousFavorite()
    {
        return $this->favorited()
            ->orderByDesc('name')
            ->where('gallery_id', $this->gallery->id)
            ->where('name', '<', $this->name)
            ->first();
    }

    public function deleteFromDisk()
    {
        if ($this->path) {
            DeleteFromDisk::dispatch($this->path);
        }

        if ($this->thumb_path) {
            DeleteFromDisk::dispatch($this->thumb_path);
        }

        return $this;
    }

    public function download()
    {
        return Storage::disk('public')->download($this->path, $this->name);
    }

    protected function url(): Attribute
    {
        return Attribute::get(function (): string {
            return Storage::disk('public')->url($this->path);
        });
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(function (): string {
            return $this->thumb_path
                ? Storage::disk('public')->url($this->thumb_path)
                : $this->url;
        });
    }
}
